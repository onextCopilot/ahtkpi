-- Tạo database
CREATE DATABASE IF NOT EXISTS login_system;
USE login_system;

-- Tạo bảng users
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm user admin mặc định
-- Password: @admin123
INSERT INTO users (username, password, full_name, email, role) 
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Administrator',
    'admin@system.com',
    'admin'
) ON DUPLICATE KEY UPDATE username=username;

-- Thêm một số user demo (optional)
INSERT INTO users (username, password, full_name, email, role) 
VALUES 
    ('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn A', 'user1@example.com', 'user'),
    ('user2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trần Thị B', 'user2@example.com', 'user'),
    ('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lê Văn C', 'manager@example.com', 'manager')
ON DUPLICATE KEY UPDATE username=username;

-- Hiển thị kết quả
SELECT 'Database và bảng đã được tạo thành công!' AS Status;
SELECT COUNT(*) AS 'Tổng số users' FROM users;
SELECT username, full_name, email, role, created_at FROM users;
