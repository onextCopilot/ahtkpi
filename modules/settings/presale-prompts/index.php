<?php
require_once __DIR__ . '/../../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: /dashboard");
    exit();
}

$full_name = $_SESSION['full_name'] ?? 'User';
$avatar = $_SESSION['avatar'] ?? '';

// Auto create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS presale_prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_key VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    system_prompt TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$success_message = '';
$error_message = '';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $action_key = $_POST['action_key'] ?? '';
        $title = $_POST['title'] ?? '';
        $system_prompt = $_POST['system_prompt'] ?? '';
        
        try {
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE presale_prompts SET action_key = ?, title = ?, system_prompt = ? WHERE id = ?");
                $stmt->bind_param("sssi", $action_key, $title, $system_prompt, $id);
            } else {
                $stmt = $conn->prepare("INSERT INTO presale_prompts (action_key, title, system_prompt) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $action_key, $title, $system_prompt);
            }
            $stmt->execute();
            $success_message = "Lưu prompt thành công!";
        } catch (Exception $e) {
            $error_message = "Lỗi: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        try {
            $stmt = $conn->prepare("DELETE FROM presale_prompts WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $success_message = "Đã xóa prompt!";
        } catch (Exception $e) {
            $error_message = "Lỗi xóa: " . $e->getMessage();
        }
    }
}

// Fetch all prompts
$prompts = [];
$res = $conn->query("SELECT * FROM presale_prompts ORDER BY id ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $prompts[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý System Prompts (Presale)</title>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .settings-container { padding: 2rem; max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; }
        .header-flex h2 { margin: 0; color: #1e293b; font-size: 1.25rem; }
        .btn-primary { background: #4f46e5; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; font-weight: 500; }
        .btn-primary:hover { background: #4338ca; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .table th { font-weight: 600; color: #475569; background: #f8fafc; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; width: 600px; max-width: 90%; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: #334155; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 14px; }
        .form-group textarea { height: 150px; resize: vertical; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
        .btn-cancel { background: #e2e8f0; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; color: #475569; font-weight: 500; }
        .btn-danger { background: #ef4444; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; color: white; font-size: 12px; }
        .btn-edit { background: #3b82f6; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; color: white; font-size: 12px; margin-right: 4px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php
            $page_title = 'Quản lý System Prompts (Sale/Presale)';
            include __DIR__ . '/../../../modules/includes/topbar.php';
            ?>
            <div class="settings-container">
                <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
                <?php if ($error_message): ?><div class="alert alert-error"><?php echo $error_message; ?></div><?php endif; ?>
                
                <div class="card">
                    <div class="header-flex">
                        <h2>Danh sách System Prompts</h2>
                        <button class="btn-primary" onclick="openModal()">+ Thêm Prompt mới</button>
                    </div>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="15%">Action Key</th>
                                <th width="25%">Tên hiển thị (Quick Action)</th>
                                <th width="45%">System Prompt</th>
                                <th width="15%">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($prompts as $p): ?>
                                <tr>
                                    <td><span style="background:#eef2ff; color:#4f46e5; padding:2px 6px; border-radius:4px; font-size:12px; font-family:monospace;"><?php echo htmlspecialchars($p['action_key']); ?></span></td>
                                    <td><?php echo htmlspecialchars($p['title']); ?></td>
                                    <td>
                                        <div style="font-size:12px; color:#64748b; max-height:40px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                                            <?php echo htmlspecialchars($p['system_prompt']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn-edit" onclick="editPrompt(<?php echo htmlspecialchars(json_encode($p)); ?>)">Sửa</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Chắc chắn xóa?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn-danger">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($prompts)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:2rem; color:#94a3b8;">Chưa có Prompt nào được cấu hình.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="promptModal">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0; color:#1e293b; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Thêm Prompt</h3>
            <form method="POST">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="promptId" value="0">
                
                <div class="form-group">
                    <label>Action Key (Ví dụ: qna, create_sow)</label>
                    <input type="text" name="action_key" id="action_key" required>
                </div>
                
                <div class="form-group">
                    <label>Tên hiển thị trên màn hình (Nút Quick Action)</label>
                    <input type="text" name="title" id="title" required placeholder="Ví dụ: Tạo Proposal / SOW">
                </div>
                
                <div class="form-group">
                    <label>System Prompt (Chỉ dẫn hệ thống cho AI)</label>
                    <textarea name="system_prompt" id="system_prompt" required placeholder="Bạn là trợ lý tư vấn công nghệ chuyên nghiệp..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Hủy</button>
                    <button type="submit" class="btn-primary">Lưu Prompt</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('promptId').value = '0';
            document.getElementById('action_key').value = '';
            document.getElementById('title').value = '';
            document.getElementById('system_prompt').value = '';
            document.getElementById('modalTitle').textContent = 'Thêm Prompt mới';
            document.getElementById('promptModal').classList.add('active');
        }
        function editPrompt(data) {
            document.getElementById('promptId').value = data.id;
            document.getElementById('action_key').value = data.action_key;
            document.getElementById('title').value = data.title;
            document.getElementById('system_prompt').value = data.system_prompt;
            document.getElementById('modalTitle').textContent = 'Sửa Prompt';
            document.getElementById('promptModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('promptModal').classList.remove('active');
        }
    </script>
</body>
</html>
