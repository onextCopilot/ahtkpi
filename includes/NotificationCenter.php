<?php
/**
 * NotificationCenter — single source of truth that aggregates a user's
 * notifications from every source into one typed list.
 *
 * Sources:
 *   - pasx   : pasx_notifications (PAKD/PASX approval events), is_read = 0
 *   - debt   : debts overdue > 30 / > 60 days (AM, by full_name), minus
 *              entries already in debt_notifications_read
 *   - manual : debt_manual_warnings sent to this user, is_read = 0
 *   - kpi    : AM/BD quarter KPI/commission not yet confirmed (live reminder)
 *
 * Each item:
 *   key, kind, severity(info|warning|danger|success), title, body, link,
 *   link_label, created_at (Y-m-d H:i:s|null), dismissible(bool),
 *   mark(array|null)  // payload for markRead()
 *
 * Used by: the topbar bell, the /notifications page, and the email digest cron.
 */
class NotificationCenter
{
    /** Build the unified notification list for a user. */
    public static function items($conn, array $session): array
    {
        $uid = (int) ($session['user_id'] ?? 0);
        $full_name = $session['full_name'] ?? '';
        $is_am_bd = !empty($session['is_am_bd']);
        $items = [];
        if (!$uid) return $items;

        // ── PASX / PAKD notifications ─────────────────────────────────────────
        try {
            $pn = $conn->prepare("SELECT id, pakd_id, event, status, opp_name, submitted_by, message, created_at
                                  FROM pasx_notifications WHERE user_id = ? AND is_read = 0
                                  ORDER BY created_at DESC LIMIT 50");
            if ($pn) {
                $pn->bind_param("i", $uid);
                $pn->execute();
                $r = $pn->get_result();
                while ($row = $r->fetch_assoc()) {
                    $ev = $row['event'] ?? '';
                    if ($ev === 'ceo_approve_request') {
                        $sev = 'warning'; $title = 'Yêu cầu phê duyệt PASX';
                        $link = '/projects/ceo-review'; $label = 'Xem & Duyệt';
                    } elseif ($ev === 'ceo_approved') {
                        $sev = 'success'; $title = 'PASX đã được CEO phê duyệt';
                        $link = '/projects/pakd/edit?id=' . $row['pakd_id']; $label = 'Xem PAKD';
                    } elseif ($ev === 'ceo_rejected') {
                        $sev = 'danger'; $title = 'PASX bị CEO từ chối';
                        $link = '/projects/pakd/edit?id=' . $row['pakd_id']; $label = 'Xem PAKD';
                    } elseif (strpos($ev, 'milestone_') === 0) {
                        $sev = ($ev === 'milestone_deleted') ? 'danger' : (($ev === 'milestone_created') ? 'success' : 'info');
                        $title = ($ev === 'milestone_deleted') ? 'Milestone bị xoá'
                               : (($ev === 'milestone_created') ? 'Milestone mới' : 'Milestone cập nhật');
                        $link = '/projects/du-an/detail?id=' . $row['pakd_id']; $label = 'Xem dự án';
                    } else {
                        $sev = 'info'; $title = 'PASX ' . strtoupper($row['status'] ?? '');
                        $link = '/projects/pakd/edit?id=' . $row['pakd_id']; $label = 'Xem PAKD';
                    }
                    $body = $row['opp_name'] ?: ('PAKD #' . $row['pakd_id']);
                    if (!empty($row['submitted_by'])) $body .= ' · AM: ' . $row['submitted_by'];
                    elseif (!empty($row['message'])) $body .= ' · ' . mb_strimwidth($row['message'], 0, 80, '…');
                    $items[] = [
                        'key' => 'pasx:' . $row['id'], 'kind' => 'pasx', 'severity' => $sev,
                        'title' => $title, 'body' => $body, 'link' => $link, 'link_label' => $label,
                        'created_at' => $row['created_at'], 'dismissible' => true,
                        'mark' => ['type' => 'pasx', 'id' => (int) $row['id']],
                    ];
                }
                $pn->close();
            }
        } catch (\Throwable $e) { /* table may not exist yet */ }

        // ── Cảnh báo: invoice chưa add vào Debts (gửi từ Debts Check) ──────────
        try {
            $aw = $conn->prepare("SELECT id, invoice_name, company, penalty_points, message, created_at
                                  FROM debt_add_warnings WHERE am_user_id = ? AND is_acknowledged = 0
                                  ORDER BY created_at DESC LIMIT 50");
            if ($aw) {
                $aw->bind_param("i", $uid);
                $aw->execute();
                $r = $aw->get_result();
                while ($row = $r->fetch_assoc()) {
                    $items[] = [
                        'key' => 'debtadd:' . $row['id'], 'kind' => 'debt_add', 'severity' => 'danger',
                        'title' => 'Invoice chưa add vào Debts (−' . (int) $row['penalty_points'] . ' điểm KPI)',
                        'body' => $row['invoice_name'] . (!empty($row['company']) ? ' · ' . $row['company'] : ''),
                        'link' => '/invoices', 'link_label' => 'Add to Debts',
                        'created_at' => $row['created_at'], 'dismissible' => true,
                        'mark' => ['type' => 'debt_add', 'id' => (int) $row['id']],
                    ];
                }
                $aw->close();
            }
        } catch (\Throwable $e) { /* table may not exist yet */ }

        // ── Overdue debts (AM by name) ────────────────────────────────────────
        if ($is_am_bd && $full_name !== '') {
            try {
                $read = [];
                $rs = $conn->prepare("SELECT debt_id, warning_level FROM debt_notifications_read WHERE user_id = ?");
                if ($rs) {
                    $rs->bind_param("i", $uid); $rs->execute();
                    $rr = $rs->get_result();
                    while ($x = $rr->fetch_assoc()) $read[$x['debt_id'] . '_' . $x['warning_level']] = true;
                    $rs->close();
                }
                $st = $conn->prepare("SELECT id, client_name, project_name, expected_payment_date, odoo_invoice_id, invoice_date
                                      FROM debts WHERE am = ? AND payment_status = 'Not paid'
                                        AND expected_payment_date IS NOT NULL AND expected_payment_date > '2000-01-01'");
                if ($st) {
                    $st->bind_param("s", $full_name); $st->execute();
                    $res = $st->get_result();
                    $today = new DateTime(); $today->setTime(0, 0, 0);
                    while ($d = $res->fetch_assoc()) {
                        $exp = new DateTime($d['expected_payment_date']); $exp->setTime(0, 0, 0);
                        $diff = $today->diff($exp);
                        if (!$diff->invert) continue;            // not overdue yet
                        $level = $diff->days > 60 ? 60 : ($diff->days > 30 ? 30 : 0);
                        if ($level === 0) continue;
                        if (isset($read[$d['id'] . '_' . $level])) continue;
                        $m = date('m', strtotime($d['invoice_date'] ?? 'now'));
                        $y = date('Y', strtotime($d['invoice_date'] ?? 'now'));
                        $items[] = [
                            'key' => 'debt:' . $d['id'] . ':' . $level, 'kind' => 'debt',
                            'severity' => $level === 60 ? 'danger' : 'warning',
                            'title' => $level === 60 ? 'Công nợ quá hạn > 60 ngày' : 'Công nợ quá hạn > 30 ngày',
                            'body' => trim(($d['client_name'] ?? '') . ' — ' . ($d['project_name'] ?? '')) . ' · Hạn ' . date('d/m/Y', strtotime($d['expected_payment_date'])),
                            'link' => "/modules/my_debt?month=$m&year=$y&highlight_id=" . $d['id'],
                            'link_label' => 'Cập nhật', 'created_at' => $d['expected_payment_date'],
                            'dismissible' => true,
                            'mark' => ['type' => 'debt', 'debt_id' => (int) $d['id'], 'level' => $level],
                        ];
                    }
                    $st->close();
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Manual debt warnings sent to this user
            try {
                $mw = $conn->prepare("SELECT mw.id, mw.created_at, mw.warning_type, d.client_name, d.project_name, u.full_name AS sender
                                      FROM debt_manual_warnings mw
                                      JOIN debts d ON mw.debt_id = d.id
                                      JOIN users u ON mw.sender_id = u.id
                                      WHERE mw.receiver_id = ? AND mw.is_read = 0 ORDER BY mw.created_at DESC");
                if ($mw) {
                    $mw->bind_param("i", $uid); $mw->execute();
                    $r = $mw->get_result();
                    while ($x = $r->fetch_assoc()) {
                        $items[] = [
                            'key' => 'manual:' . $x['id'], 'kind' => 'manual', 'severity' => 'danger',
                            'title' => 'Cảnh báo công nợ từ ' . $x['sender'],
                            'body' => trim(($x['client_name'] ?? '') . ' — ' . ($x['project_name'] ?? '')),
                            'link' => '/my-debt', 'link_label' => 'Xem', 'created_at' => $x['created_at'],
                            'dismissible' => true, 'mark' => ['type' => 'manual', 'id' => (int) $x['id']],
                        ];
                    }
                    $mw->close();
                }
            } catch (\Throwable $e) { /* ignore */ }

            // ── KPI / commission of the current quarter not yet confirmed ─────
            try {
                $q = (int) ceil((int) date('n') / 3);
                $y = (int) date('Y');
                $tab = "Q{$q}_{$y}";
                $confirmed = false;
                $cres = $conn->query("SELECT type FROM sale_report_confirmations WHERE user_id = $uid AND quarter = '$tab' ORDER BY confirmed_at DESC LIMIT 1");
                if ($cres && ($c = $cres->fetch_assoc())) {
                    $confirmed = in_array($c['type'], ['confirmed', 'commission_confirmed'], true);
                }
                if (!$confirmed) {
                    $items[] = [
                        'key' => "kpi:$tab", 'kind' => 'kpi', 'severity' => 'info',
                        'title' => "KPI/Hoa hồng Quý $q chưa xác nhận",
                        'body' => "Hãy rà soát và xác nhận báo cáo bán hàng Quý $q/$y.",
                        'link' => '/sale-reports?quarter=' . $tab, 'link_label' => 'Xác nhận',
                        'created_at' => null, 'dismissible' => false, 'mark' => null,
                    ];
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        // Sort: dismissible/dated newest first, undated (live reminders) last.
        usort($items, function ($a, $b) {
            return strcmp($b['created_at'] ?? '0', $a['created_at'] ?? '0');
        });
        return $items;
    }

    /** Count of notifications (for the bell badge). */
    public static function count($conn, array $session): int
    {
        return count(self::items($conn, $session));
    }

    /**
     * Mark one notification read. $payload is an item's 'mark' array.
     * Returns true on success. Scoped to the given user for safety.
     */
    public static function markRead($conn, int $uid, array $payload): bool
    {
        $type = $payload['type'] ?? '';
        try {
            if ($type === 'pasx') {
                $st = $conn->prepare("UPDATE pasx_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $st->bind_param("ii", $payload['id'], $uid); return $st->execute();
            }
            if ($type === 'manual') {
                $st = $conn->prepare("UPDATE debt_manual_warnings SET is_read = 1 WHERE id = ? AND receiver_id = ?");
                $st->bind_param("ii", $payload['id'], $uid); return $st->execute();
            }
            if ($type === 'debt') {
                $st = $conn->prepare("INSERT IGNORE INTO debt_notifications_read (user_id, debt_id, warning_level) VALUES (?, ?, ?)");
                $st->bind_param("iii", $uid, $payload['debt_id'], $payload['level']); return $st->execute();
            }
            if ($type === 'debt_add') {
                $st = $conn->prepare("UPDATE debt_add_warnings SET is_acknowledged = 1, acknowledged_at = NOW() WHERE id = ? AND am_user_id = ?");
                $st->bind_param("ii", $payload['id'], $uid); return $st->execute();
            }
        } catch (\Throwable $e) {
            error_log('NotificationCenter::markRead failed: ' . $e->getMessage());
        }
        return false;
    }

    /** Mark every dismissible notification of the user as read. */
    public static function markAllRead($conn, array $session): int
    {
        $uid = (int) ($session['user_id'] ?? 0);
        $n = 0;
        foreach (self::items($conn, $session) as $it) {
            if (!empty($it['mark']) && self::markRead($conn, $uid, $it['mark'])) $n++;
        }
        return $n;
    }
}
