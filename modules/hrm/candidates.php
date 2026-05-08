<?php
require_once __DIR__ . '/../../config/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: /login"); exit(); }
$full_name = $_SESSION['full_name'];
$avatar = $_SESSION['avatar'] ?? null;
$role = $_SESSION['role'] ?? 'staff';
?>
<script>const USER_ROLE = '<?php echo $role; ?>';</script>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách ứng viên | Hệ thống quản trị tuyển dụng E-Hiring</title>
    <meta name="description" content="Quản lý danh sách ứng viên, theo dõi tiến độ tuyển dụng và thực hiện các thao tác hàng loạt một cách chuyên nghiệp.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/modules/hrm/sidebar.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#f0f2f5;color:#1a1a2e;height:100vh;overflow:hidden}
        .eh-wrapper{display:flex;height:100vh;overflow:hidden}
        .eh-content-col{flex:1;display:flex;flex-direction:column;overflow:hidden}
        
        /* TOPBAR */
        .eh-top{height:48px;background:#003459;display:flex;align-items:center;padding:0 16px;gap:12px;flex-shrink:0;border-bottom:1px solid #123a41}
        .eh-search-top{flex:1;max-width:320px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.15);border-radius:6px;padding:6px 12px 6px 32px;color:#fff;font-size:13px;outline:none;position:relative}
        .eh-search-top::placeholder{color:rgba(255,255,255,0.4)}
        .top-actions{margin-left:auto;display:flex;align-items:center;gap:8px}
        .top-btn{background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);color:#fff;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px}
        .top-btn:hover{background:rgba(255,255,255,0.2)}
        .top-btn.primary{background:#2563eb;border-color:#2563eb}
        .top-btn.outline{background:#fff;color:#4b5563;border:1px solid #d1d5db}
        .top-btn.outline:hover{background:#f9fafb}
        .top-avatar{width:32px;height:32px;border-radius:50%;background:#0ea5e9;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff;overflow:hidden}
        
        /* MAIN AREA */
        .eh-main{flex:1;overflow:hidden;display:flex;background:#fff}
        
        /* SIDEBAR FILTERS (SIDE-OVER VERSION) */
        .filter-sidebar{position:absolute;top:0;right:0;bottom:0;width:320px;border-left:1px solid #e5e7eb;background:#fff;display:none;flex-direction:column;flex-shrink:0;overflow-y:auto;z-index:600;box-shadow:-5px 0 20px rgba(0,0,0,0.1)}
        .filter-sidebar.active{display:flex}
        .filter-sidebar-header{padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;background:#f8fafc}
        .filter-sidebar-title{font-size:14px;font-weight:700;color:#1e293b}
        .filter-section{padding:16px;border-bottom:1px solid #f1f5f9}
        .filter-label{font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:12px;display:block}
        .filter-select-v2{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;outline:none}

        /* CONTENT COL */
        .list-col{flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative}
        .page-header{padding:8px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e5e7eb;flex-shrink:0;background:#fff}
        .header-tabs{display:flex;gap:24px}
        .header-tab{font-size:13px;font-weight:600;color:#6b7280;text-decoration:none;padding:12px 0;border-bottom:2px solid transparent;cursor:pointer;transition:all 0.2s}
        .header-tab.active{color:#2563eb;border-bottom-color:#2563eb}
        
        /* SEARCH BAR */
        .search-bar{padding:10px 20px;display:flex;gap:12px;border-bottom:1px solid #e5e7eb;align-items:center;flex-shrink:0;background:#fff}
        .search-input-v2{flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;outline:none;background:#f9fafb}
        
        /* TABLE */
        .table-container{flex:1;overflow:auto;background:#fff}
        table{width:100%;min-width:1200px;border-collapse:separate;border-spacing:0;table-layout:fixed}
        th{position:sticky;top:0;background:#f8fafc;padding:10px 16px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;z-index:10}
        td{padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle;color:#334155}
        tr:nth-child(even) td { background: #fcfdfe; }
        tr:hover td{background:#f1f5f9 !important}
        tr.active-row td{background:#eff6ff !important}

        /* CANDIDATE CELL */
        .candidate-cell{display:flex;align-items:center;gap:12px}
        .cand-avatar{width:36px;height:36px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:600;color:#64748b;flex-shrink:0}
        .cand-info{display:flex;flex-direction:column}
        .cand-name{font-weight:600;color:#1e293b;font-size:13.5px}
        .cand-email{font-size:11px;color:#64748b;margin-top:1px}
        
        /* PROGRESS DOTS */
        .stage-container{display:flex;flex-direction:column;gap:6px}
        .stage-name{font-size:12px;font-weight:500;color:#1e293b}
        .stage-progress{display:flex;gap:3px;align-items:center}
        .dot{width:6px;height:6px;border-radius:50%;background:#e2e8f0}
        .dot.active{background:#2563eb}
        .dot.hired{background:#10b981}
        .dot.rejected{background:#ef4444}
        .stage-count{font-size:10px;color:#94a3b8;margin-left:4px}

        /* CONTACT ICONS */
        .contact-icons{display:flex;gap:10px;margin-top:4px}
        .contact-icon{width:14px;height:14px;opacity:0.4;cursor:pointer;transition:opacity 0.2s}
        .contact-icon:hover{opacity:0.8}

        /* SIDE-OVER */
        .side-over{position:absolute;top:0;right:0;bottom:0;width:950px;background:#fff;box-shadow:-10px 0 30px rgba(0,0,0,0.1);z-index:500;display:none;flex-direction:column;border-left:1px solid #e5e7eb;transition:transform 0.3s ease-out}
        .side-over.active{display:flex}
        
        .so-header{padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;flex-direction:column;gap:12px;background:#fff}
        .so-top-row{display:flex;justify-content:space-between;align-items:center}
        .so-title-area{display:flex;gap:16px;align-items:center}
        .so-avatar{width:56px;height:56px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#64748b;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,0.05)}
        .so-name{font-size:18px;font-weight:700;color:#0f172a}
        .so-job{font-size:13px;color:#64748b;margin-top:2px}
        .so-rating{margin-top:6px;color:#fbbf24;font-size:16px;cursor:pointer}
        
        .so-actions{display:flex;gap:8px;margin-top:8px}
        .so-tabs{display:flex;gap:32px;padding:0 24px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
        .so-tab{padding:14px 0;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap}
        .so-tab.active{color:#2563eb;border-bottom-color:#2563eb}
        
        .so-main-container{flex:1;display:flex;overflow:hidden}
        .so-body{flex:1;overflow-y:auto;padding:24px;background:#fff}
        .so-cv-preview{width:450px;border-left:1px solid #e5e7eb;background:#525659;display:flex;flex-direction:column}
        .cv-header{padding:10px 16px;background:#323639;color:#fff;font-size:12px;display:flex;justify-content:space-between;align-items:center}
        
        /* INFO CARDS */
        .info-section{margin-bottom:24px}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .info-item{display:flex;flex-direction:column;gap:4px}
        .info-label{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase}
        .info-value{font-size:13.5px;color:#334155}
        
        /* TIMELINE V2 */
        .timeline-v2{display:flex;flex-direction:column;gap:0;position:relative;padding-left:16px}
        .timeline-v2::before{content:'';position:absolute;left:0;top:8px;bottom:0;width:2px;background:#f1f5f9}
        .timeline-item-v2{position:relative;padding-bottom:24px}
        .timeline-dot-v2{position:absolute;left:-21px;top:4px;width:12px;height:12px;border-radius:50%;background:#fff;border:2px solid #cbd5e1;z-index:2}
        .timeline-item-v2.active .timeline-dot-v2{border-color:#2563eb;background:#2563eb}
        .timeline-content-v2{background:#f8fafc;padding:12px 16px;border-radius:8px;border:1px solid #f1f5f9}
        .timeline-meta-v2{display:flex;justify-content:space-between;margin-bottom:6px;font-size:11px}
        .timeline-user-v2{font-weight:700;color:#475569}
        .timeline-time-v2{color:#94a3b8}
        .timeline-text-v2{font-size:13px;color:#334155;line-height:1.5}
        
        /* NOTE INPUT */
        .note-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:24px}
        .note-area{width:100%;border:none;background:transparent;outline:none;font-size:13px;resize:none;min-height:60px}

        /* BULK ACTIONS BAR */
        .bulk-actions-bar{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(100px);background:#003459;color:#fff;padding:12px 24px;border-radius:12px;display:none;align-items:center;gap:20px;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:1000;transition:transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)}
        .bulk-actions-bar.active{display:flex;transform:translateX(-50%) translateY(0)}
        .bulk-count{font-weight:700;font-size:14px;border-right:1px solid rgba(255,255,255,0.2);padding-right:20px}
        .bulk-btn{background:transparent;border:none;color:#fff;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:6px;transition:background 0.2s}
        .bulk-btn:hover{background:rgba(255,255,255,0.1)}
        
        /* MODALS V2 */
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);display:none;align-items:center;justify-content:center;z-index:2000}
        .modal-content{background:#fff;width:400px;border-radius:12px;box-shadow:0 20px 50px rgba(0,0,0,0.2);overflow:hidden}
        .modal-header{padding:16px 20px;font-weight:700;font-size:16px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
        .modal-body{padding:20px}
        .modal-footer{padding:12px 20px;background:#f8fafc;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:10px}

        /* STATUSES */
        .status-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
        .status-active{background:#eff6ff;color:#1d4ed8}
        .status-hired{background:#dcfce7;color:#15803d}
        .status-rejected{background:#fee2e2;color:#b91c1c}

        /* MODALS */
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal-content{background:#fff;border-radius:12px;width:400px;overflow:hidden;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1)}
        .modal-header{padding:16px 20px;border-bottom:1px solid #e5e7eb;font-weight:700}
        .modal-body{padding:20px}
        .modal-footer{padding:16px 20px;background:#f9fafb;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:12px}

        .bulk-actions-bar{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 24px;border-radius:40px;display:none;align-items:center;gap:16px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.3);z-index:100}
        
        .eval-table{width:100%; border-collapse:collapse; margin-top:16px}
        .eval-table th{text-align:left; padding:12px 16px; background:#f9fafb; font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:0.05em}
        .eval-table td{padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:13px}
        .eval-group{background:#f8fafc; font-weight:600; font-size:12px; color:#1e293b}
        .score-badge{display:inline-block; padding:2px 8px; border-radius:4px; font-size:12px; font-weight:600}
        .score-badge.expected{background:#f1f5f9; color:#475569}
        .score-badge.actual{background:#dcfce7; color:#166534}

        .eh-stats-row{display:flex; gap:16px; padding:0 24px 24px 24px}
        .eh-stat-card{flex:1; background:#fff; padding:16px; border-radius:12px; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,0.05)}
        .eh-stat-label{font-size:12px; color:#64748b; margin-bottom:4px; font-weight:500}
        .eh-stat-value{font-size:24px; font-weight:700; color:#1e293b}
        .truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* PAGINATION */
        .bottom-bar { padding: 12px 24px; border-top: 1px solid #e5e7eb; background: #fff; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .pagination { display: flex; align-items: center; gap: 12px; }
        .page-btn { padding: 6px 12px; border: 1px solid #d1d5db; background: #fff; border-radius: 6px; font-size: 13px; font-weight: 500; color: #374151; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .page-btn:hover:not(:disabled) { background: #f9fafb; border-color: #9ca3af; }
        .page-btn:disabled { opacity: 0.5; cursor: not-allowed; background: #f3f4f6; }
        #pageNumber { font-size: 13px; font-weight: 600; color: #111827; min-width: 60px; text-align: center; }
    </style>
</head>
<body>
<div class="eh-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="eh-content-col">
        <div class="eh-top">
            <div style="position:relative;flex:1;max-width:320px">
                <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);opacity:0.4" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input class="eh-search-top" placeholder="Tìm ứng viên..." id="topSearch">
            </div>
            <div class="top-actions">
                <button class="top-btn primary" onclick="seedData()">⚡ Seed dữ liệu</button>
                <div class="top-avatar"><?=strtoupper(substr($full_name,0,1))?></div>
            </div>
        </div>

        <main class="eh-main">
            <div class="list-col">
                <aside class="filter-sidebar" id="filterSidebar">
                    <div class="filter-sidebar-header">
                        <span class="filter-sidebar-title">Bộ lọc nâng cao</span>
                        <button onclick="toggleFilterSidebar()" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:16px">✕</button>
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Trạng thái hồ sơ</span>
                        <select class="filter-select-v2" id="statusFilter">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="active">Đang xử lý</option>
                            <option value="hired">Đã tuyển dụng</option>
                            <option value="rejected">Đã từ chối</option>
                        </select>
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Tin tuyển dụng</span>
                        <select class="filter-select-v2" id="jobFilter"><option value="0">Tất cả tin tuyển dụng</option></select>
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Nguồn ứng tuyển</span>
                        <select class="filter-select-v2" id="sourceFilter"><option value="0">Tất cả nguồn</option></select>
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Người phụ trách</span>
                        <select class="filter-select-v2" id="ownerFilter"><option value="0">Tất cả người phụ trách</option></select>
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Nhãn (Tags)</span>
                        <select class="filter-select-v2" id="tagFilter"><option value="">Tất cả nhãn</option></select>
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Từ ngày</span>
                        <input type="date" class="filter-select-v2" id="dateFrom">
                    </div>
                    <div class="filter-section">
                        <span class="filter-label">Đến ngày</span>
                        <input type="date" class="filter-select-v2" id="dateTo">
                    </div>
                    <div style="padding:16px; margin-top:auto">
                        <button class="top-btn outline" style="width:100%; justify-content:center" onclick="resetFilters()">Đặt lại bộ lọc</button>
                    </div>
                </aside>
                <div class="page-header">
                    <div class="header-tabs">
                        <div class="header-tab active">TẤT CẢ ỨNG VIÊN</div>
                        <div class="header-tab">CHIA SẺ VỚI TÔI</div>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button class="top-btn outline" onclick="toggleFilterSidebar()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                            Bộ lọc
                        </button>
                        <?php if($role === 'admin'): ?>
                        <button class="top-btn" style="background:#111827; color:#fff; border:none" onclick="seedData()">⚡ Seed dữ liệu</button>
                        <?php endif; ?>
                        <button class="top-btn outline" onclick="importExcel()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Import Excel</button>
                        <?php if($role === 'admin'): ?>
                        <button class="top-btn outline" onclick="exportExcel()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Xuất Excel</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="eh-stats-row">
                    <div class="eh-stat-card">
                        <div class="eh-stat-label">Tổng ứng viên</div>
                        <div class="eh-stat-value" id="statTotal">0</div>
                    </div>
                    <div class="eh-stat-card">
                        <div class="eh-stat-label">Đang xử lý</div>
                        <div class="eh-stat-value" id="statActive" style="color:#2563eb">0</div>
                    </div>
                    <div class="eh-stat-card">
                        <div class="eh-stat-label">Đã tuyển</div>
                        <div class="eh-stat-value" id="statHired" style="color:#10b981">0</div>
                    </div>
                    <div class="eh-stat-card">
                        <div class="eh-stat-label">Đã từ chối</div>
                        <div class="eh-stat-value" id="statRejected" style="color:#ef4444">0</div>
                    </div>
                </div>
                <div class="search-bar">
                    <div style="position:relative; flex:1">
                        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);opacity:0.3" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" class="search-input-v2" style="padding-left:32px" placeholder="Tìm theo tên, email hoặc số điện thoại..." id="searchInput">
                    </div>
                    <div style="display:flex;gap:8px; align-items:center">
                        <select class="filter-select-v2" id="sortFilter" style="width:160px; margin:0">
                            <option value="newest">Mới nhất</option>
                            <option value="oldest">Cũ nhất</option>
                            <option value="rating">Đánh giá cao nhất</option>
                        </select>
                        <button class="top-btn outline" id="refreshBtn" style="padding: 8px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center"><input type="checkbox" id="selectAll"></th>
                                <th style="width:280px">Ứng viên / Liên hệ</th>
                                <th style="width:120px">Phân loại</th>
                                <th style="width:220px">Tin tuyển dụng</th>
                                <th style="width:200px">Giai đoạn hiện tại</th>
                                <th style="width:180px">Lý do từ chối</th>
                                <th style="width:120px">Nguồn</th>
                                <th style="width:150px">Chiến dịch</th>
                                <th style="width:120px">Medium</th>
                                <th style="width:180px">Văn phòng</th>
                                <th style="width:180px">Thẻ</th>
                                <th style="width:120px">Đánh giá</th>
                                <th style="width:180px">Người phụ trách</th>
                                <th style="width:150px">Ngày ứng tuyển</th>
                                <th style="width:150px">Ngày phỏng vấn</th>
                                <th style="width:200px">Người tham chiếu</th>
                                <th style="width:150px">Theo dõi mail</th>
                                <th style="width:150px">Mail cuối cùng</th>
                                <th style="width:180px">Cập nhật lần cuối</th>
                            </tr>
                        </thead>
                        <tbody id="candidatesBody"></tbody>
                    </table>
                </div>

                <!-- SIDE-OVER DETAIL -->
                <div class="side-over" id="sideOver">
                    <div class="so-header">
                        <div class="so-top-row">
                            <div class="so-title-area">
                                <div class="so-avatar" id="soAvatar">?</div>
                                <div>
                                    <h2 class="so-name" id="soName">Loading...</h2>
                                    <div class="so-job" id="soJob">Job title</div>
                                    <div class="so-rating" id="soRating" onclick="handleRatingClick(event)">☆☆☆☆☆</div>
                                </div>
                            </div>
                            <button class="top-btn outline" onclick="closeSideOver()" style="color:#64748b; border:none; background:transparent">✕</button>
                        </div>
                        <div class="so-actions">
                            <button class="top-btn primary" onclick="openMoveModal(currentDetail.data.application_id, currentDetail.data.job_id)">Chuyển bước</button>
                            <button class="top-btn outline" style="color:#ef4444" onclick="openRejectModal(currentDetail.data.application_id)">Loại ứng viên</button>
                            <button class="top-btn outline">Gửi Email</button>
                            <button class="top-btn outline" style="margin-left:auto"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg></button>
                        </div>
                    </div>
                    <div class="so-tabs">
                        <div class="so-tab active" data-tab="info">THÔNG TIN CHUNG</div>
                        <div class="so-tab" data-tab="activities">HOẠT ĐỘNG & GHI CHÚ</div>
                        <div class="so-tab" data-tab="eval">ĐÁNH GIÁ & KẾT QUẢ</div>
                        <div class="so-tab" data-tab="attachments">FILE ĐÍNH KÈM</div>
                    </div>
                    <div class="so-main-container">
                        <div class="so-body" id="soBody"></div>
                        <div class="so-cv-preview" id="soCvPreview">
                            <div class="cv-header">
                                <span>Xem trước hồ sơ</span>
                                <a href="#" target="_blank" id="cvOpenNew" style="color:#fff; text-decoration:none">Mở rộng ↗</a>
                            </div>
                            <div style="flex:1; position:relative" id="cvIframeContainer">
                                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#94a3b8; font-size:12px" id="cvEmptyState">Không có file CV để hiển thị</div>
                                <iframe id="cvIframe" style="width:100%; height:100%; border:none; display:none"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bottom-bar">
                    <div id="paginationInfo" style="font-size:13px; color:#6b7280; font-weight:500"></div>
                    <div class="pagination">
                        <button class="page-btn" id="prevPage">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            Trước
                        </button>
                        <span id="pageNumber">1</span>
                        <button class="page-btn" id="nextPage">
                            Sau
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- BULK BAR -->
<div class="bulk-actions-bar" id="bulkBar">
    <div class="bulk-count"><span id="selectedCount">0</span> đã chọn</div>
    <button class="bulk-btn" onclick="openBulkMove()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        Chuyển bước
    </button>
    <button class="bulk-btn" onclick="openBulkReject()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        Loại ứng viên
    </button>
    <button class="bulk-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Gửi Email
    </button>
    <button class="bulk-btn" onclick="deselectAll()" style="margin-left:auto; opacity:0.6">Bỏ chọn</button>
</div>

<!-- MODAL: MOVE -->
<div class="modal-overlay" id="moveModal">
    <div class="modal-content">
        <div class="modal-header">Chuyển bước tuyển dụng</div>
        <div class="modal-body">
            <div style="margin-bottom:16px">
                <label class="filter-label">Chuyển sang bước</label>
                <select class="filter-select-v2" id="stepSelect"></select>
            </div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px">
                <input type="checkbox" id="sendEmailMove" checked>
                <label for="sendEmailMove" style="font-size:13px; font-weight:500; cursor:pointer">Gửi email thông báo cho ứng viên</label>
            </div>
            <div id="emailMoveArea">
                <label class="filter-label">Chọn mẫu email</label>
                <select class="filter-select-v2" id="emailTemplateMove"></select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="top-btn" onclick="closeModals()">Hủy</button>
            <button class="top-btn primary" id="confirmMoveBtn">Chuyển ngay</button>
        </div>
    </div>
</div>

<!-- MODAL: REJECT -->
<div class="modal-overlay" id="rejectModal"><div class="modal-content"><div class="modal-header">Từ chối hồ sơ</div><div class="modal-body"><textarea class="filter-select-v2" id="rejectReason" rows="3" placeholder="Lý do từ chối..."></textarea></div><div class="modal-footer"><button class="top-btn" onclick="closeModals()">Hủy</button><button class="top-btn" style="background:#ef4444; color:#fff" id="confirmRejectBtn">Xác nhận từ chối</button></div></div></div>

<!-- MODAL: PROGRESS -->
<div class="modal-overlay" id="progressModal" style="z-index:3000">
    <div class="modal-content" style="width:450px; padding:24px; text-align:center">
        <h3 style="font-size:16px; margin-bottom:20px; color:#1e293b">Đang import ứng viên...</h3>
        <div style="width:100%; height:12px; background:#f1f5f9; border-radius:10px; overflow:hidden; margin-bottom:12px">
            <div id="importProgressBar" style="width:0%; height:100%; background:#2563eb; transition:width 0.3s"></div>
        </div>
        <div id="importStatusText" style="font-size:13px; color:#64748b">Đang chuẩn bị...</div>
    </div>
</div>

<input type="file" id="importFileInput" style="display:none" accept=".xlsx" onchange="processImportFile(this)">

<script>
let filters = { search: '', job_id: 0, status: 'all', source_id: 0, owner_id: 0, tag: '', date_from: '', date_to: '', sort: 'newest', page: 1, limit: 20 };
let currentDetail = null;

async function loadInitialData() {
    // Load Jobs
    const jobs = await fetch('/hrm/ajax-handler?action=get_jobs&tabStatus=active').then(r => r.json());
    jobs.data.forEach(j => document.getElementById('jobFilter').add(new Option(j.title, j.id)));
    
    // Load Sources
    const sources = await fetch('/hrm/ajax-handler?action=get_candidate_sources').then(r => r.json());
    sources.forEach(s => document.getElementById('sourceFilter').add(new Option(s.name, s.id)));
    
    // Load Owners
    const users = await fetch('/hrm/ajax-handler?action=get_users').then(r => r.json());
    users.forEach(u => document.getElementById('ownerFilter').add(new Option(u.full_name, u.id)));

    // Load Tags
    const tags = await fetch('/hrm/ajax-handler?action=get_all_tags').then(r => r.json());
    tags.forEach(t => document.getElementById('tagFilter').add(new Option(t, t)));

    fetchCandidates();
}

async function importExcel() {
    document.getElementById('importFileInput').click();
}

async function processImportFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    
    if (!confirm(`Bắt đầu import ứng viên từ file "${file.name}"?`)) {
        input.value = '';
        return;
    }

    const progressModal = document.getElementById('progressModal');
    const progressBar = document.getElementById('importProgressBar');
    const statusText = document.getElementById('importStatusText');
    
    progressModal.style.display = 'flex';
    progressBar.style.width = '0%';
    statusText.innerText = 'Đang tải file lên server...';

    try {
        // 1. Upload File
        const formData = new FormData();
        formData.append('file', file);
        const upRes = await fetch('/hrm/ajax-handler?action=upload_import_file', {
            method: 'POST',
            body: formData
        }).then(r => r.json());
        
        if (!upRes.success) throw new Error(upRes.message);
        const tempFile = upRes.file_path;

        // 2. Get Total
        statusText.innerText = 'Đang phân tích file...';
        const stats = await fetch(`/hrm/ajax-handler?action=get_import_total&file=${tempFile}`).then(r => r.json());
        if (!stats.success) throw new Error(stats.message);
        
        const total = stats.total;
        const limit = 20;
        let processed = 0;

        // 3. Process Batches
        while (processed < total) {
            const res = await fetch(`/hrm/ajax-handler?action=import_candidates&file=${tempFile}&start=${processed}&limit=${limit}`).then(r => r.json());
            if (!res.success) throw new Error(res.message);
            
            processed += limit;
            const percent = Math.min(100, Math.round((processed / total) * 100));
            progressBar.style.width = percent + '%';
            statusText.innerText = `Đang xử lý: ${Math.min(processed, total)} / ${total} ứng viên...`;
        }

        alert(`Đã import thành công ${total} ứng viên!`);
        fetchCandidates();
    } catch (e) {
        alert('Lỗi: ' + e.message);
    } finally {
        progressModal.style.display = 'none';
        input.value = '';
    }
}

async function fetchCandidates() {
    const query = new URLSearchParams(filters).toString();
    const res = await fetch(`/hrm/ajax-handler?action=get_candidates&${query}`).then(r => r.json());
    if (res.success) renderCandidates(res.data, res.total);
    
    // Fetch Stats
    const stats = await fetch(`/hrm/ajax-handler?action=get_candidate_stats&job_id=${filters.job_id}`).then(r => r.json());
    document.getElementById('statTotal').innerText = stats.total;
    document.getElementById('statActive').innerText = stats.active;
    document.getElementById('statHired').innerText = stats.hired;
    document.getElementById('statRejected').innerText = stats.rejected;

    if(document.getElementById('pageNumber')) document.getElementById('pageNumber').innerText = filters.page;
    if(document.getElementById('paginationInfo')) document.getElementById('paginationInfo').innerText = `Hiển thị ${res.data.length} / ${res.total} ứng viên`;

    document.getElementById('prevPage').disabled = filters.page <= 1;
    document.getElementById('nextPage').disabled = (filters.page * filters.limit) >= res.total;
}

function renderCandidates(list, total) {
    const tbody = document.getElementById('candidatesBody');
    tbody.innerHTML = list.map(c => {
        const total = c.total_steps || 1;
        const current = c.progress_index || 0;
        let dots = '';
        for(let i=1; i<=total; i++) {
            let cls = i <= current ? 'active' : '';
            if (c.status === 'hired') cls = 'hired';
            if (c.status === 'rejected' && i === current) cls = 'rejected';
            dots += `<div class="dot ${cls}"></div>`;
        }

        const tags = (c.tags_str || '').split(',').filter(t=>t).map(t => `<span class="tag-v2" style="background:#f1f5f9; color:#475569; padding:2px 6px; border-radius:4px; font-size:10px; margin-right:4px">${t}</span>`).join('');

        return `
        <tr onclick="openCandidate(${c.application_id})" id="row-${c.application_id}">
            <td onclick="event.stopPropagation()" style="text-align:center"><input type="checkbox" class="cand-check" value="${c.application_id}" data-job="${c.job_id}"></td>
            <td>
                <div class="candidate-cell">
                    <div class="cand-avatar">${c.avatar ? `<img src="${c.avatar}" style="width:100%;height:100%;border-radius:50%">` : c.full_name[0]}</div>
                    <div class="cand-info">
                        <span class="cand-name">${c.full_name}</span>
                        <div class="contact-icons">
                            <svg class="contact-icon" title="${c.email}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            ${c.phone ? `<svg class="contact-icon" title="${c.phone}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>` : ''}
                        </div>
                    </div>
                </div>
            </td>
            <td style="color:#64748b">${c.candidate_type || '---'}</td>
            <td style="font-size:12px; color:#64748b"><div class="truncate" style="max-width:220px" title="${c.job_title || ''}">${c.job_title}</div></td>
            <td>
                <div class="stage-container">
                    <div class="stage-name">${c.step_name || 'Hồ sơ mới'}</div>
                    <div class="stage-progress">
                        ${dots}
                        <span class="stage-count">${current}/${total}</span>
                    </div>
                </div>
            </td>
            <td style="color:#ef4444; font-size:12px">${c.rejection_note || '---'}</td>
            <td style="color:#64748b">${c.source_name || '---'}</td>
            <td style="color:#64748b">${c.campaign || '---'}</td>
            <td style="color:#64748b">${c.medium || '---'}</td>
            <td style="color:#64748b"><div class="truncate" style="max-width:180px" title="${c.job_office || ''}">${c.job_office || '---'}</div></td>
            <td>${tags || '---'}</td>
            <td style="color:#f59e0b; letter-spacing:1px">${'★'.repeat(c.rating)}${'☆'.repeat(5-c.rating)}</td>
            <td>
                <div style="display:flex; align-items:center; gap:8px">
                    <div style="width:20px; height:20px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:9px; color:#94a3b8">${c.owner_name ? c.owner_name[0] : '?'}</div>
                    <span style="font-size:12px">${c.owner_name || '---'}</span>
                </div>
            </td>
            <td style="font-size:12px; color:#64748b">${c.applied_at.split(' ')[0]}</td>
            <td style="font-size:12px; color:#64748b">${c.interview_date ? c.interview_date.split(' ')[0] : '---'}</td>
            <td style="font-size:12px; color:#64748b">${c.reference_contact || '---'}</td>
            <td style="font-size:12px; color:#64748b">${c.email_tracking_status || '---'}</td>
            <td style="font-size:12px; color:#64748b">${c.last_email_sent_at ? c.last_email_sent_at.split(' ')[0] : '---'}</td>
            <td style="font-size:12px; color:#64748b">${c.last_updated ? c.last_updated.split(' ')[0] : '---'}</td>
        </tr>`;
    }).join('');
    document.getElementById('paginationInfo').innerText = `Hiển thị ${list.length} / ${total}`;
    bindChecks();
}

function bindChecks() {
    const checks = document.querySelectorAll('.cand-check');
    const selectAll = document.getElementById('selectAll');
    const bulkBar = document.getElementById('bulkBar');
    
    selectAll.onchange = (e) => { 
        checks.forEach(c => c.checked = e.target.checked); 
        toggleBar(); 
    };
    
    checks.forEach(c => c.onchange = toggleBar);
    
    function toggleBar() {
        const sel = document.querySelectorAll('.cand-check:checked');
        if (sel.length > 0) {
            bulkBar.style.display = 'flex';
            setTimeout(() => bulkBar.classList.add('active'), 10);
        } else {
            bulkBar.classList.remove('active');
            setTimeout(() => bulkBar.style.display = 'none', 300);
        }
        document.getElementById('selectedCount').innerText = sel.length;
    }
}

function deselectAll() {
    document.getElementById('selectAll').checked = false;
    document.querySelectorAll('.cand-check').forEach(c => c.checked = false);
    const bulkBar = document.getElementById('bulkBar');
    bulkBar.classList.remove('active');
    setTimeout(() => bulkBar.style.display = 'none', 300);
}

function openBulkMove() {
    const sel = Array.from(document.querySelectorAll('.cand-check:checked')).map(c => c.value);
    const jid = document.querySelector('.cand-check:checked').dataset.job; 
    
    document.getElementById('moveModal').style.display = 'flex';
    
    fetch(`/hrm/ajax-handler?action=get_job_steps&job_id=${jid}`).then(r=>r.json()).then(steps => {
        const s = document.getElementById('stepSelect');
        s.innerHTML = steps.map(st=>`<option value="${st.id}">${st.name}</option>`).join('');
    });

    fetch('/hrm/ajax-handler?action=get_email_templates').then(r=>r.json()).then(res => {
        const s = document.getElementById('emailTemplateMove');
        s.innerHTML = res.map(t=>`<option value="${t.id}">${t.name}</option>`).join('');
    });

    document.getElementById('sendEmailMove').onchange = (e) => {
        document.getElementById('emailMoveArea').style.opacity = e.target.checked ? '1' : '0.4';
        document.getElementById('emailMoveArea').style.pointerEvents = e.target.checked ? 'auto' : 'none';
    };

    document.getElementById('confirmMoveBtn').onclick = async () => {
        const step_id = document.getElementById('stepSelect').value;
        const send_email = document.getElementById('sendEmailMove').checked;
        const template_id = document.getElementById('emailTemplateMove').value;

        await fetch('/hrm/ajax-handler?action=bulk_move_stage', {
            method:'POST', 
            body:JSON.stringify({
                ids:sel, 
                step_id,
                send_email,
                template_id
            })
        });
        closeModals(); deselectAll(); fetchCandidates();
    };
}

function openBulkReject() {
    const sel = Array.from(document.querySelectorAll('.cand-check:checked')).map(c => c.value);
    document.getElementById('rejectModal').style.display = 'flex';
    document.getElementById('confirmRejectBtn').onclick = async () => {
        const reason = document.getElementById('rejectReason').value;
        await fetch('/hrm/ajax-handler?action=bulk_reject', {method:'POST', body:JSON.stringify({ids:sel, reason})});
        closeModals(); deselectAll(); fetchCandidates();
    };
}

async function openCandidate(id) {
    document.getElementById('filterSidebar').classList.remove('active');
    document.querySelectorAll('tr').forEach(r => r.classList.remove('active-row'));
    document.getElementById('row-'+id).classList.add('active-row');
    document.getElementById('sideOver').classList.add('active');
    
    const res = await fetch(`/hrm/ajax-handler?action=get_candidate_detail&application_id=${id}`);
    currentDetail = await res.json();
    const d = currentDetail.data;
    
    document.getElementById('soName').innerText = d.full_name;
    document.getElementById('soJob').innerText = d.job_title;
    document.getElementById('soAvatar').innerText = d.full_name[0];
    document.getElementById('soRating').innerText = '★'.repeat(d.rating) + '☆'.repeat(5-d.rating);
    if(d.avatar) document.getElementById('soAvatar').innerHTML = `<img src="${d.avatar}" style="width:100%;height:100%;border-radius:50%">`;
    
    // Handle CV Preview (Prioritize local uploads)
    const cvFile = d.attachments.find(f => f.file_path.startsWith('/uploads')) || d.attachments.find(f => f.file_name.toLowerCase().endsWith('.pdf'));
    const iframe = document.getElementById('cvIframe');
    const empty = document.getElementById('cvEmptyState');
    const openNew = document.getElementById('cvOpenNew');
    
    if (cvFile) {
        iframe.src = cvFile.file_path;
        iframe.style.display = 'block';
        empty.style.display = 'none';
        openNew.href = cvFile.file_path;
        openNew.style.display = 'inline';
    } else {
        iframe.src = '';
        iframe.style.display = 'none';
        empty.style.display = 'block';
        openNew.style.display = 'none';
    }
    
    showTab('info');
}

function showTab(tab) {
    document.querySelectorAll('.so-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    const body = document.getElementById('soBody');
    const d = currentDetail.data;

    if (tab === 'info') {
        body.innerHTML = `
            <div class="info-section">
                <h3 style="font-size:14px; margin-bottom:16px; color:#1e293b">Thông tin cơ bản</h3>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Email</span><span class="info-value">${d.email}</span></div>
                    <div class="info-item"><span class="info-label">Điện thoại</span><span class="info-value">${d.phone || '---'}</span></div>
                    <div class="info-item"><span class="info-label">Ngày sinh</span><span class="info-value">${d.dob || '---'}</span></div>
                    <div class="info-item"><span class="info-label">Nguồn ứng tuyển</span><span class="info-value">${d.source_name || 'Trực tiếp'}</span></div>
                </div>
            </div>
            <div class="info-section">
                <h3 style="font-size:14px; margin-bottom:12px; color:#1e293b">Nhãn (Tags)</h3>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px" id="soTagsList">
                    ${d.tags.map(t => `<div class="tag-v2" style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:4px; font-size:12px; display:flex; align-items:center; gap:6px">
                        ${t} <span style="cursor:pointer; font-weight:700" onclick="handleTagRemove('${t}')">×</span>
                    </div>`).join('')}
                    ${d.tags.length === 0 ? '<span style="color:#94a3b8; font-size:12px">Chưa có nhãn</span>' : ''}
                </div>
                <div style="display:flex; gap:8px">
                    <input type="text" id="newTagName" class="filter-select-v2" style="flex:1; padding:6px 12px" placeholder="Thêm nhãn mới...">
                    <button class="top-btn primary" style="padding:4px 12px" onclick="handleTagAdd()">Thêm</button>
                </div>
            </div>
            <div class="info-section" style="background:#f8fafc; padding:20px; border-radius:12px; border:1px solid #f1f5f9">
                <h3 style="font-size:14px; margin-bottom:12px; color:#1e293b">Vị trí ứng tuyển</h3>
                <div class="info-item"><span class="info-label">Tin tuyển dụng</span><span class="info-value" style="font-weight:600">${d.job_title}</span></div>
                <div class="info-item" style="margin-top:12px"><span class="info-label">Vòng hiện tại</span><span class="info-value">${d.step_name || 'Hồ sơ mới'}</span></div>
            </div>
        `;
    } else if (tab === 'activities') {
        body.innerHTML = `
            <div class="note-box">
                <textarea class="note-area" id="newNote" placeholder="Viết ghi chú cho ứng viên này..."></textarea>
                <div style="display:flex; justify-content:flex-end; margin-top:10px">
                    <button class="top-btn primary" onclick="submitNote()">Lưu ghi chú</button>
                </div>
            </div>
            <div class="timeline-v2">
                ${d.activities.map((a, index) => {
                    let icon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
                    let cls = '';
                    if (a.action_type === 'move_stage') {
                        icon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="9 18 15 12 9 6"/></svg>';
                        cls = 'active';
                    } else if (a.action_type === 'reject') {
                        icon = '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg>';
                        cls = 'rejected';
                    }
                    
                    return `
                    <div class="timeline-item-v2 ${index === 0 ? 'active' : ''}">
                        <div class="timeline-dot-v2" style="${cls==='rejected'?'border-color:#ef4444;background:#ef4444':''}">
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#fff; display:flex; align-items:center; justify-content:center">
                                ${icon}
                            </div>
                        </div>
                        <div class="timeline-content-v2">
                            <div class="timeline-meta-v2">
                                <span class="timeline-user-v2">${a.user_name}</span>
                                <span class="timeline-time-v2">${a.created_at}</span>
                            </div>
                            <div class="timeline-text-v2">${a.note}</div>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        `;
    } else if (tab === 'eval') {
        const d = currentDetail.data;
        let html = '<div style="padding:20px"><h3 style="margin:0 0 16px 0; font-size:16px">Bảng đánh giá năng lực</h3>';
        if (d.job_criteria && d.job_criteria.length > 0) {
            html += '<table class="eval-table"><thead><tr><th>Tiêu chí đánh giá</th><th style="width:100px">Trọng số</th><th style="width:100px">Kỳ vọng</th><th style="width:100px">Thực tế</th></tr></thead><tbody>';
            let lastGroup = '';
            d.job_criteria.forEach(c => {
                if (c.group_name !== lastGroup) {
                    html += `<tr class="eval-group"><td colspan="4">${c.group_name}</td></tr>`;
                    lastGroup = c.group_name;
                }
                html += `<tr>
                    <td>${c.criterion_text}</td>
                    <td>x${c.weight}</td>
                    <td><span class="score-badge expected">${c.expected_score}</span></td>
                    <td><span class="score-badge actual">---</span></td>
                </tr>`;
            });
            html += '</tbody></table>';
        } else {
            html += '<div style="padding:40px; text-align:center; color:#94a3b8"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin-bottom:16px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><br>Chưa có bộ tiêu chí đánh giá cho tin tuyển dụng này.</div>';
        }
        html += '</div>';
        b.innerHTML = html;
    } else if (tab === 'attachments') {
        body.innerHTML = d.attachments.map(f => `
            <div style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:12px; transition:all 0.2s" class="attachment-item">
                <div style="width:36px; height:36px; background:#f1f5f9; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#64748b; font-size:10px; font-weight:700">${f.file_name.split('.').pop().toUpperCase()}</div>
                <div style="flex:1">
                    <div style="font-weight:600;font-size:13px">${f.file_name}</div>
                    <div style="font-size:11px; color:#94a3b8">${f.file_size > 0 ? (f.file_size/1024).toFixed(1) : '---'} KB</div>
                </div>
                <a href="${f.file_path}" target="_blank" class="top-btn outline" style="font-size:11px; padding:6px 10px">Tải xuống</a>
            </div>
        `).join('') || '<div style="text-align:center; padding:40px; color:#94a3b8">Không có file đính kèm.</div>';
    }
}

async function handleRatingClick(e) {
    const rect = e.target.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const rating = Math.ceil(x / (rect.width / 5));
    await fetch('/hrm/ajax-handler?action=update_rating', {method:'POST', body:JSON.stringify({application_id:currentDetail.data.application_id, rating})});
    document.getElementById('soRating').innerText = '★'.repeat(rating) + '☆'.repeat(5-rating);
    fetchCandidates();
}

async function submitNote() {
    const note = document.getElementById('newNote').value;
    if(!note) return;
    await fetch('/hrm/ajax-handler?action=add_activity', {method:'POST', body:JSON.stringify({application_id:currentDetail.data.application_id, note})});
    openCandidate(currentDetail.data.application_id); // Refresh detail
}

function openMoveModal(aid, jid) {
    document.getElementById('moveModal').style.display = 'flex';
    
    // Load Steps
    fetch(`/hrm/ajax-handler?action=get_job_steps&job_id=${jid}`).then(r=>r.json()).then(steps => {
        const s = document.getElementById('stepSelect');
        s.innerHTML = steps.map(st=>`<option value="${st.id}">${st.name}</option>`).join('');
    });

    // Load Email Templates
    fetch('/hrm/ajax-handler?action=get_email_templates').then(r=>r.json()).then(res => {
        const s = document.getElementById('emailTemplateMove');
        s.innerHTML = res.map(t=>`<option value="${t.id}">${t.name}</option>`).join('');
    });

    document.getElementById('sendEmailMove').onchange = (e) => {
        document.getElementById('emailMoveArea').style.opacity = e.target.checked ? '1' : '0.4';
        document.getElementById('emailMoveArea').style.pointerEvents = e.target.checked ? 'auto' : 'none';
    };

    document.getElementById('confirmMoveBtn').onclick = async () => {
        const step_id = document.getElementById('stepSelect').value;
        const send_email = document.getElementById('sendEmailMove').checked;
        const template_id = document.getElementById('emailTemplateMove').value;

        await fetch('/hrm/ajax-handler?action=move_stage', {
            method:'POST', 
            body:JSON.stringify({
                application_id:aid, 
                step_id, 
                send_email, 
                template_id
            })
        });
        closeModals(); openCandidate(aid); fetchCandidates();
    };
}

function openRejectModal(aid) {
    document.getElementById('rejectModal').style.display = 'flex';
    document.getElementById('confirmRejectBtn').onclick = async () => {
        const reason = document.getElementById('rejectReason').value;
        await fetch('/hrm/ajax-handler?action=reject_candidate', {method:'POST', body:JSON.stringify({application_id:aid, reason})});
        closeModals(); openCandidate(aid); fetchCandidates();
    };
}

async function seedData() {
    if (!confirm('Bạn có muốn seed dữ liệu mẫu?')) return;
    const btn = event.target;
    btn.innerText = 'Đang seed...';
    btn.disabled = true;
    try {
        await fetch('/hrm/ajax-handler?action=import_candidates');
        alert('Đã seed dữ liệu mẫu thành công!');
        fetchCandidates();
    } catch (e) {
        alert('Lỗi khi seed dữ liệu');
    } finally {
        btn.innerText = '⚡ Seed dữ liệu';
        btn.disabled = false;
    }
}

function resetFilters() {
    filters = { search: '', job_id: 0, status: 'all', source_id: 0, owner_id: 0, tag: '', date_from: '', date_to: '', sort: 'newest', page: 1, limit: 20 };
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('jobFilter').value = '0';
    document.getElementById('sourceFilter').value = '0';
    document.getElementById('ownerFilter').value = '0';
    document.getElementById('tagFilter').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('sortFilter').value = 'newest';
    document.getElementById('topSearch').value = '';
    fetchCandidates();
}

function toggleFilterSidebar() { document.getElementById('filterSidebar').classList.toggle('active'); }
function closeSideOver() { document.getElementById('sideOver').classList.remove('active'); document.querySelectorAll('tr').forEach(r => r.classList.remove('active-row')); }
function closeModals() { document.querySelectorAll('.modal-overlay').forEach(m=>m.style.display='none'); }

document.querySelectorAll('.so-tab').forEach(t => t.onclick = () => showTab(t.dataset.tab));
document.getElementById('topSearch').oninput = (e) => { filters.search = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('jobFilter').onchange = (e) => { filters.job_id = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('statusFilter').onchange = (e) => { filters.status = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('sourceFilter').onchange = (e) => { filters.source_id = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('ownerFilter').onchange = (e) => { filters.owner_id = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('dateFrom').onchange = (e) => { filters.date_from = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('dateTo').onchange = (e) => { filters.date_to = e.target.value; filters.page = 1; fetchCandidates(); };
document.getElementById('sortFilter').onchange = (e) => { filters.sort = e.target.value; filters.page = 1; fetchCandidates(); };
async function handleTagAdd() {
    const name = document.getElementById('newTagName').value.trim();
    if (!name) return;
    const aid = currentDetail.data.application_id;
    await fetch('/hrm/ajax-handler?action=add_tag', {
        method: 'POST',
        body: JSON.stringify({ application_id: aid, tag_name: name })
    });
    openCandidate(aid); // Refresh side-over
}

async function handleTagRemove(name) {
    if (!confirm('Xóa nhãn này?')) return;
    const aid = currentDetail.data.application_id;
    await fetch('/hrm/ajax-handler?action=remove_tag', {
        method: 'POST',
        body: JSON.stringify({ application_id: aid, tag_name: name })
    });
    openCandidate(aid); // Refresh side-over
}

async function handleRatingClick(e) {
    const aid = currentDetail.data.application_id;
    const rect = e.target.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const starWidth = rect.width / 5;
    const rating = Math.ceil(x / starWidth);
    
    const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
    document.getElementById('soRating').innerText = stars;
    
    await fetch('/hrm/ajax-handler?action=update_rating', {
        method: 'POST',
        body: JSON.stringify({ application_id: aid, rating: rating })
    });
    fetchCandidates(); // Refresh list to show new rating
}

document.getElementById('prevPage').onclick = () => { if (filters.page > 1) { filters.page--; fetchCandidates(); } };
document.getElementById('nextPage').onclick = () => { filters.page++; fetchCandidates(); };

document.getElementById('tagFilter').onchange = (e) => { filters.tag = e.target.value; filters.page = 1; fetchCandidates(); };
document.addEventListener('DOMContentLoaded', loadInitialData);
</script>
</body>
</html>
