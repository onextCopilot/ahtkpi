<?php
/**
 * Recruitment KPI metrics (SOP §4) + funnel counts, computed for a period.
 */
require_once __DIR__ . '/core.php';

/** Resolve a preset period to [from, to, label]. */
function hrm_kpi_period(string $preset): array
{
    $to = date('Y-m-d 23:59:59');
    if ($preset === 'all')      { return ['2000-01-01 00:00:00', $to, 'Toàn bộ thời gian']; }
    if ($preset === 'quarter')  { $q = (int)floor((date('n') - 1) / 3); $m = $q * 3 + 1; return [date('Y-' . sprintf('%02d', $m) . '-01 00:00:00'), $to, 'Quý ' . ($q + 1) . '/' . date('Y')]; }
    return [date('Y-m-01 00:00:00'), $to, 'Tháng ' . date('m/Y')]; // month (default)
}

/** One scalar from a prepared (from,to) query. */
function hrm_kpi_scalar(mysqli $conn, string $sql, string $from, string $to)
{
    $st = $conn->prepare($sql);
    $st->bind_param('ss', $from, $to);
    $st->execute();
    $row = $st->get_result()->fetch_row();
    return $row ? $row[0] : 0;
}

/** Full KPI + funnel snapshot for [from,to]. */
function hrm_kpi_metrics(mysqli $conn, string $from, string $to): array
{
    // Funnel (period-scoped by applied_at, except open positions which is point-in-time).
    $open = (int)($conn->query("SELECT COUNT(*) FROM hrm_jobs WHERE status='open'")->fetch_row()[0] ?? 0);
    $cv   = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_applications WHERE applied_at BETWEEN ? AND ?", $from, $to);
    $intv = (int)hrm_kpi_scalar($conn, "SELECT COUNT(DISTINCT application_id) FROM hrm_interviews WHERE created_at BETWEEN ? AND ?", $from, $to);
    $off  = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_offers WHERE created_at BETWEEN ? AND ?", $from, $to);
    $join = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_applications WHERE status='hired' AND updated_at BETWEEN ? AND ?", $from, $to);

    // Time to hire (avg days applied -> hired).
    $tth = hrm_kpi_scalar($conn, "SELECT AVG(TIMESTAMPDIFF(DAY, applied_at, updated_at)) FROM hrm_applications WHERE status='hired' AND updated_at BETWEEN ? AND ?", $from, $to);
    $tth = $tth !== null ? round((float)$tth, 1) : null;

    // Offer acceptance rate.
    $acc = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_offers WHERE status='accepted' AND responded_at BETWEEN ? AND ?", $from, $to);
    $dec = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_offers WHERE status='declined' AND responded_at BETWEEN ? AND ?", $from, $to);
    $offerRate = ($acc + $dec) > 0 ? round($acc * 100 / ($acc + $dec)) : null;

    // Probation pass rate.
    $conf = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_probation_reviews WHERE decision='confirm' AND reviewed_at BETWEEN ? AND ?", $from, $to);
    $allP = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_probation_reviews WHERE decision IS NOT NULL AND reviewed_at BETWEEN ? AND ?", $from, $to);
    $probRate = $allP > 0 ? round($conf * 100 / $allP) : null;

    // SLA: CV screening responded within 48h (on-time satisfied vs total).
    $slaTotal = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_sla_events WHERE event_key='screening' AND created_at BETWEEN ? AND ?", $from, $to);
    $slaOk    = (int)hrm_kpi_scalar($conn, "SELECT COUNT(*) FROM hrm_sla_events WHERE event_key='screening' AND satisfied_at IS NOT NULL AND satisfied_at <= due_at AND created_at BETWEEN ? AND ?", $from, $to);
    $slaRate  = $slaTotal > 0 ? round($slaOk * 100 / $slaTotal) : null;

    return [
        'funnel' => [
            'open' => $open, 'cv' => $cv, 'interviewed' => $intv, 'offers' => $off, 'joined' => $join,
        ],
        'kpi' => [
            ['key' => 'tth',   'label' => 'Time to Hire',        'value' => $tth,       'unit' => ' ngày', 'target' => '≤ 30', 'ok' => $tth === null ? null : $tth <= 30],
            ['key' => 'offer', 'label' => 'Offer Acceptance',    'value' => $offerRate, 'unit' => '%',     'target' => '≥ 75%', 'ok' => $offerRate === null ? null : $offerRate >= 75],
            ['key' => 'prob',  'label' => 'Probation Pass Rate', 'value' => $probRate,  'unit' => '%',     'target' => '≥ 80%', 'ok' => $probRate === null ? null : $probRate >= 80],
            ['key' => 'sla',   'label' => 'SLA phản hồi CV ≤48h', 'value' => $slaRate,  'unit' => '%',     'target' => '100%',  'ok' => $slaRate === null ? null : $slaRate >= 95],
        ],
    ];
}

/**
 * Point-in-time counters cho dashboard điều hành (không phụ thuộc kỳ).
 * Mỗi số là "đang ở trạng thái này NGAY BÂY GIỜ".
 */
