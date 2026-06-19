<?php
/**
 * AHT Talent – REST API
 * Base URL: /wp-json/aht/v1/
 *
 * Endpoints:
 *   POST   /jobs        – Create a job
 *   GET    /jobs        – List jobs
 *   GET    /jobs/{id}   – Get a job
 *   PUT    /jobs/{id}   – Update a job
 *   DELETE /jobs/{id}   – Delete a job
 *
 * Auth: Header  X-API-Key: <your-key>
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Allow Base64 Images ────────────────────────────────────────
add_filter( 'kses_allowed_protocols', function( $protocols ) {
    if ( ! in_array( 'data', $protocols, true ) ) {
        $protocols[] = 'data';
    }
    return $protocols;
} );

// ── Department map ─────────────────────────────────────────────
define( 'AHT_DEPARTMENTS', array(
    'IT'                  => 989,
    'BFSI'                => 1134,
    'Sales/Marketing'     => 346,
    'BackOffice'          => 374,
    'Akdemy'              => 1274,
    'Remote/Hybrid/Expat' => 1135,
) );

// ── Register routes ────────────────────────────────────────────
add_action( 'rest_api_init', function () {

    register_rest_route( 'aht/v1', '/jobs', array(
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'aht_api_create_job',
            'permission_callback' => 'aht_api_auth',
            'args'                => aht_api_job_args( 'create' ),
        ),
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'aht_api_list_jobs',
            'permission_callback' => 'aht_api_auth',
            'args'                => array(
                'per_page' => array(
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ),
                'page' => array(
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ),
                'department' => array(
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'status' => array(
                    'default'           => 'publish',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ),
    ) );

    register_rest_route( 'aht/v1', '/jobs/(?P<id>\d+)', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'aht_api_get_job',
            'permission_callback' => 'aht_api_auth',
        ),
        array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => 'aht_api_update_job',
            'permission_callback' => 'aht_api_auth',
            'args'                => aht_api_job_args( 'update' ),
        ),
        array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'aht_api_delete_job',
            'permission_callback' => 'aht_api_auth',
        ),
    ) );
} );

// ── Auth ───────────────────────────────────────────────────────
function aht_api_auth( WP_REST_Request $request ) {
    $stored_key = get_option( 'aht_api_key', '' );
    if ( empty( $stored_key ) ) {
        return new WP_Error( 'no_api_key', 'API key chưa được cấu hình trên server.', array( 'status' => 500 ) );
    }

    $provided = $request->get_header( 'X-API-Key' );
    if ( empty( $provided ) ) {
        return new WP_Error( 'missing_api_key', 'Thiếu header X-API-Key.', array( 'status' => 401 ) );
    }

    if ( ! hash_equals( $stored_key, $provided ) ) {
        return new WP_Error( 'invalid_api_key', 'API key không hợp lệ.', array( 'status' => 403 ) );
    }

    return true;
}

// ── Arg definitions ────────────────────────────────────────────
function aht_api_job_args( $mode = 'create' ) {
    $required = ( $mode === 'create' );
    return array(
        'title' => array(
            'required'          => $required,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => 'Tên vị trí tuyển dụng.',
        ),
        'content' => array(
            'required'          => false,
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'description'       => 'Mô tả chi tiết (HTML được phép).',
        ),
        'department' => array(
            'required'          => false,
            'type'              => 'string',
            'enum'              => array_keys( AHT_DEPARTMENTS ),
            'description'       => 'Phòng ban: ' . implode( ', ', array_keys( AHT_DEPARTMENTS ) ),
        ),
        'salary' => array(
            'required'          => false,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => 'Mức lương, VD: "Thương lượng" hoặc "$2000 - $3000".',
        ),
        'location' => array(
            'required'          => false,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => 'Địa điểm làm việc.',
        ),
        'deadline' => array(
            'required'          => false,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => 'Hạn nộp hồ sơ, định dạng DD/MM/YYYY.',
            'validate_callback' => function ( $value ) {
                if ( $value && ! preg_match( '/^\d{2}\/\d{2}\/\d{4}$/', $value ) ) {
                    return new WP_Error( 'invalid_deadline', 'deadline phải có định dạng DD/MM/YYYY.' );
                }
                return true;
            },
        ),
        'apply_url' => array(
            'required'          => false,
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'validate_callback' => function ( $value ) {
                if ( $value && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return new WP_Error( 'invalid_url', 'apply_url phải là URL hợp lệ.' );
                }
                return true;
            },
            'description'       => 'Link apply bên ngoài (nếu có).',
        ),
        'status' => array(
            'required'          => false,
            'type'              => 'string',
            'enum'              => array( 'publish', 'draft' ),
            'default'           => 'publish',
            'description'       => 'Trạng thái: publish | draft.',
        ),
    );
}

// ── Helpers ────────────────────────────────────────────────────
function aht_api_format_job( $post ) {
    $meta = get_post_meta( $post->ID );
    $dept_id   = isset( $meta['aht_job_department'][0] ) ? (int) $meta['aht_job_department'][0] : null;
    $dept_name = $dept_id ? array_search( $dept_id, AHT_DEPARTMENTS ) : null;

    return array(
        'id'         => $post->ID,
        'title'      => $post->post_title,
        'content'    => $post->post_content,
        'status'     => $post->post_status,
        'slug'       => $post->post_name,
        'url'        => get_permalink( $post->ID ),
        'created_at' => $post->post_date_gmt,
        'updated_at' => $post->post_modified_gmt,
        'department' => $dept_name ?: null,
        'salary'     => $meta['aht_job_salary'][0]     ?? null,
        'location'   => $meta['aht_job_location'][0]   ?? null,
        'deadline'   => $meta['aht_job_deadline'][0]   ?? null,
        'apply_url'  => $meta['aht_job_apply_url'][0]  ?? null,
    );
}

function aht_api_save_meta( $post_id, WP_REST_Request $request ) {
    $meta_map = array(
        'salary'     => 'aht_job_salary',
        'location'   => 'aht_job_location',
        'deadline'   => 'aht_job_deadline',
        'apply_url'  => 'aht_job_apply_url',
    );
    foreach ( $meta_map as $param => $meta_key ) {
        if ( $request->has_param( $param ) ) {
            update_post_meta( $post_id, $meta_key, $request->get_param( $param ) );
        }
    }
    if ( $request->has_param( 'department' ) ) {
        $dept = $request->get_param( 'department' );
        $dept_id = AHT_DEPARTMENTS[ $dept ] ?? null;
        if ( $dept_id ) {
            update_post_meta( $post_id, 'aht_job_department', $dept_id );
        }
    }
}

// ── CREATE ─────────────────────────────────────────────────────
function aht_api_create_job( WP_REST_Request $request ) {
    $post_id = wp_insert_post( array(
        'post_type'    => 'aht_job',
        'post_title'   => $request->get_param( 'title' ),
        'post_content' => $request->get_param( 'content' ) ?? '',
        'post_status'  => $request->get_param( 'status' ) ?? 'publish',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'insert_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
    }

    aht_api_save_meta( $post_id, $request );

    $post = get_post( $post_id );
    return new WP_REST_Response( aht_api_format_job( $post ), 201 );
}

// ── LIST ───────────────────────────────────────────────────────
function aht_api_list_jobs( WP_REST_Request $request ) {
    $args = array(
        'post_type'      => 'aht_job',
        'post_status'    => $request->get_param( 'status' ),
        'posts_per_page' => $request->get_param( 'per_page' ),
        'paged'          => $request->get_param( 'page' ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $dept = $request->get_param( 'department' );
    if ( $dept && isset( AHT_DEPARTMENTS[ $dept ] ) ) {
        $args['meta_query'] = array(
            array(
                'key'   => 'aht_job_department',
                'value' => AHT_DEPARTMENTS[ $dept ],
            ),
        );
    }

    $query = new WP_Query( $args );
    $jobs  = array_map( 'aht_api_format_job', $query->posts );

    $response = new WP_REST_Response( $jobs, 200 );
    $response->header( 'X-Total',       $query->found_posts );
    $response->header( 'X-Total-Pages', $query->max_num_pages );
    return $response;
}

// ── GET ONE ────────────────────────────────────────────────────
function aht_api_get_job( WP_REST_Request $request ) {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || $post->post_type !== 'aht_job' ) {
        return new WP_Error( 'not_found', 'Không tìm thấy job.', array( 'status' => 404 ) );
    }
    return new WP_REST_Response( aht_api_format_job( $post ), 200 );
}

// ── UPDATE ─────────────────────────────────────────────────────
function aht_api_update_job( WP_REST_Request $request ) {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || $post->post_type !== 'aht_job' ) {
        return new WP_Error( 'not_found', 'Không tìm thấy job.', array( 'status' => 404 ) );
    }

    $data = array( 'ID' => $post->ID );
    if ( $request->has_param( 'title' ) )   $data['post_title']   = $request->get_param( 'title' );
    if ( $request->has_param( 'content' ) ) $data['post_content'] = $request->get_param( 'content' );
    if ( $request->has_param( 'status' ) )  $data['post_status']  = $request->get_param( 'status' );

    $result = wp_update_post( $data, true );
    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'update_failed', $result->get_error_message(), array( 'status' => 500 ) );
    }

    aht_api_save_meta( $post->ID, $request );

    return new WP_REST_Response( aht_api_format_job( get_post( $post->ID ) ), 200 );
}

// ── DELETE ─────────────────────────────────────────────────────
function aht_api_delete_job( WP_REST_Request $request ) {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || $post->post_type !== 'aht_job' ) {
        return new WP_Error( 'not_found', 'Không tìm thấy job.', array( 'status' => 404 ) );
    }

    $deleted = wp_delete_post( $post->ID, true ); // true = bypass trash
    if ( ! $deleted ) {
        return new WP_Error( 'delete_failed', 'Xóa thất bại.', array( 'status' => 500 ) );
    }

    return new WP_REST_Response( array( 'deleted' => true, 'id' => $post->ID ), 200 );
}
