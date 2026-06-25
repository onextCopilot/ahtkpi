<?php
/**
 * Hồ sơ ứng viên 360 - tabs: tổng quan, kỹ năng/KN/học vấn, tệp, ứng tuyển, hoạt động.
 */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/history.php';
require_once __DIR__ . '/lib/shell.php';
hrm_require_login();
hrm_ensure_candidate_module($conn);

$id = (int)($_GET['id'] ?? 0);
$c = $conn->query("SELECT c.*, s.name AS source_name, ev.name AS event_name, u.full_name AS owner_name
    FROM hrm_candidates c
    LEFT JOIN hrm_candidate_sources s ON s.id=c.source_id
    LEFT JOIN hrm_events ev ON ev.id=c.event_id
    LEFT JOIN users u ON u.id=c.owner_id WHERE c.id=$id")->fetch_assoc();
if (!$c) { hrm_header('Không tìm thấy', '', 'candidates'); echo '<div class="rc-empty">Ứng viên không tồn tại.</div>'; hrm_footer(); exit; }

$tags     = array_column($conn->query("SELECT tag FROM hrm_candidate_tags WHERE candidate_id=$id ORDER BY tag")->fetch_all(MYSQLI_ASSOC), 'tag');
$skills   = $conn->query("SELECT * FROM hrm_candidate_skills WHERE candidate_id=$id ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$exps     = $conn->query("SELECT * FROM hrm_candidate_experience WHERE candidate_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$edus     = $conn->query("SELECT * FROM hrm_candidate_education WHERE candidate_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$attach   = $conn->query("SELECT * FROM hrm_candidate_attachments WHERE candidate_id=$id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$acts     = $conn->query("SELECT a.*, u.full_name AS actor FROM hrm_candidate_activities a LEFT JOIN users u ON u.id=a.actor_id WHERE a.candidate_id=$id ORDER BY a.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$reminders= $conn->query("SELECT r.*, u.full_name AS owner FROM hrm_candidate_reminders r LEFT JOIN users u ON u.id=r.owner_id WHERE r.candidate_id=$id AND r.done=0 ORDER BY r.due_at")->fetch_all(MYSQLI_ASSOC);
$allPools = $conn->query("SELECT id,name,color FROM hrm_pools WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$candPools = $conn->query("SELECT p.id,p.name,p.color FROM hrm_candidate_pools cp JOIN hrm_pools p ON p.id=cp.pool_id WHERE cp.candidate_id=$id AND p.active=1 ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$candPoolIds = array_column($candPools, 'id');
$apps = $conn->query("SELECT a.id, a.status, a.applied_at, j.title AS job_title, st.name AS stage_name
        FROM hrm_applications a JOIN hrm_jobs j ON j.id=a.job_id LEFT JOIN hrm_pipeline_stages st ON st.id=a.stage_id
        WHERE a.candidate_id = $id ORDER BY a.id DESC")->fetch_all(MYSQLI_ASSOC);
$openJobs = $conn->query("SELECT id, title FROM hrm_jobs WHERE status IN ('open','draft') ORDER BY COALESCE(source_created,source_start) DESC, CAST(external_id AS UNSIGNED) DESC LIMIT 300")->fetch_all(MYSQLI_ASSOC);
$sources = $conn->query("SELECT id, name FROM hrm_candidate_sources WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$events  = $conn->query("SELECT id, name FROM hrm_events WHERE active=1 ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$users   = $conn->query("SELECT id, full_name FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$statuses = hrm_candidate_statuses();
$appStatus = ['active'=>'Đang xử lý','hired'=>'Đã tuyển','rejected'=>'Từ chối','hold'=>'Giữ','withdrawn'=>'Rút'];
$actLabel = ['note'=>'Ghi chú','call'=>'Cuộc gọi','email'=>'Email','meeting'=>'Gặp mặt','create'=>'Tạo','update'=>'Cập nhật','stage'=>'Pipeline'];

// Status color map
$statusColorMap = [
    'new'=>['bg'=>'#eff6ff','text'=>'#1d4ed8'],
    'active'=>['bg'=>'#f0fdf4','text'=>'#15803d'],
    'hired'=>['bg'=>'#f0fdf4','text'=>'#166534'],
    'rejected'=>['bg'=>'#fef2f2','text'=>'#dc2626'],
    'hold'=>['bg'=>'#fffbeb','text'=>'#b45309'],
    'withdrawn'=>['bg'=>'#f1f5f9','text'=>'#64748b'],
];
$stColor = $statusColorMap[$c['status']] ?? ['bg'=>'#f1f5f9','text'=>'#475569'];
$initials = mb_strtoupper(mb_substr(trim($c['full_name']), 0, 1, 'UTF-8'));
$ratingInt = (int)$c['rating'];

hrm_header($c['full_name'], ($c['current_position'] ?: 'Ứng viên') . ' · ' . ($statuses[$c['status']] ?? $c['status']), 'candidates');
?>

<!-- ── PROFILE HERO ── -->
<div class="cd-hero">
    <div class="cd-hero-left">
        <div class="cd-avatar"><?= $initials ?></div>
        <div class="cd-hero-info">
            <h1 class="cd-hero-name"><?= h($c['full_name']) ?></h1>
            <?php if ($c['current_position']): ?>
                <div class="cd-hero-pos"><?= h($c['current_position']) ?></div>
            <?php endif; ?>
            <div class="cd-hero-meta">
                <?php if ($c['location']): ?>
                    <span class="cd-meta-pill">
                        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= h($c['location']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($c['years_exp']): ?>
                    <span class="cd-meta-pill">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= (float)$c['years_exp'] ?> năm KN
                    </span>
                <?php endif; ?>
                <?php if ($c['email']): ?>
                    <span class="cd-meta-pill">
                        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                        <?= h($c['email']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($c['phone']): ?>
                    <span class="cd-meta-pill">
                        <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.58 3.44 2 2 0 0 1 3.55 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.81a16 16 0 0 0 6.29 6.29l.91-.9a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <?= h($c['phone']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="cd-hero-right">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">
            <span class="cd-status-badge" style="background:<?= $stColor['bg'] ?>;color:<?= $stColor['text'] ?>">
                <?= h($statuses[$c['status']] ?? $c['status']) ?>
            </span>
            <?php if ($c['talent_pool']): ?>
                <span class="cd-status-badge" style="background:#f5f3ff;color:#7c3aed">⭐ Talent Pool</span>
            <?php endif; ?>
        </div>
        <?php if ($ratingInt): ?>
        <div class="cd-rating">
            <?php for ($i=1;$i<=5;$i++): ?>
                <span style="color:<?= $i<=$ratingInt?'#f59e0b':'#e2e8f0' ?>">★</span>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <div class="cd-hero-actions">
            <button class="rc-btn ghost" onclick="openHistory()">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Lịch sử
            </button>
            <button class="rc-btn" onclick="show('edit')">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Sửa thông tin
            </button>
        </div>
    </div>
</div>
<a href="/hrm/candidates" class="cd-back-link">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
    Kho ứng viên
</a>

<!-- ── TABS ── -->
<div class="cd-tabs">
    <button class="cd-tab on" data-t="overview" onclick="tab(this)">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Tổng quan
    </button>
    <button class="cd-tab" data-t="profile" onclick="tab(this)">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Kỹ năng &amp; Kinh nghiệm
    </button>
    <button class="cd-tab" data-t="files" onclick="tab(this)">
        <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        Tệp đính kèm <span class="cd-tab-count"><?= count($attach) ?></span>
    </button>
    <button class="cd-tab" data-t="apps" onclick="tab(this)">
        <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        Ứng tuyển <span class="cd-tab-count"><?= count($apps) ?></span>
    </button>
    <button class="cd-tab" data-t="timeline" onclick="tab(this)">
        <svg viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
        Hoạt động
    </button>
</div>

<!-- ── TAB: TỔNG QUAN ── -->
<div class="cd-pane" id="p-overview">
    <div class="cd-layout">
        <div class="cd-main">
            <!-- Thông tin cơ bản -->
            <div class="rc-card">
                <div class="cd-section-head">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Thông tin cơ bản
                </div>
                <div class="cd-info-grid">
                    <div class="cd-info-row">
                        <span class="cd-info-label">Họ tên</span>
                        <span class="cd-info-value"><strong><?= h($c['full_name']) ?></strong></span>
                    </div>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Trạng thái</span>
                        <span class="cd-info-value">
                            <span class="cd-status-badge" style="background:<?= $stColor['bg'] ?>;color:<?= $stColor['text'] ?>"><?= h($statuses[$c['status']] ?? $c['status']) ?></span>
                        </span>
                    </div>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Email</span>
                        <span class="cd-info-value">
                            <?php if ($c['email']): ?>
                                <a href="mailto:<?= h($c['email']) ?>" class="cd-link"><?= h($c['email']) ?></a>
                            <?php else: ?><span class="cd-na">—</span><?php endif; ?>
                        </span>
                    </div>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Điện thoại</span>
                        <span class="cd-info-value">
                            <?php if ($c['phone']): ?>
                                <a href="tel:<?= h($c['phone']) ?>" class="cd-link"><?= h($c['phone']) ?></a>
                            <?php else: ?><span class="cd-na">—</span><?php endif; ?>
                        </span>
                    </div>
                    <?php if ($c['current_position']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Vị trí gần nhất</span>
                        <span class="cd-info-value"><?= h($c['current_position']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['location']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Khu vực</span>
                        <span class="cd-info-value"><?= h($c['location']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Số năm kinh nghiệm</span>
                        <span class="cd-info-value">
                            <?php if ($c['years_exp']): ?>
                                <span class="cd-highlight"><?= (float)$c['years_exp'] ?> năm</span>
                            <?php else: ?><span class="cd-na">Chưa có</span><?php endif; ?>
                        </span>
                    </div>
                    <?php if ($c['expected_salary']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Lương kỳ vọng</span>
                        <span class="cd-info-value"><strong><?= h($c['expected_salary']) ?></strong></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($ratingInt): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Đánh giá</span>
                        <span class="cd-info-value">
                            <span class="cd-stars">
                                <?php for ($i=1;$i<=5;$i++): ?><span style="color:<?= $i<=$ratingInt?'#f59e0b':'#cbd5e1' ?>">★</span><?php endfor; ?>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['source_name']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Nguồn</span>
                        <span class="cd-info-value"><?= h($c['source_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['event_name']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Sự kiện</span>
                        <span class="cd-info-value"><?= h($c['event_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php $ownerDisplay = $c['owner_text'] ?: $c['owner_name']; ?>
                    <?php if ($ownerDisplay): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Người phụ trách</span>
                        <span class="cd-info-value"><?= h($ownerDisplay) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['linkedin_url']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">LinkedIn</span>
                        <span class="cd-info-value"><a href="<?= h($c['linkedin_url']) ?>" target="_blank" rel="noopener" class="cd-link cd-ext-link">
                            <svg viewBox="0 0 24 24" width="13" height="13"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                            Xem hồ sơ LinkedIn
                        </a></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['portfolio_url']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Portfolio</span>
                        <span class="cd-info-value"><a href="<?= h($c['portfolio_url']) ?>" target="_blank" rel="noopener" class="cd-link cd-ext-link">
                            <svg viewBox="0 0 24 24" width="13" height="13"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            Xem Portfolio
                        </a></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['id_card']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">CMND/CCCD</span>
                        <span class="cd-info-value"><?= h($c['id_card']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['classification']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Phân loại</span>
                        <span class="cd-info-value"><?= h($c['classification']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['campaign']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Chiến dịch</span>
                        <span class="cd-info-value"><?= h($c['campaign']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['applied_date']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Ngày ứng tuyển</span>
                        <span class="cd-info-value"><?= date('d/m/Y', strtotime($c['applied_date'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['applied_job']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Vị trí ứng tuyển (gốc)</span>
                        <span class="cd-info-value"><?= h($c['applied_job']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($c['applied_stage']): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Giai đoạn (gốc)</span>
                        <span class="cd-info-value"><?= h($c['applied_stage']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['reject_reason'])): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Lý do từ chối</span>
                        <span class="cd-info-value" style="color:#dc2626"><?= h($c['reject_reason']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['reject_note'])): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Thông tin từ chối</span>
                        <span class="cd-info-value"><?= h($c['reject_note']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['rejected_by'])): $rb = $conn->query("SELECT full_name FROM users WHERE id=".(int)$c['rejected_by'])->fetch_assoc(); ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Từ chối bởi</span>
                        <span class="cd-info-value"><?= h($rb['full_name'] ?? ('#'.$c['rejected_by'])) ?></span>
                    </div>
                    <?php elseif (!empty($c['rejected_by_text'])): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Từ chối bởi</span>
                        <span class="cd-info-value"><?= h($c['rejected_by_text']) ?> <span class="cd-na">(ngoài OS)</span></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['office_text'])): ?>
                    <div class="cd-info-row">
                        <span class="cd-info-label">Văn phòng làm việc</span>
                        <span class="cd-info-value"><?= h($c['office_text']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($c['notes']): ?>
            <!-- Ghi chú -->
            <div class="rc-card">
                <div class="cd-section-head">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Ghi chú
                </div>
                <div class="cd-notes-body"><?= nl2br(h($c['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="cd-side">
            <!-- Tags -->
            <div class="rc-card">
                <div class="cd-section-head">
                    <svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    Thẻ (Tags)
                </div>
                <div id="tagBox" style="display:flex;flex-wrap:wrap;gap:6px;min-height:28px">
                    <?php foreach ($tags as $t): ?>
                        <span class="cd-tag"><?= h($t) ?> <a href="#" onclick="delTag('<?= h(addslashes($t)) ?>');return false" class="cd-tag-del" title="Xóa">×</a></span>
                    <?php endforeach; ?>
                    <?php if (!$tags): ?><span class="cd-na" style="font-size:12px">Chưa có thẻ</span><?php endif; ?>
                </div>
                <div style="margin-top:10px;display:flex;gap:6px">
                    <input id="newTag" class="cd-in" placeholder="Thêm thẻ..." style="flex:1;min-width:0">
                    <button class="rc-btn ghost" style="padding:7px 12px;font-size:12px" onclick="addTag()">+ Thêm</button>
                </div>
            </div>

            <!-- Talent Pools -->
            <div class="rc-card">
                <div class="cd-section-head">
                    <svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg>
                    Talent Pools
                    <a href="/hrm/pools" style="margin-left:auto;font-size:11px;font-weight:500;color:#64748b">Quản lý</a>
                </div>
                <div id="poolBox" style="display:flex;flex-wrap:wrap;gap:6px;min-height:28px">
                    <?php foreach ($candPools as $p): ?>
                        <span class="cd-pool-tag" style="background:<?= h($p['color']) ?>18;color:<?= h($p['color']) ?>;border-color:<?= h($p['color']) ?>40">
                            <?= h($p['name']) ?>
                            <a href="#" style="color:inherit;opacity:.6" onclick="delPool(<?= (int)$p['id'] ?>);return false">×</a>
                        </span>
                    <?php endforeach; ?>
                    <?php if (!$candPools): ?><span class="cd-na" style="font-size:12px">Chưa thuộc pool nào</span><?php endif; ?>
                </div>
                <div style="margin-top:10px;display:flex;gap:6px">
                    <select id="poolSel" class="cd-in" style="flex:1;min-width:0">
                        <option value="">+ Chọn pool...</option>
                        <?php foreach ($allPools as $p): if (in_array((int)$p['id'], $candPoolIds, true)) continue; ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="rc-btn ghost" style="padding:7px 12px;font-size:12px" onclick="addPool()">Thêm</button>
                </div>
            </div>

            <!-- Nhắc việc -->
            <div class="rc-card">
                <div class="cd-section-head">
                    <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Nhắc việc (follow-up)
                </div>
                <div id="remList">
                    <?php if ($reminders): foreach ($reminders as $r): ?>
                        <div class="cd-reminder" id="rem<?= $r['id'] ?>">
                            <div class="cd-rem-icon">🔔</div>
                            <div class="cd-rem-body">
                                <div class="cd-rem-time"><?= date('d/m H:i', strtotime($r['due_at'])) ?></div>
                                <div class="cd-rem-note"><?= h($r['note'] ?: '(không ghi chú)') ?></div>
                                <?php if ($r['owner']): ?><div class="cd-na"><?= h($r['owner']) ?></div><?php endif; ?>
                            </div>
                            <button class="rc-btn ghost" style="padding:4px 10px;font-size:11px;flex-shrink:0" onclick="remDone(<?= $r['id'] ?>)">Xong</button>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="cd-na" style="text-align:center;padding:12px 0">Chưa có nhắc việc</div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:10px;display:grid;gap:6px">
                    <input type="datetime-local" id="remDue" class="cd-in">
                    <input id="remNote" class="cd-in" placeholder="Nội dung nhắc...">
                    <button class="rc-btn" style="width:100%;justify-content:center;gap:6px" onclick="addRem()">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Đặt nhắc
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── TAB: KỸ NĂNG & KINH NGHIỆM ── -->
<div class="cd-pane" id="p-profile" style="display:none">
    <!-- Kỹ năng -->
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Kỹ năng
        </div>
        <div id="skillBox" style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px;min-height:32px">
            <?php foreach ($skills as $s): ?>
                <span class="cd-skill-tag" id="sk<?= $s['id'] ?>">
                    <?= h($s['skill']) ?>
                    <?php if ($s['level']): ?><span class="cd-skill-level"><?= h($s['level']) ?></span><?php endif; ?>
                    <a href="#" onclick="delSkill(<?= $s['id'] ?>);return false" class="cd-tag-del">×</a>
                </span>
            <?php endforeach; ?>
            <?php if (!$skills): ?><span class="cd-na">Chưa có kỹ năng nào</span><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input id="skName" class="cd-in" placeholder="Tên kỹ năng (VD: PHP)" style="flex:1;min-width:140px">
            <input id="skLevel" class="cd-in" placeholder="Mức độ (VD: Senior)" style="width:150px">
            <button class="rc-btn ghost" onclick="addSkill()">+ Thêm kỹ năng</button>
        </div>
    </div>

    <!-- Kinh nghiệm -->
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Kinh nghiệm làm việc
        </div>
        <div id="expBox">
            <?php foreach ($exps as $e): ?>
                <div class="cd-exp-item" id="ex<?= $e['id'] ?>">
                    <div class="cd-exp-dot"></div>
                    <div class="cd-exp-body">
                        <div class="cd-exp-title"><?= h($e['title']) ?><?= $e['company'] ? ' <span class="cd-exp-co">@ '.h($e['company']).'</span>' : '' ?></div>
                        <?php if ($e['period']): ?><div class="cd-exp-period">🗓 <?= h($e['period']) ?></div><?php endif; ?>
                        <?php if ($e['summary']): ?><div class="cd-exp-summary"><?= nl2br(h($e['summary'])) ?></div><?php endif; ?>
                    </div>
                    <a href="#" onclick="delExp(<?= $e['id'] ?>);return false" class="cd-del-btn" title="Xóa">
                        <svg viewBox="0 0 24 24" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$exps): ?><div class="cd-na" style="padding:16px 0;text-align:center">Chưa có kinh nghiệm</div><?php endif; ?>
        </div>
        <div class="cd-add-form">
            <div class="rc-grid2">
                <input id="exTitle" class="cd-in" placeholder="Chức danh *">
                <input id="exCompany" class="cd-in" placeholder="Công ty">
                <input id="exPeriod" class="cd-in" placeholder="Thời gian (VD: 2020 – 2023)">
                <input id="exSummary" class="cd-in" placeholder="Mô tả ngắn">
            </div>
            <button class="rc-btn ghost" style="margin-top:8px" onclick="addExp()">+ Thêm kinh nghiệm</button>
        </div>
    </div>

    <!-- Học vấn -->
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            Học vấn
        </div>
        <div id="eduBox">
            <?php foreach ($edus as $e): ?>
                <div class="cd-exp-item" id="ed<?= $e['id'] ?>">
                    <div class="cd-exp-dot" style="background:#7c3aed"></div>
                    <div class="cd-exp-body">
                        <div class="cd-exp-title"><?= h($e['degree']) ?><?= $e['major'] ? ' · <span style="font-weight:400">'.h($e['major']).'</span>' : '' ?></div>
                        <?php if ($e['school']): ?><div class="cd-exp-period">🎓 <?= h($e['school']) ?><?= $e['grad_year'] ? ' · '.h($e['grad_year']) : '' ?></div><?php endif; ?>
                    </div>
                    <a href="#" onclick="delEdu(<?= $e['id'] ?>);return false" class="cd-del-btn" title="Xóa">
                        <svg viewBox="0 0 24 24" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </a>
                </div>
            <?php endforeach; ?>
            <?php if (!$edus): ?><div class="cd-na" style="padding:16px 0;text-align:center">Chưa có thông tin học vấn</div><?php endif; ?>
        </div>
        <div class="cd-add-form">
            <div class="rc-grid2">
                <input id="edSchool" class="cd-in" placeholder="Trường *">
                <input id="edDegree" class="cd-in" placeholder="Bằng cấp (VD: Cử nhân)">
                <input id="edMajor" class="cd-in" placeholder="Chuyên ngành">
                <input id="edYear" class="cd-in" placeholder="Năm tốt nghiệp">
            </div>
            <button class="rc-btn ghost" style="margin-top:8px" onclick="addEdu()">+ Thêm học vấn</button>
        </div>
    </div>
</div>

<!-- ── TAB: TỆP ── -->
<div class="cd-pane" id="p-files" style="display:none">
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
            Tệp đính kèm (CV, chứng chỉ, portfolio)
        </div>
        <?php if ($attach): ?>
        <div class="cd-file-list" id="fileTable">
            <?php foreach ($attach as $a): ?>
                <div class="cd-file-row" id="att<?= $a['id'] ?>">
                    <div class="cd-file-icon">
                        <?php
                        $ext = strtolower(pathinfo($a['label'] ?? '', PATHINFO_EXTENSION));
                        $fileColors = ['pdf'=>'#dc2626','doc'=>'#2563eb','docx'=>'#2563eb','xls'=>'#16a34a','xlsx'=>'#16a34a'];
                        $fc = $fileColors[$ext] ?? '#64748b';
                        ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="<?= $fc ?>" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                    </div>
                    <div class="cd-file-info">
                        <a href="<?= h($a['file_path']) ?>" target="_blank" rel="noopener" class="cd-file-name"><?= h($a['label']) ?></a>
                        <div class="cd-na"><?= strtoupper(h($a['type'])) ?> · <?= date('d/m/Y', strtotime($a['created_at'])) ?></div>
                    </div>
                    <a href="#" class="cd-del-btn" onclick="delAtt(<?= $a['id'] ?>);return false" title="Xóa">
                        <svg viewBox="0 0 24 24" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="rc-empty" style="border:none;padding:32px 0">Chưa có tệp nào được đính kèm.</div>
        <?php endif; ?>
        <div class="cd-upload-area">
            <form id="attForm" onsubmit="return false">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <input type="file" name="file" required style="font-size:13px">
                    <select name="type" class="cd-in" style="width:130px">
                        <option value="cv">CV</option>
                        <option value="cert">Chứng chỉ</option>
                        <option value="portfolio">Portfolio</option>
                        <option value="other">Khác</option>
                    </select>
                    <input name="label" class="cd-in" placeholder="Nhãn (tùy chọn)" style="flex:1;min-width:120px">
                    <button class="rc-btn" onclick="upAtt()">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                        Tải lên
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── TAB: ỨNG TUYỂN ── -->
<div class="cd-pane" id="p-apps" style="display:none">
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            Hồ sơ ứng tuyển (pipeline)
        </div>
        <?php if ($apps): ?>
        <table class="rc-table">
            <thead><tr><th>Vị trí tuyển dụng</th><th>Giai đoạn</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($apps as $a): ?>
                <tr>
                    <td><strong><?= h($a['job_title']) ?></strong></td>
                    <td><?= h($a['stage_name'] ?: '—') ?></td>
                    <td><span class="rc-badge rc-b-<?= $a['status']==='hired'?'approved':($a['status']==='rejected'?'rejected':'pending') ?>"><?= h($appStatus[$a['status']] ?? $a['status']) ?></span></td>
                    <td style="text-align:right"><a class="rc-btn ghost" style="padding:5px 14px;font-size:12px" href="/hrm/application?id=<?= $a['id'] ?>">Mở hồ sơ →</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="rc-empty" style="border:none;padding:32px 0">Chưa được đưa vào pipeline tin tuyển dụng nào.</div>
        <?php endif; ?>
        <div style="display:flex;gap:8px;align-items:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9">
            <div class="rc-field" style="margin:0;flex:1;max-width:440px">
                <label>Đưa vào tin tuyển dụng</label>
                <select id="jobSel">
                    <option value="0">— Chọn tin tuyển dụng —</option>
                    <?php foreach ($openJobs as $j): ?><option value="<?= $j['id'] ?>"><?= h($j['title']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="rc-btn" onclick="addToJob()">Đưa vào pipeline</button>
        </div>
    </div>
</div>

<!-- ── TAB: HOẠT ĐỘNG ── -->
<div class="cd-pane" id="p-timeline" style="display:none">
    <!-- Quick add -->
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Thêm ghi chú / hoạt động
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <select id="actType" class="cd-in" style="width:150px">
                <option value="note">📝 Ghi chú</option>
                <option value="call">📞 Cuộc gọi</option>
                <option value="email">✉️ Email</option>
                <option value="meeting">🤝 Gặp mặt</option>
            </select>
            <input id="actBody" class="cd-in" placeholder="Nội dung ghi chú..." style="flex:1;min-width:240px">
            <button class="rc-btn" onclick="addAct()">Lưu</button>
        </div>
    </div>
    <!-- Timeline -->
    <div class="rc-card">
        <div class="cd-section-head">
            <svg viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
            Dòng thời gian
        </div>
        <div id="actBox" class="cd-timeline">
            <?php if ($acts): foreach ($acts as $a):
                $tlIcon = ['note'=>'📝','call'=>'📞','email'=>'✉️','meeting'=>'🤝','create'=>'✨','update'=>'✏️','stage'=>'🔀'];
                $tlColor = ['note'=>'#0071e3','call'=>'#16a34a','email'=>'#7c3aed','meeting'=>'#ea580c','create'=>'#0e9f6e','update'=>'#64748b','stage'=>'#b45309'];
                $ic = $tlIcon[$a['type']] ?? '•';
                $cl = $tlColor[$a['type']] ?? '#94a3b8';
            ?>
                <div class="cd-tl">
                    <div class="cd-tl-dot" style="background:<?= $cl ?>;box-shadow:0 0 0 3px <?= $cl ?>20"><?= $ic ?></div>
                    <div style="flex:1;padding-top:2px">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
                            <span class="cd-act-badge" style="background:<?= $cl ?>15;color:<?= $cl ?>"><?= h($actLabel[$a['type']] ?? $a['type']) ?></span>
                            <span class="cd-na"><?= h($a['actor'] ?: 'Hệ thống') ?> · <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></span>
                        </div>
                        <div class="cd-tl-body"><?= nl2br(h($a['body'])) ?></div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="cd-na" style="text-align:center;padding:32px 0">Chưa có hoạt động nào</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$appIds = array_map(fn($a) => (int)$a['id'], $apps);
$offerIds = [];
if ($appIds) {
    $or = $conn->query("SELECT id FROM hrm_offers WHERE application_id IN (" . implode(',', $appIds) . ")");
    while ($or && ($x = $or->fetch_assoc())) { $offerIds[] = (int)$x['id']; }
}
hrm_history_sidebar(hrm_history_events($conn, $appIds, $id, $offerIds));
?>

<!-- ── MODAL SỬA THÔNG TIN ── -->
<div id="editModal" class="cd-modal">
    <div class="cd-modal-box">
        <div class="cd-modal-head">
            <div style="display:flex;align-items:center;gap:10px">
                <div class="cd-modal-icon">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#fff" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div>
                    <div style="font-size:15px;font-weight:700;color:#0f172a">Sửa thông tin ứng viên</div>
                    <div class="cd-na">Cập nhật thông tin hồ sơ</div>
                </div>
            </div>
            <button class="cd-modal-close" onclick="document.getElementById('editModal').style.display='none'">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="editForm" onsubmit="return false" style="overflow-y:auto;max-height:calc(90vh - 80px);padding:20px">
            <div class="rc-grid2">
                <div class="rc-field"><label>Họ tên *</label><input name="full_name" required value="<?= h($c['full_name']) ?>"></div>
                <div class="rc-field"><label>Trạng thái</label><select name="status"><?php foreach ($statuses as $k=>$lbl): ?><option value="<?= $k ?>"<?= $c['status']===$k?' selected':'' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Email</label><input name="email" type="email" value="<?= h($c['email']) ?>"></div>
                <div class="rc-field"><label>Điện thoại</label><input name="phone" value="<?= h($c['phone']) ?>"></div>
                <div class="rc-field"><label>Vị trí gần nhất</label><input name="current_position" value="<?= h($c['current_position']) ?>"></div>
                <div class="rc-field"><label>Khu vực</label><input name="location" value="<?= h($c['location']) ?>"></div>
                <div class="rc-field"><label>Số năm kinh nghiệm</label><input name="years_exp" type="number" step="0.5" min="0" value="<?= (float)$c['years_exp'] ?>"></div>
                <div class="rc-field"><label>Lương kỳ vọng</label><input name="expected_salary" value="<?= h($c['expected_salary']) ?>"></div>
                <div class="rc-field"><label>Đánh giá (0–5 ★)</label><input name="rating" type="number" min="0" max="5" value="<?= (int)$c['rating'] ?>"></div>
                <div class="rc-field"><label>Nguồn</label><select name="source_id"><option value="0">—</option><?php foreach ($sources as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$c['source_id']===(int)$s['id']?' selected':'' ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Sự kiện</label><select name="event_id"><option value="0">—</option><?php foreach ($events as $e): ?><option value="<?= $e['id'] ?>"<?= (int)$c['event_id']===(int)$e['id']?' selected':'' ?>><?= h($e['name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>Người phụ trách</label><select name="owner_id"><option value="0">—</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= (int)$c['owner_id']===(int)$u['id']?' selected':'' ?>><?= h($u['full_name']) ?></option><?php endforeach; ?></select></div>
                <div class="rc-field"><label>LinkedIn URL</label><input name="linkedin_url" type="url" placeholder="https://linkedin.com/in/..." value="<?= h($c['linkedin_url']) ?>"></div>
                <div class="rc-field"><label>Portfolio URL</label><input name="portfolio_url" type="url" placeholder="https://..." value="<?= h($c['portfolio_url']) ?>"></div>
            </div>
            <div class="rc-field"><label>Ghi chú nội bộ</label><textarea name="notes" rows="4" placeholder="Ghi chú, nhận xét về ứng viên..."><?= h($c['notes']) ?></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px">
                <button type="button" class="rc-btn ghost" onclick="document.getElementById('editModal').style.display='none'">Hủy bỏ</button>
                <button type="button" class="rc-btn" onclick="saveCand()">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* ── HERO ── */
.cd-hero{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;background:#fff;border:1px solid var(--bd);border-radius:16px;padding:24px 28px;margin-bottom:6px;box-shadow:0 2px 8px rgba(0,0,0,.06);flex-wrap:wrap;transition:.2s}
.cd-hero:hover{box-shadow:0 4px 16px rgba(0,0,0,.09)}
.cd-hero-left{display:flex;align-items:flex-start;gap:18px;min-width:0}
.cd-avatar{width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,#0c3138,#0a6b5c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;flex-shrink:0;box-shadow:0 8px 20px -6px rgba(10,37,42,.4);letter-spacing:-1px}
.cd-hero-info{min-width:0}
.cd-hero-name{font-size:22px;font-weight:700;color:#0f172a;margin:0 0 3px;line-height:1.3}
.cd-hero-pos{font-size:13.5px;color:#475569;margin-bottom:10px;font-weight:500}
.cd-hero-meta{display:flex;flex-wrap:wrap;gap:6px}
.cd-meta-pill{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#475569;background:#f8fafc;border:1px solid #e8ecf0;padding:4px 10px;border-radius:20px;transition:.15s}
.cd-meta-pill:hover{background:#f0f4f8;border-color:#d1dae3}
.cd-meta-pill svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;flex-shrink:0}
.cd-hero-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0}
.cd-status-badge{display:inline-flex;align-items:center;font-size:12px;font-weight:700;padding:5px 13px;border-radius:99px;letter-spacing:.3px}
.cd-rating{font-size:20px;letter-spacing:3px}
.cd-hero-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.cd-back-link{display:inline-flex;align-items:center;gap:5px;font-size:12px;color:#64748b;text-decoration:none;margin-bottom:16px;padding:4px 0;transition:.15s}
.cd-back-link:hover{color:#0f172a}

/* ── TABS ── */
.cd-tabs{display:flex;gap:2px;border-bottom:2px solid #e5e9ef;margin-bottom:18px;flex-wrap:wrap}
.cd-tab{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;transition:.15s;white-space:nowrap}
.cd-tab svg{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;opacity:.7}
.cd-tab:hover{color:#0f172a}
.cd-tab.on{color:#0071e3;border-bottom-color:#0071e3}
.cd-tab.on svg{opacity:1}
.cd-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;background:#f1f5f9;color:#64748b;border-radius:99px;font-size:11px;font-weight:700}
.cd-tab.on .cd-tab-count{background:#dbeafe;color:#1d4ed8}

/* ── LAYOUT ── */
.cd-layout{display:flex;gap:18px;align-items:flex-start}
.cd-main{flex:1;min-width:0}
.cd-side{width:280px;flex:0 0 280px;display:flex;flex-direction:column;gap:14px}
@media(max-width:900px){.cd-layout{flex-direction:column}.cd-side{width:100%;flex:none}}

/* ── SECTION HEAD ── */
.cd-section-head{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:#334155;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9}
.cd-section-head svg{width:15px;height:15px;fill:none;stroke:#0071e3;stroke-width:2;stroke-linecap:round;flex-shrink:0}

/* ── INFO GRID ── */
.cd-info-grid{display:flex;flex-direction:column;gap:0}
.cd-info-row{display:flex;align-items:center;gap:12px;padding:9px 4px;border-bottom:1px solid #f5f7fa;font-size:13px;border-radius:6px;transition:.1s}
.cd-info-row:hover{background:#fafbfc}
.cd-info-row:last-child{border-bottom:none}
.cd-info-label{color:#64748b;font-size:12px;font-weight:600;min-width:170px;flex-shrink:0;text-transform:uppercase;letter-spacing:.3px;font-size:11px}
.cd-info-value{color:#0f172a;flex:1;word-break:break-word;font-size:13.5px}
.cd-na{color:#94a3b8;font-size:12px}
.cd-link{color:#0071e3;text-decoration:none}
.cd-link:hover{text-decoration:underline}
.cd-ext-link{display:inline-flex;align-items:center;gap:5px}
.cd-highlight{font-weight:600;color:#0f172a}
.cd-stars{font-size:16px;letter-spacing:1px}

/* ── NOTES ── */
.cd-notes-body{font-size:13.5px;color:#334155;line-height:1.7;background:#f8fafc;border-radius:8px;padding:12px 14px;border:1px solid #f1f5f9}

/* ── TAGS ── */
.cd-in{padding:8px 11px;border:1px solid var(--bd);border-radius:8px;font-size:13px;background:#fff;font-family:inherit;transition:.15s;outline:none;color:#0f172a}
.cd-in:focus{border-color:#0071e3;box-shadow:0 0 0 3px rgba(0,113,227,.1)}
.cd-tag{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:4px 10px;border-radius:99px;background:#eef6ff;color:#0071e3;border:1px solid #bfdbfe}
.cd-tag-del{color:#94a3b8;text-decoration:none;font-weight:700;font-size:14px;line-height:1;transition:.1s}
.cd-tag-del:hover{color:#dc2626}
.cd-pool-tag{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:4px 10px;border-radius:99px;border:1px solid}

/* ── REMINDERS ── */
.cd-reminder{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;background:#fffbeb;border:1px solid #fef3c7;border-radius:10px;margin-bottom:8px}
.cd-rem-icon{font-size:16px;flex-shrink:0;margin-top:2px}
.cd-rem-body{flex:1;min-width:0}
.cd-rem-time{font-size:12px;font-weight:700;color:#92400e}
.cd-rem-note{font-size:13px;color:#78350f;margin-top:2px}

/* ── SKILLS ── */
.cd-skill-tag{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:5px 12px;border-radius:8px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0}
.cd-skill-level{font-size:11px;font-weight:500;color:#15803d;opacity:.8;border-left:1px solid #86efac;padding-left:6px}

/* ── EXPERIENCE ── */
.cd-add-form{margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9}
.cd-exp-item{display:flex;gap:14px;padding:12px 0;border-bottom:1px solid #f8fafc;align-items:flex-start}
.cd-exp-item:last-child{border-bottom:none}
.cd-exp-dot{width:10px;height:10px;border-radius:50%;background:#0071e3;flex-shrink:0;margin-top:6px;box-shadow:0 0 0 3px #dbeafe}
.cd-exp-body{flex:1;min-width:0}
.cd-exp-title{font-size:14px;font-weight:600;color:#0f172a}
.cd-exp-co{font-weight:400;color:#64748b}
.cd-exp-period{font-size:12px;color:#64748b;margin-top:3px}
.cd-exp-summary{font-size:13px;color:#475569;margin-top:6px;line-height:1.6}
.cd-del-btn{color:#94a3b8;text-decoration:none;flex-shrink:0;padding:4px;border-radius:6px;transition:.15s;display:flex;align-items:center}
.cd-del-btn:hover{color:#dc2626;background:#fef2f2}
.cd-del-btn svg{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}

/* ── FILES ── */
.cd-file-list{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
.cd-file-row{display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f8fafc;border:1px solid #f1f5f9;border-radius:10px;transition:.15s}
.cd-file-row:hover{background:#f0f4ff;border-color:#c7d7ff}
.cd-file-icon{flex-shrink:0}
.cd-file-info{flex:1;min-width:0}
.cd-file-name{font-size:13px;font-weight:600;color:#0071e3;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cd-file-name:hover{text-decoration:underline}
.cd-upload-area{margin-top:16px;padding:14px;border:2px dashed #e2e8f0;border-radius:10px;background:#f8fafc}

/* ── TIMELINE ── */
.cd-timeline{display:flex;flex-direction:column;gap:0}
.cd-tl{display:flex;gap:14px;padding:12px 0;border-bottom:1px solid #f8fafc;position:relative}
.cd-tl:last-child{border-bottom:none}
.cd-tl-dot{width:32px;height:32px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:15px}
.cd-tl-body{font-size:13px;color:#334155;line-height:1.6}
.cd-act-badge{display:inline-flex;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;letter-spacing:.2px}

/* ── MODAL ── */
.cd-modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:999;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.cd-modal-box{background:#fff;border-radius:16px;width:660px;max-width:96vw;max-height:94vh;box-shadow:0 25px 60px -12px rgba(0,0,0,.4);display:flex;flex-direction:column;overflow:hidden}
.cd-modal-head{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #f1f5f9;flex-shrink:0}
.cd-modal-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#0c3138,#0a6b5c);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cd-modal-close{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:8px;border:none;background:none;color:#64748b;cursor:pointer;transition:.15s}
.cd-modal-close:hover{background:#f1f5f9;color:#0f172a}
</style>

<script>
const CID = <?= $id ?>;
function tab(b){document.querySelectorAll('.cd-tab').forEach(x=>x.classList.remove('on'));b.classList.add('on');
    document.querySelectorAll('.cd-pane').forEach(p=>p.style.display='none');document.getElementById('p-'+b.dataset.t).style.display='block';}
function show(w){if(w==='edit')document.getElementById('editModal').style.display='flex';}
function post(data,cb){const fd=new FormData();for(const k in data)fd.append(k,data[k]);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?(cb?cb(j):location.reload()):alert(j.error||'Lỗi');});}
function saveCand(){const f=document.getElementById('editForm');if(!f.full_name.value.trim()){alert('Nhập họ tên');return;}
    const fd=new FormData(f);fd.append('action','update_candidate');fd.append('candidate_id',CID);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function addTag(){const v=document.getElementById('newTag').value.trim();if(!v)return;post({action:'cand_tag_add',candidate_id:CID,tag:v});}
function addPool(){const v=document.getElementById('poolSel').value;if(!v){alert('Chọn pool');return;}post({action:'cand_pool_add',candidate_id:CID,pool_id:v});}
function delPool(pid){post({action:'cand_pool_del',candidate_id:CID,pool_id:pid});}
function delTag(t){post({action:'cand_tag_del',candidate_id:CID,tag:t});}
function addSkill(){const n=document.getElementById('skName').value.trim();if(!n)return;post({action:'cand_skill_add',candidate_id:CID,skill:n,level:document.getElementById('skLevel').value.trim()});}
function delSkill(id){post({action:'cand_skill_del',id:id});}
function addExp(){const t=document.getElementById('exTitle').value.trim();if(!t){alert('Nhập chức danh');return;}
    post({action:'cand_exp_add',candidate_id:CID,title:t,company:document.getElementById('exCompany').value.trim(),period:document.getElementById('exPeriod').value.trim(),summary:document.getElementById('exSummary').value.trim()});}
function delExp(id){post({action:'cand_exp_del',id:id});}
function addEdu(){const s=document.getElementById('edSchool').value.trim();if(!s){alert('Nhập trường');return;}
    post({action:'cand_edu_add',candidate_id:CID,school:s,degree:document.getElementById('edDegree').value.trim(),major:document.getElementById('edMajor').value.trim(),grad_year:document.getElementById('edYear').value.trim()});}
function delEdu(id){post({action:'cand_edu_del',id:id});}
function delAtt(id){if(confirm('Xóa tệp này?'))post({action:'cand_attach_del',id:id});}
function upAtt(){const f=document.getElementById('attForm');if(!f.file.files.length){alert('Chọn file');return;}
    const fd=new FormData(f);fd.append('action','cand_attach_add');fd.append('candidate_id',CID);
    fetch('/hrm/api',{method:'POST',body:fd}).then(r=>r.json()).then(j=>{j.ok?location.reload():alert(j.error||'Lỗi');});}
function addAct(){const b=document.getElementById('actBody').value.trim();if(!b)return;post({action:'cand_activity_add',candidate_id:CID,type:document.getElementById('actType').value,body:b});}
function addRem(){const d=document.getElementById('remDue').value;if(!d){alert('Chọn thời gian');return;}
    post({action:'cand_reminder_add',candidate_id:CID,due_at:d.replace('T',' ')+':00',note:document.getElementById('remNote').value.trim()});}
function remDone(id){post({action:'cand_reminder_done',reminder_id:id});}
function addToJob(){const jid=document.getElementById('jobSel').value;if(jid=='0'){alert('Chọn tin tuyển dụng');return;}
    post({action:'add_candidate_to_job',candidate_id:CID,job_id:jid},j=>location.href='/hrm/application?id='+j.id);}
// Close modal on backdrop click
document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
</script>
<?php
hrm_footer();