function hrm_dashboard_counts(mysqli $conn): array
{
    $q = fn(string $sql): int => (int)($conn->query($sql)->fetch_row()[0] ?? 0);
    return [
        'hrf_pending'   => $q("SELECT COUNT(*) FROM hrm_requests WHERE status='pending'"),
        'jobs_open'     => $q("SELECT COUNT(*) FROM hrm_jobs WHERE status='open'"),
        'apps_active'   => $q("SELECT COUNT(*) FROM hrm_applications WHERE status='active'"),
        'offers_out'    => $q("SELECT COUNT(*) FROM hrm_offers WHERE status IN ('sent','pending_approval')"),
        'onb_active'    => $q("SELECT COUNT(*) FROM hrm_onboarding WHERE status='active'"),
        'probation_due' => $q("SELECT COUNT(*) FROM hrm_checkpoints WHERE status='pending' AND due_date IS NOT NULL AND due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"),
    ];
}

/**
 * Cảnh báo SLA / điểm tắc nghẽn cần xử lý: HRF quá hạn duyệt, ứng viên kẹt giai
 * đoạn quá SLA, task onboarding quá hạn. Trả mảng ['type','color','text','due','link'].
 */
function hrm_dashboard_alerts(mysqli $conn): array
{
    $alerts = [];

    // 1. HRF quá hạn duyệt (bước phê duyệt pending mà đã quá due_at).
    $res = $conn->query("SELECT a.due_at, rq.id, rq.code, rq.title
        FROM hrm_approvals a JOIN hrm_requests rq ON rq.id = a.entity_id
        WHERE a.entity_type='hrf' AND a.status='pending' AND a.due_at IS NOT NULL AND a.due_at < NOW()
        ORDER BY a.due_at LIMIT 15");
    while ($res && ($r = $res->fetch_assoc())) {
        $alerts[] = ['type' => 'HRF quá hạn duyệt', 'color' => '#dc2626',
            'text' => trim(($r['code'] ?: '') . ' · ' . $r['title']),
            'due' => $r['due_at'], 'link' => '/hrm/requests?id=' . (int)$r['id']];
    }

    // 2. Ứng viên kẹt ở 1 giai đoạn vượt SLA của giai đoạn đó.
    $res = $conn->query("SELECT ap.id, c.full_name, s.name AS stage_name, s.sla_hours, ap.updated_at
        FROM hrm_applications ap
        JOIN hrm_pipeline_stages s ON s.id = ap.stage_id
        JOIN hrm_candidates c ON c.id = ap.candidate_id
        WHERE ap.status='active' AND s.sla_hours > 0
          AND ap.updated_at < DATE_SUB(NOW(), INTERVAL s.sla_hours HOUR)
        ORDER BY ap.updated_at LIMIT 15");
    while ($res && ($r = $res->fetch_assoc())) {
        $alerts[] = ['type' => 'Kẹt giai đoạn', 'color' => '#b45309',
            'text' => trim(($r['full_name'] ?: 'Ứng viên') . ' · ' . $r['stage_name'] . ' (SLA ' . (int)$r['sla_hours'] . 'h)'),
            'due' => $r['updated_at'], 'link' => '/hrm/application?id=' . (int)$r['id']];
    }

    // 3. Task onboarding quá hạn chưa hoàn thành.
    $res = $conn->query("SELECT t.id, t.title, t.due_date, o.id AS onb_id, o.candidate_name
        FROM hrm_onboarding_tasks t JOIN hrm_onboarding o ON o.id = t.onboarding_id
        WHERE t.done=0 AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
          AND o.status IN ('preboarding','active')
        ORDER BY t.due_date LIMIT 15");
    while ($res && ($r = $res->fetch_assoc())) {
        $alerts[] = ['type' => 'Onboarding trễ', 'color' => '#7c3aed',
            'text' => trim(($r['candidate_name'] ?: '') . ' · ' . $r['title']),
            'due' => $r['due_date'], 'link' => '/hrm/onboarding-detail?id=' . (int)$r['onb_id']];
    }

    return $alerts;
}

/**
 * Tải tuyển dụng theo phòng ban: số vị trí đang mở + ứng viên đang xử lý.
 * Trả mảng [name => ['open'=>int, 'active'=>int]] sắp theo tổng giảm dần.
 */
function hrm_dashboard_dept_load(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query("SELECT COALESCE(d.name,'(Chưa gán)') name, COUNT(*) c
        FROM hrm_jobs j LEFT JOIN departments d ON d.id = j.department_id
        WHERE j.status='open' GROUP BY j.department_id");
    while ($res && ($r = $res->fetch_assoc())) { $rows[$r['name']] = ['open' => (int)$r['c'], 'active' => 0]; }

    $res = $conn->query("SELECT COALESCE(d.name,'(Chưa gán)') name, COUNT(*) c
        FROM hrm_applications ap JOIN hrm_jobs j ON j.id = ap.job_id
        LEFT JOIN departments d ON d.id = j.department_id
        WHERE ap.status='active' GROUP BY j.department_id");
    while ($res && ($r = $res->fetch_assoc())) {
        if (!isset($rows[$r['name']])) { $rows[$r['name']] = ['open' => 0, 'active' => 0]; }
        $rows[$r['name']]['active'] = (int)$r['c'];
    }

    uasort($rows, fn($a, $b) => ($b['open'] + $b['active']) <=> ($a['open'] + $a['active']));
    return $rows;
}

/** Hoạt động gần đây từ audit log (kèm tên người thực hiện). */
function hrm_dashboard_activity(mysqli $conn, int $limit = 12): array
{
    $limit = max(1, min(50, $limit));
    $res = $conn->query("SELECT a.action, a.entity_type, a.entity_id, a.detail, a.created_at, u.full_name
        FROM hrm_audit_log a LEFT JOIN users u ON u.id = a.user_id
        ORDER BY a.id DESC LIMIT $limit");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
