# 🚀 KHỞI ĐỘNG SERVER - HƯỚNG DẪN NHANH

## ⚡ Các bước thực hiện:

### 1️⃣ Mở Terminal

Mở Terminal và cd vào thư mục dự án:
```bash
cd "/Users/hyuncao/AHT KPI"
```

### 2️⃣ Dừng server cũ (nếu đang chạy)

Nếu server đang chạy ở port 8000, kill nó:
```bash
# Tìm process
lsof -ti:8000

# Kill process (thay 12345 bằng số hiện ra ở trên)
kill -9 12345
```

Hoặc đơn giản hơn:
```bash
kill -9 $(lsof -ti:8000)
```

### 3️⃣ Khởi động server mới

```bash
php -S localhost:8000 router.php
```

Bạn sẽ thấy:
```
[Thu Feb 12 10:42:56 2026] PHP 8.5.0 Development Server (http://localhost:8000) started
```

### 4️⃣ Truy cập ứng dụng

Mở browser và truy cập:
```
http://localhost:8000/login
```
bb2db03e3f9eb996b187a898fb7468d0ad6b6905
**Thông tin đăng nhập:**
- Username: `admin`
- Password: `@admin123`

---

## 🔗 Các URL có sẵn:

- http://localhost:8000/ (Trang chủ)
- http://localhost:8000/login (Đăng nhập)
- http://localhost:8000/dashboard (Dashboard)
- http://localhost:8000/logout (Đăng xuất)

---

## 🐛 Xử lý lỗi thường gặp:

### Lỗi: "Failed to listen on localhost:8000"

**Nguyên nhân:** Port 8000 đang được sử dụng

**Giải pháp:**
```bash
# Kill process cũ
kill -9 $(lsof -ti:8000)

# Hoặc dùng port khác
php -S localhost:8001 router.php
```

### Lỗi: "ERR_TOO_MANY_REDIRECTS"

**Giải pháp:**
1. Xóa cookies trong browser (F12 → Application → Cookies → Clear)
2. Hoặc dùng Incognito mode
3. Restart server

### CSS/JS không load

**Kiểm tra:** Server có đang chạy không?
```bash
# Kiểm tra
lsof -ti:8000

# Nếu không có output → server chưa chạy
# Khởi động lại server
php -S localhost:8000 router.php
```

---

## 💡 Tips:

1. **Giữ Terminal mở:** Đừng đóng terminal đang chạy server
2. **Xem logs:** Mọi request sẽ hiện trong terminal
3. **Stop server:** Nhấn `Ctrl+C` trong terminal
4. **Restart:** Nhấn `Ctrl+C` rồi chạy lại lệnh `php -S localhost:8000 router.php`

---

## 📝 Lệnh nhanh (Copy & Paste):

```bash
# Vào thư mục dự án
cd "/Users/hyuncao/AHT KPI"

# Kill server cũ (nếu có)
kill -9 $(lsof -ti:8000) 2>/dev/null

# Khởi động server
php -S localhost:8000 router.php
```

---

**Chúc bạn thành công! 🎉**
