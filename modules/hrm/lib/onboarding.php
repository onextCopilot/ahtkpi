<?php
/**
 * Onboarding 60-day plan helpers (SOP On-boarding).
 */
require_once __DIR__ . '/core.php';

/** Owner-role labels used inside onboarding tasks/checkpoints. */
function hrm_onb_owner_label(string $key): string
{
    $m = ['hr' => 'HR/TA', 'ta' => 'TA', 'manager' => 'Direct Manager', 'buddy' => 'Buddy', 'bc' => 'BC Director', 'newhire' => 'Nhân sự mới'];
    return $m[$key] ?? $key;
}

/** Checkpoint definitions: key => [label, day-offset, owner_role]. */
function hrm_onb_checkpoints(): array
{
    return [
        'day3'  => ['Ngày 3 - Gặp gỡ & môi trường',          3,  'ta'],
        'day5'  => ['Ngày 5 - Kiểm tra hiểu việc',           5,  'manager'],
        'week2' => ['Tuần 2 - Checkpoint hội nhập',          14, 'manager'],
        'day30' => ['Ngày 30 - Review (quan trọng nhất)',    30, 'manager'],
        'week6' => ['Tuần 6 - Career Discussion',            42, 'manager'],
        'day60' => ['Ngày 60 - Final Probation Review',      60, 'manager'],
    ];
}

/** (Re)generate the full 60-day plan: pre-board tasks, day-1 orientations, checkpoints. */
function hrm_onb_generate_plan(mysqli $conn, int $onbId, string $startDate): void
{
    $conn->query("DELETE FROM hrm_onboarding_tasks WHERE onboarding_id=$onbId");
    $conn->query("DELETE FROM hrm_checkpoints WHERE onboarding_id=$onbId");

    $start = strtotime($startDate);
    $dateAt = fn(int $off) => $start ? date('Y-m-d', strtotime("+$off days", $start)) : null;

    // phase, title, owner_role, day-offset (relative to start; pre-board = -3)
    $tasks = [
        ['preboarding', 'Chuẩn bị Laptop', 'hr', -3],
        ['preboarding', 'Tạo Email công ty', 'hr', -3],
        ['preboarding', 'Tài khoản Odoo', 'hr', -2],
        ['preboarding', 'Tài khoản Jira', 'hr', -2],
        ['preboarding', 'Tài khoản Git', 'hr', -2],
        ['preboarding', 'Teams / Slack', 'hr', -2],
        ['preboarding', 'Chỗ ngồi', 'hr', -1],
        ['preboarding', 'Kế hoạch 60 ngày', 'manager', -3],
        ['preboarding', 'Phân công Buddy', 'manager', -3],
        ['day1', 'HR Orientation (1h): AHT Group, tầm nhìn, giá trị, quy chế, ESOP, KPI', 'hr', 0],
        ['day1', 'Manager Orientation (1h): BC, team, khách hàng, dự án, KPI thử việc', 'manager', 0],
        ['day1', 'Buddy Orientation (30p): thành viên, quy trình, công cụ', 'buddy', 0],
    ];
    $st = $conn->prepare('INSERT INTO hrm_onboarding_tasks (onboarding_id,phase,title,owner_role,due_date) VALUES (?,?,?,?,?)');
    foreach ($tasks as $t) {
        $due = $dateAt($t[3]);
        $st->bind_param('issss', $onbId, $t[0], $t[1], $t[2], $due);
        $st->execute();
    }

    $sc = $conn->prepare('INSERT INTO hrm_checkpoints (onboarding_id,checkpoint_key,due_date,owner_role) VALUES (?,?,?,?)');
    foreach (hrm_onb_checkpoints() as $key => $def) {
        $due = $dateAt($def[1]);
        $sc->bind_param('isss', $onbId, $key, $due, $def[2]);
        $sc->execute();
    }
}
