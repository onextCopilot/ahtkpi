<?php
/**
 * URL Helper Functions
 * 
 * Provides helper functions for generating clean URLs
 */

// Base URL của ứng dụng
define('BASE_URL', '/AHT%20KPI/');

/**
 * Generate URL
 * 
 * @param string $path Path without leading slash
 * @return string Full URL
 */
function url($path = '')
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * Redirect to a path
 * 
 * @param string $path Path to redirect to
 * @param int $code HTTP status code (default: 302)
 */
function redirect($path, $code = 302)
{
    header('Location: ' . url($path), true, $code);
    exit();
}

/**
 * Get current URL path
 * 
 * @return string Current path
 */
function current_path()
{
    $request_uri = $_SERVER['REQUEST_URI'];
    $base_url = BASE_URL;
    return str_replace($base_url, '', $request_uri);
}

/**
 * Check if current path matches
 * 
 * @param string $path Path to check
 * @return bool
 */
function is_current_path($path)
{
    return current_path() === ltrim($path, '/');
}

/**
 * Generate asset URL
 * 
 * @param string $asset Asset path (e.g., 'css/style.css')
 * @return string Full asset URL
 */
function asset($asset)
{
    return url('assets/' . ltrim($asset, '/'));
}
