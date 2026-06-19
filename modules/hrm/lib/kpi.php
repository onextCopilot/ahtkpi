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
