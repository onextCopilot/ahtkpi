<?php
require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quyết định Cơ chế Bonus Core/Key Members - AHT Tech</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --secondary: #64748b;
            --text-dark: #0f172a;
            --text-body: #334155;
            --surface: #ffffff;
            --border: #e2e8f0;
            --radius: 16px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: var(--text-body);
            line-height: 1.7;
            scroll-behavior: smooth;
        }

        .dashboard-container { display: flex; min-height: 100vh; }

        .guide-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 40px;
            max-width: 1300px;
            margin: 40px auto 100px;
            padding: 0 40px;
        }

        /* Sticky Navigation Sidebar */
        .guide-nav {
            position: sticky;
            top: 40px;
            height: fit-content;
            background: white;
            padding: 24px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            z-index: 10;
        }

        .guide-nav-title {
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--secondary);
            margin-bottom: 20px;
            letter-spacing: 0.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .guide-nav ul { list-style: none; padding: 0; }
        .guide-nav li { margin-bottom: 8px; }
        .guide-nav a {
            display: block;
            padding: 8px 12px;
            border-radius: 8px;
            color: var(--secondary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .guide-nav a:hover { background: var(--primary-light); color: var(--primary); }
        .guide-nav a.active { background: var(--primary); color: white; }

        /* Document Styling */
        .doc-wrapper {
            background: var(--surface);
            border-radius: 20px;
            padding: 60px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
        }

        .doc-header {
            text-align: center;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 40px;
            margin-bottom: 50px;
        }

        .company-name { font-weight: 800; text-transform: uppercase; margin-bottom: 24px; font-size: 1.1rem; color: var(--text-dark); letter-spacing: 1.2px; text-align: left; }
        .doc-header h1 { font-size: 2rem; font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin-bottom: 15px; line-height: 1.3; }
        .doc-subtitle { color: var(--primary); font-weight: 700; font-size: 1.1rem; margin-bottom: 5px; }
        .doc-meta { color: var(--secondary); font-size: 0.9rem; font-weight: 500; font-style: italic; }

        .article { margin-bottom: 60px; scroll-margin-top: 40px; }
        .article-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .article-title .icon-bg {
            width: 42px;
            height: 42px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.2rem;
        }

        .content-box { padding-left: 57px; }
        .content-box p { font-family: 'Inter', sans-serif; color: var(--text-body); margin-bottom: 16px; text-align: justify; }

        .list-main { list-style: none; padding: 0; margin-bottom: 20px; }
        .list-main > li { position: relative; padding-left: 30px; margin-bottom: 16px; color: var(--text-dark); font-weight: 600; font-size: 1rem; }
        .list-main > li > i { position: absolute; left: 0; top: 4px; color: var(--primary); font-size: 0.9rem; }

        .sub-list { list-style: none; padding-left: 20px; margin-top: 10px; font-weight: 400; color: var(--text-body); }
        .sub-list li { position: relative; padding-left: 24px; margin-bottom: 10px; font-size: 0.95rem; }
        .sub-list li::before { content: '→'; position: absolute; left: 0; color: var(--secondary); }

        .formula-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .formula-label { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: white; padding: 0 15px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--secondary); border: 1px solid var(--border); border-radius: 20px; }
        .formula-text { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.4rem; color: var(--text-dark); letter-spacing: -0.02em; }

        .highlight-notice {
            background: #fffbeb;
            border-radius: 12px;
            padding: 20px 25px;
            border-left: 6px solid #f59e0b;
            margin: 25px 0;
            display: flex;
            gap: 15px;
        }

        .highlight-text { color: #92400e; font-size: 0.95rem; font-weight: 500; line-height: 1.6; }

        .weight-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .weight-pill {
            background: white;
            border: 1px solid var(--border);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .weight-pill .val { font-size: 1.6rem; font-weight: 800; color: var(--primary); display: block; }
        .weight-pill .lab { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--secondary); }

        /* Side-by-side boxes for Art 2 */
        .parallel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .parallel-card {
            background: #fafafa;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.01);
            position: relative;
            overflow: hidden;
        }

        .card-icon { position: absolute; right: 15px; top: 15px; font-size: 2.2rem; color: #e2e8f0; pointer-events: none; }

        .parallel-card h4 { font-weight: 800; color: var(--text-dark); margin-bottom: 12px; font-size: 1.05rem; display: flex; align-items: center; gap: 8px; position: relative; z-index: 1; }
        .parallel-card h4 i { color: var(--primary); font-size: 0.9rem; }
        
        .parallel-card p { font-size: 0.9rem; line-height: 1.6; color: var(--text-body); margin: 0; position: relative; z-index: 1; text-align: justify; }

        /* Warning styles for Principles list */
        .warning-list { list-style: none; padding: 0; margin-top: 15px; }
        .warning-list li {
            position: relative;
            padding: 12px 15px 12px 45px;
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 0.95rem;
            color: #92400e;
            font-weight: 600;
        }
        .warning-list li i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #d97706;
            font-size: 1.2rem;
        }

        .appendix-header {
            background: var(--text-dark);
            color: white;
            padding: 24px 30px;
            border-radius: 12px;
            margin-top: 80px;
            margin-bottom: 40px;
            text-align: center;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .example-card { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-top: 25px; background: white; }
        .card-table { width: 100%; border-collapse: collapse; }
        .card-table th { background: #f8fafc; padding: 15px 20px; text-align: left; font-size: 0.85rem; font-weight: 800; color: var(--secondary); border-bottom: 2px solid var(--border); }
        .card-table td { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        .card-table tr.total { background: #eff6ff; font-weight: 800; color: var(--primary); }

        .signature-block { margin-top: 60px; text-align: right; }
        .signature-title { font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin-bottom: 70px; }
        .signature-seal { font-weight: 700; color: var(--secondary); font-style: italic; }

        @media (max-width: 1024px) {
            .guide-layout { grid-template-columns: 1fr; padding: 0 20px; }
            .guide-nav { display: none; }
            .doc-wrapper { padding: 40px 20px; }
            .parallel-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="guide-layout">
                
                <!-- Quick Navigation -->
                <nav class="guide-nav">
                    <div class="guide-nav-title"><i class="fas fa-list-ul"></i> Mục lục đề mục</div>
                    <ul id="guideMenu">
                        <li><a href="#art1">Điều 1: Mục đích & Phạm vi</a></li>
                        <li><a href="#art2">Điều 2: Định nghĩa & Nguyên tắc</a></li>
                        <li><a href="#art3">Điều 3: Cơ cấu & Trần Bonus</a></li>
                        <li><a href="#art4">Điều 4: Cấu trúc KPI</a></li>
                        <li><a href="#art5">Điều 5: Nguyên tắc tính toán</a></li>
                        <li><a href="#art6">Điều 6: Thời điểm chi trả</a></li>
                        <li><a href="#art7">Điều 7: Điều kiện chi trả</a></li>
                        <li><a href="#art8">Điều 8: Trách nhiệm</a></li>
                        <li><a href="#art9">Điều 9: Bảo mật thông tin</a></li>
                        <li><a href="#art10">Điều 10: Hiệu lực</a></li>
                        <li><a href="#appendix">Phụ lục & Ví dụ</a></li>
                    </ul>
                </nav>

                <!-- Documentation -->
                <div class="doc-wrapper">
                    
                    <div class="doc-header">
                        <div class="company-name">AHT TECH JOINT STOCK COMPANY</div>
                        <p class="doc-subtitle">Quyết định cơ chế nội bộ</p>
                        <h1>Bonus Core/Key Members Policy</h1>
                        <p class="doc-meta">Số tài liệu: 01/2026/QĐ-TGĐ • Hiệu lực: 01/02/2026</p>
                    </div>

                    <!-- Art 1 -->
                    <section class="article" id="art1">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-award"></i></span> Điều 1: Mục đích và phạm vi áp dụng</h2>
                        <div class="content-box">
                            <div class="policy-box" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:16px; padding:30px; margin-bottom:25px;">
                                <p style="font-weight: 700; color: var(--text-dark); margin-bottom: 12px; font-size: 1.05rem;">Công ty ban hành cơ chế bonus dành cho đội ngũ Core/Key Members, nhằm:</p>
                                <ul class="list-main" style="margin-bottom: 0;">
                                    <li><i class="fas fa-check-circle" style="color:var(--primary)"></i> Gắn kết trách nhiệm cá nhân với kết quả Công ty/BC/Bộ phận.</li>
                                    <li><i class="fas fa-check-circle" style="color:var(--primary)"></i> Ghi nhận đóng góp vượt trội và giữ chân đội ngũ nhân sự nòng cốt.</li>
                                    <li><i class="fas fa-check-circle" style="color:var(--primary)"></i> Đảm bảo minh bạch, công bằng và kiểm soát rủi ro tài chính.</li>
                                </ul>
                            </div>
                            <div style="background:#eff6ff; border:1px solid #dbeafe; border-radius:12px; padding:16px 20px; margin-bottom:12px; display:flex; align-items:center; gap:15px; color:#1e40af; font-size:0.95rem; font-weight:500;">
                                <i class="fas fa-circle-info" style="color:var(--primary)"></i>
                                <span>Quyết định này không thay thế các chính sách lương, thưởng, phúc lợi hiện hành khác của Công ty.</span>
                            </div>
                            <div style="background:#eff6ff; border:1px solid #dbeafe; border-radius:12px; padding:16px 20px; margin-bottom:12px; display:flex; align-items:center; gap:15px; color:#1e40af; font-size:0.95rem; font-weight:500;">
                                <i class="fas fa-user-group" style="color:var(--primary)"></i>
                                <span>Áp dụng cho toàn bộ Core/Key Members được phê duyệt theo danh sách từng năm.</span>
                            </div>
                        </div>
                    </section>

                    <!-- Art 2 -->
                    <section class="article" id="art2">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-id-badge"></i></span> Điều 2: Định nghĩa và nguyên tắc chung</h2>
                        <div class="content-box">
                            <p><strong>1. Định nghĩa</strong></p>
                            
                            <div class="parallel-grid">
                                <div class="parallel-card">
                                    <i class="fas fa-crown card-icon"></i>
                                    <h4><i class="fas fa-gem"></i> Core Members</h4>
                                    <p>Là các vị trí trụ cột chiến lược do Tổng Giám đốc quyết định, bao gồm các chức danh quản lý cấp cao từ cấp Director trở lên ở Tổng công ty hoặc Giám đốc, Phó Giám đốc tại các công ty thành viên và các vị trí khác được Tổng Giám đốc xác định là có vai trò chiến lược theo từng giai đoạn phát triển của Công ty.</p>
                                </div>
                                <div class="parallel-card">
                                    <i class="fas fa-key card-icon"></i>
                                    <h4><i class="fas fa-user-shield"></i> Key Members</h4>
                                    <p>Là các nhân sự then chốt theo từng Đơn vị/Bộ phận, có ảnh hưởng lớn đến kết quả kinh doanh, vận hành và được rà soát hàng năm theo sự đề xuất của Trưởng bộ phận/Ban điều hành.</p>
                                </div>
                            </div>
                            
                            <p><strong>2. Nguyên tắc</strong></p>
                            <ul class="warning-list">
                                <li><i class="fas fa-square-check"></i> Bonus được xem xét dựa trên kết quả đánh giá KPI theo năm tài chính của Công ty/Công ty thành viên, Bộ phận và mức độ đóng góp thực tế của mỗi cá nhân.</li>
                                <li><i class="fas fa-square-check"></i> Danh sách Core/Key Members và mức bonus có thể được điều chỉnh theo tình hình hoạt động của Công ty.</li>
                                <li><i class="fas fa-square-check"></i> Việc chi trả bonus được thực hiện theo năm tài chính.</li>
                                <li><i class="fas fa-square-check"></i> Tổng Giám đốc là người xem xét, quyết định cuối cùng trong các trường hợp đặc biệt.</li>
                            </ul>
                            
                            <div class="highlight-notice" style="margin-top: 25px;">
                                <i class="fas fa-bookmark"></i>
                                <span class="highlight-text">Tiêu chí đánh giá nhân sự Core/Key Members được quy định chi tiết tại Phụ lục 01 ban hành kèm theo Quyết định này.</span>
                            </div>
                        </div>
                    </section>

                    <!-- Art 3 -->
                    <section class="article" id="art3">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-coins"></i></span> Điều 3: Cấu trúc thu nhập và mức bonus</h2>
                        <div class="content-box">
                            <p><strong>1. Cơ cấu lương</strong></p>
                            <div class="formula-container">
                                <div class="formula-label">Nguyên tắc cơ bản</div>
                                <div class="formula-text" style="font-size:1.4rem;">Tổng lương = Lương cơ bản + Lương bổ sung</div>
                            </div>
                            <p>Trong đó: Bonus Key/core được tính dựa trên phần <strong>Lương bổ sung</strong>.</p>
                            
                            <p style="margin-top: 25px;"><strong>2. Mức trần bonus tối đa</strong></p>
                            <div class="formula-container" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                                <div class="formula-label" style="border-color: #3b82f6; color: #1d4ed8;">Mức trần quy định</div>
                                <div class="formula-text" style="color: #1e40af; font-size:1.4rem;">Trần bonus tối đa năm = 20% * Tổng Lương bổ sung năm</div>
                            </div>
                            <p style="font-size: 0.95rem; color: var(--secondary);">Mức trần bonus chỉ áp dụng cho cơ chế bonus Core/Key Members theo quyết định này. Các khoản phụ cấp, phúc lợi khác vẫn được duy trì theo chính sách công ty.</p>
                        </div>
                    </section>

                    <!-- Art 4 -->
                    <section class="article" id="art4">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-sitemap"></i></span> Điều 4: Cấu trúc KPI dành cho Core/Key members</h2>
                        <div class="content-box">
                            <p><strong>1. Cấu trúc KPI của Core/Key Members</strong></p>
                            <p>KPI của Core/Key Members được đánh giá theo kết quả KPI năm, bao gồm ba nhóm KPI với tỷ trọng như sau:</p>
                            <div class="weight-grid">
                                <div class="weight-pill"><span class="val">30%</span><span class="lab">KPI Công ty</span></div>
                                <div class="weight-pill"><span class="val">30%</span><span class="lab">KPI Bộ phận</span></div>
                                <div class="weight-pill" style="background: var(--primary-light);"><span class="val">40%</span><span class="lab">KPI Cá nhân</span></div>
                            </div>

                            <p>Cách tính Key KPI Score:</p>
                            <div class="formula-container">
                                <div class="formula-text" style="font-size: 1.15rem;">Key KPI Score = [(KPI năm Công ty * 30%) + (KPI năm Bộ phận * 30%) + (KPI năm Cá nhân * 40%)]</div>
                            </div>
                            
                            <p>Trong đó:</p>
                            <ul class="sub-list">
                                <li>Kết quả KPI của từng nhóm được tính theo tỷ lệ hoàn thành thực tế. Trường hợp KPI Công ty, KPI Bộ phận hoặc KPI Cá nhân đạt trên 100%, điểm KPI tương ứng vẫn được ghi nhận theo kết quả thực tế, do đó Key KPI Score có thể lớn hơn 100%.</li>
                                <li>KPI năm của cá nhân Core/Key Members chiếm tỷ trọng cao nhất 40% và là bộ KPI riêng phục vụ đánh giá, chi trả bonus Core/Key, độc lập với KPI cá nhân tổng kết hàng tháng.</li>
                            </ul>

                            <p style="margin-top: 25px;"><strong>2. Nội dung đánh giá KPI</strong></p>
                            <ul class="list-main">
                                <li><i class="fas fa-circle-dot"></i> <strong>KPI năm - Công ty:</strong> Đánh giá mức độ hoàn thành các mục tiêu trọng yếu của Công ty là kết quả các bộ chỉ tiêu KPI năm của công ty.</li>
                                <li><i class="fas fa-circle-dot"></i> <strong>KPI năm - Bộ phận:</strong> Đánh giá kết quả hoạt động của bộ phận/ đơn vị mà Key Members trực thuộc là kết quả KPI của các bộ chỉ tiêu KPI năm của bộ phận/ đơn vị hàng năm.</li>
                                <li><i class="fas fa-circle-dot"></i> <strong>KPI năm - Cá nhân:</strong> Đánh giá mức độ hoàn thành vai trò của cá nhân là kết quả KPI của cá nhân trong năm, thường tập trung vào các yếu tố chính:
                                    <ul class="sub-list" style="margin-left: 10px;">
                                        <li>Ownership kết quả công việc và trách nhiệm với phạm vi công việc được giao.</li>
                                        <li>Năng lực dẫn dắt, quản lý, phối hợp và xử lý vấn đề.</li>
                                        <li>Đóng góp ngoài phạm vi vị trí khi được yêu cầu.</li>
                                    </ul>
                                </li>
                            </ul>
                            <div class="highlight-notice" style="background: #fef2f2; border-color: #ef4444; color: #991b1b;">
                                <i class="fas fa-triangle-exclamation" style="color: #b91c1c;"></i>
                                <span class="highlight-text"><strong>Lưu ý:</strong> Trường hợp KPI Công ty hoặc KPI Bộ phận không đạt, việc xét chi trả bonus cho Core/Key Members do Ban Điều hành xem xét và quyết định.</span>
                            </div>
                        </div>
                    </section>

                    <!-- Art 5-10 Full Text Re-Enforced -->
                    <section class="article" id="art5">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-calculator"></i></span> Điều 5: Nguyên tắc tính bonus Core/Key Members</h2>
                        <div class="content-box">
                            <ul class="list-main">
                                <li>Bonus Core/Key Members được xác định theo công thức:
                                    <div class="formula-container" style="background: var(--text-dark); border: none;">
                                        <div class="formula-text" style="color: white; font-size:1.4rem;">Bonus thực nhận = Key KPI Score x Trần Bonus tối đa năm</div>
                                    </div>
                                </li>
                                <li>Key KPI Score được tính trên cơ sở kết quả đánh giá thực tế của từng nhóm KPI theo tỷ trọng quy định và không đảm bảo chi trả trong trường hợp không đạt yêu cầu.</li>
                                <li>Trường hợp KPI Công ty hoặc KPI Bộ phận không đạt, việc ghi nhận và tính điểm KPI thực hiện theo nguyên tắc:
                                    <ul class="sub-list">
                                        <li>Không ghi nhận điểm cho nhóm KPI không đạt.</li>
                                        <li>Phần KPI cá nhân vẫn được xem xét đánh giá theo kết quả thực tế.</li>
                                        <li>Tổng mức bonus chi trả chỉ được tính trên phần KPI đạt và được ghi nhận theo đúng tỷ trọng quy định. Các nhóm KPI không đạt sẽ không được tính vào điểm KPI và không phát sinh bonus.</li>
                                    </ul>
                                </li>
                                <li>Tổng Giám đốc là người có thẩm quyền xem xét và quyết định cuối cùng trong các trường hợp đặc biệt.</li>
                            </ul>
                        </div>
                    </section>

                    <section class="article" id="art6">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-calendar-check"></i></span> Điều 6: Thời điểm và lịch chi trả</h2>
                        <div class="content-box">
                            <ul class="list-main">
                                <li>Bonus dành cho Core/Key Members được tổng kết và quyết toán theo năm tài chính (N).</li>
                                <li>Việc chi trả thưởng được thực hiện sang năm tài chính tiếp theo (N+1) với mục tiêu gắn trách nhiệm cá nhân với kết quả kinh doanh và đảm bảo kiểm soát dòng tiền, hiệu quả tài chính của Công ty.</li>
                                <li>Lịch chi trả bonus:
                                    <div style="display: flex; gap: 20px; margin-top: 15px;">
                                        <div style="flex: 1; background: #fff; border: 1.5px solid var(--border); padding: 24px; border-radius: 12px; text-align: center; border-bottom: 4px solid var(--primary);">
                                            <span style="font-weight: 800; font-size: 0.8rem; color: var(--primary); display: block; margin-bottom: 5px; text-transform: uppercase;">ĐỢT 01 (Tháng 5 N+1)</span>
                                            <span style="font-size: 1.4rem; font-weight: 800; color:var(--text-dark)">60% tổng bonus</span>
                                        </div>
                                        <div style="flex: 1; background: #fff; border: 1.5px solid var(--border); padding: 24px; border-radius: 12px; text-align: center; border-bottom: 4px solid #10b981;">
                                            <span style="font-weight: 800; font-size: 0.8rem; color: #10b981; display: block; margin-bottom: 5px; text-transform: uppercase;">ĐỢT 02 (Tháng 10 N+1)</span>
                                            <span style="font-size: 1.4rem; font-weight: 800; color:var(--text-dark)">40% tổng bonus</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <section class="article" id="art7">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-user-lock"></i></span> Điều 7: Điều kiện và nguyên tắc chi trả bonus</h2>
                        <div class="content-box">
                            <p><strong>1. Điều kiện nhận bonus</strong></p>
                            <p>Bonus được chi trả theo từng đợt khi Core/Key Members đồng thời đáp ứng các điều kiện sau tại thời điểm chi trả:</p>
                            <ul class="sub-list" style="margin-bottom: 25px;">
                                <li>Đang làm việc tại Công ty tại thời điểm chi trả.</li>
                                <li>Không vi phạm kỷ luật mức nghiêm trọng.</li>
                                <li>Không gây thiệt hại nghiêm trọng về tài chính hoặc uy tín cho Công ty.</li>
                                <li>Không để Bộ phận phụ trách rơi vào tình trạng khủng hoảng do lỗi quản trị cá nhân.</li>
                            </ul>
                            <p><strong>2. Trường hợp chấm dứt hợp đồng lao động</strong></p>
                            <ul class="list-main">
                                <li>Trường hợp nhân sự chấm dứt hợp đồng lao động trước thời điểm chi trả Đợt 1: Nhân sự không được chi trả bonus của năm tài chính đó.</li>
                                <li>Trường hợp nhân sự chấm dứt hợp đồng lao động sau thời điểm chi trả Đợt 1 và trước thời điểm chi trả Đợt 2: Nhân sự chỉ được nhận phần bonus đã chi trả đợt 1, không được tất toán phần bonus còn lại.</li>
                                <li>Trường hợp nhân sự chấm dứt hợp đồng lao động sau thời điểm chi trả Đợt 2: Nhân sự được nhận đầy đủ bonus theo quy định.</li>
                            </ul>
                            <div class="highlight-notice" style="background: var(--primary-light); border-color: var(--primary); color: #1e3a8a;">
                                <i class="fas fa-circle-exclamation" style="color: var(--primary);"></i>
                                <span class="highlight-text">Trường hợp không đáp ứng điều kiện tại thời điểm chi trả, Công ty sẽ không thực hiện chi trả phần bonus chưa đến hạn và không truy thu phần bonus đã chi trả trước đó.</span>
                            </div>
                            <p><strong>3. Trường hợp bất khả kháng</strong></p>
                            <p>Các trường hợp bất khả kháng (ốm đau dài hạn, tái cơ cấu theo quyết định của Công ty...) được Tổng Giám đốc xem xét và quyết định riêng.</p>
                        </div>
                    </section>

                    <section class="article" id="art8">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-people-group"></i></span> Điều 8: Trách nhiệm thực hiện</h2>
                        <div class="content-box">
                            <ul class="list-main">
                                <li><strong>Phòng Hành chính - Nhân sự:</strong> Chủ trì triển khai, hướng dẫn, giám sát việc thực hiện quyết định; tổng hợp kết quả đánh giá và lập danh sách chi trả trình Tổng Giám đốc phê duyệt.</li>
                                <li><strong>Trưởng các Khối/Đơn vị/Bộ phận:</strong> Tổ chức thực hiện đánh giá KPI cho Core/Key Members thuộc phạm vi phụ trách, chịu trách nhiệm về tính chính xác và khách quan của kết quả đánh giá.</li>
                                <li><strong>Cán bộ nhân viên thuộc đối tượng áp dụng:</strong> Có trách nhiệm nỗ lực thực hiện các mục tiêu được giao, phối hợp trong quá trình đánh giá và xác nhận kết quả.</li>
                            </ul>
                        </div>
                    </section>

                    <section class="article" id="art9">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-mask"></i></span> Điều 9: Bảo mật thông tin</h2>
                        <div class="content-box">
                            <p>Các tài liệu, thông tin liên quan đến danh sách Core/Key Members và mức chi trả bonus là thông tin bảo mật tuyệt mật của Công ty. Cán bộ nhân viên có liên quan cam kết không tiết lộ thông tin này cho bất kỳ bên thứ ba nào khi chưa có sự đồng ý bằng văn bản của người có thẩm quyền. Mọi vi phạm về bảo mật thông tin sẽ bị xử lý kỷ luật nghiêm theo Nội quy lao động của Công ty.</p>
                        </div>
                    </section>

                    <section class="article" id="art10">
                        <h2 class="article-title"><span class="icon-bg"><i class="fas fa-check-double"></i></span> Điều 10: Hiệu lực thi hành</h2>
                        <div class="content-box">
                            <p>Quyết định này có hiệu lực thi hành kể từ ngày <strong>01/02/2026</strong> cho đến khi có văn bản thay thế. Các Phòng/Ban, Đơn vị và các cá nhân có liên quan chịu trách nhiệm thi hành quyết định này.</p>
                        </div>
                    </section>

                    <div class="signature-block">
                        <div class="signature-title">TỔNG GIÁM ĐỐC CÔNG TY AHT TECH</div>
                        <div class="signature-seal">Đã ký và phê duyệt lưu hành nội bộ</div>
                    </div>

                    <!-- APPENDICES (100% LITERAL TEXT ENFORCED) -->
                    <div id="appendix">
                        
                        <!-- Appendix 1 -->
                        <div class="appendix-header">PHỤ LỤC 01: CHI TIẾT CÁC TIÊU THỨC ĐÁNH GIÁ CORE/KEY MEMBERS</div>
                        <div class="content-box">
                            <p style="font-weight:700; color:var(--text-dark); margin-bottom:20px;">Tiêu thức đánh giá mức độ hội tụ các yếu tố dành cho Core/Key members của Công ty:</p>
                            <div class="responsibility-grid" style="grid-template-columns: 1fr; gap: 20px;">
                                <div class="weight-pill" style="text-align: left; padding: 24px; border-left: 5px solid var(--primary); background: #fff;">
                                    <h5 style="font-weight: 800; color: var(--text-dark); margin-bottom: 15px; font-size: 1.1rem;">1. Vai trò và Mức độ ảnh hưởng</h5>
                                    <p style="font-size: 0.95rem; line-height: 1.8; color: var(--text-body);">Nắm giữ vị trí then chốt trong bộ máy quản trị hoặc vận hành của Công ty/BC/Bộ phận. Có ảnh hưởng trực tiếp đến kết quả kinh doanh, sự ổn định và phát triển chiến lược của đơn vị.</p>
                                </div>
                                <div class="weight-pill" style="text-align: left; padding: 24px; border-left: 5px solid #10b981; background: #fff;">
                                    <h5 style="font-weight: 800; color: var(--text-dark); margin-bottom: 15px; font-size: 1.1rem;">2. Hiệu quả Công việc (KPI)</h5>
                                    <p style="font-size: 0.95rem; line-height: 1.8; color: var(--text-body);">Kết quả KPI cá nhân được đánh giá định kỳ đạt từ 100% trở lên. Đảm bảo chất lượng, tiến độ công việc, ngân sách và các chỉ số quản trị vận hành (SLA) trong phạm vi phụ trách.</p>
                                </div>
                                <div class="weight-pill" style="text-align: left; padding: 24px; border-left: 5px solid #f59e0b; background: #fff;">
                                    <h5 style="font-weight: 800; color: var(--text-dark); margin-bottom: 15px; font-size: 1.1rem;">3. Mức độ Cam kết và Trách nhiệm</h5>
                                    <p style="font-size: 0.95rem; line-height: 1.8; color: var(--text-body);">Thể hiện tinh thần tiên phong, gương mẫu theo các giá trị cốt lõi của công ty. Có cam kết gắn bó lâu dài và sẵn sàng nhận các nhiệm vụ thách thức từ Ban lãnh đạo.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Appendix 2 -->
                        <div class="appendix-header" style="background: var(--primary);">PHỤ LỤC 02: VÍ DỤ MINH HỌA VỀ CÁCH TÍNH VÀ CHI TRẢ BONUS</div>
                        <div class="content-box">
                            <div style="background: #f8fafc; padding: 35px; border-radius: 16px; border: 1px solid var(--border);">
                                
                                <p style="font-weight: 800; color: var(--text-dark); margin-bottom: 15px; font-size: 1rem;">1. Dữ liệu giả định dành cho Nhân viên A có:</p>
                                <ul class="sub-list" style="margin-bottom: 30px; font-weight:600;">
                                    <li>Tổng Lương bổ sung năm tài chính (N) = 300.000.000 VNĐ.</li>
                                    <li>Định mức Trần bonus tối đa năm = 20% x Tổng Lương bổ sung = 60.000.000 VNĐ.</li>
                                </ul>

                                <p style="font-weight: 800; color: var(--text-dark); margin-bottom: 15px; font-size: 1rem;">2. Kết quả đánh giá KPI năm tài chính (N) của Nhân viên A (Giả định):</p>
                                <div class="example-card" style="box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                                    <table class="card-table">
                                        <thead>
                                            <tr>
                                                <th>NHÓM KPI ĐÁNH GIÁ</th>
                                                <th style="text-align:center">TỶ TRỌNG</th>
                                                <th style="text-align:center">KẾT QUẢ ĐẠT</th>
                                                <th style="text-align:right">SCORE THÀNH PHẦN</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>KPI năm Công ty</td>
                                                <td style="text-align:center">30%</td>
                                                <td style="text-align:center">75%</td>
                                                <td style="text-align:right">75% x 30% = 22.5%</td>
                                            </tr>
                                            <tr>
                                                <td>KPI năm Bộ phận</td>
                                                <td style="text-align:center">30%</td>
                                                <td style="text-align:center">70%</td>
                                                <td style="text-align:right">70% x 30% = 21.0%</td>
                                            </tr>
                                            <tr>
                                                <td>KPI năm Cá nhân</td>
                                                <td style="text-align:center">40%</td>
                                                <td style="text-align:center">90%</td>
                                                <td style="text-align:right">90% x 40% = 36.0%</td>
                                            </tr>
                                            <tr class="total">
                                                <td colspan="3" style="text-align:right">TỔNG ĐIỂM KEY KPI SCORE</td>
                                                <td style="text-align:right; font-size:1.2rem">79.5%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p style="font-size: 0.9rem; color: var(--secondary); margin-top:10px; font-style:italic;">(Cách tính: 22.5% + 21.0% + 36.0% = 79.5%)</p>

                                <p style="font-weight: 800; color: var(--text-dark); margin-top: 35px; margin-bottom: 15px; font-size: 1rem;">3. Xác định mức bonus và lịch chi trả:</p>
                                <div style="background: white; border: 2px solid var(--primary-light); padding: 25px; border-radius: 12px; margin-bottom: 30px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:10px;">
                                        <span style="font-weight: 700; font-size: 1.05rem;">Bonus thực nhận năm tài chính (N)</span>
                                        <span style="font-weight: 800; color: var(--primary); font-size: 1.4rem;">47.700.000 VNĐ</span>
                                    </div>
                                    <p style="font-size: 0.9rem; color: var(--secondary); text-align:right; margin:0;">(Cách tính: 79.5% x 60.000.000 VNĐ)</p>
                                </div>

                                <p style="font-weight: 700; color: var(--text-dark); margin-bottom: 15px;">Thời điểm chi trả: Được thực hiện trong năm tài chính (N+1) theo 2 đợt:</p>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div style="background: white; border: 1.5px solid var(--border); padding: 25px; border-radius: 12px; text-align: center; border-top: 5px solid var(--primary);">
                                        <span style="font-weight: 800; font-size: 0.85rem; color: var(--primary); display: block; margin-bottom: 5px; text-transform: uppercase;">CHI TRẢ ĐỢT 01 (T5/N+1)</span>
                                        <div style="font-size: 1.3rem; font-weight: 800; margin: 10px 0; color:var(--text-dark)">28.620.000 VNĐ</div>
                                        <span style="font-size: 0.85rem; color: var(--secondary);">(Cách tính: 60% x 47.700.000 VNĐ)</span>
                                    </div>
                                    <div style="background: white; border: 1.5px solid var(--border); padding: 25px; border-radius: 12px; text-align: center; border-top: 5px solid #10b981;">
                                        <span style="font-weight: 800; font-size: 0.85rem; color: #10b981; display: block; margin-bottom: 5px; text-transform: uppercase;">CHI TRẢ ĐỢT 02 (T10/N+1)</span>
                                        <div style="font-size: 1.3rem; font-weight: 800; margin: 10px 0; color:var(--text-dark)">19.080.000 VNĐ</div>
                                        <span style="font-size: 0.85rem; color: var(--secondary);">(Cách tính: 40% x 47.700.000 VNĐ)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; color: #94a3b8; font-size: 0.8rem; margin-top: 80px;">
                        <p>© 2026 AHT Tech - Hệ thống Quản trị KPI & Bonus</p>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const sections = document.querySelectorAll('section.article');
        const navLinks = document.querySelectorAll('.guide-nav a');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 150) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>

</html>
