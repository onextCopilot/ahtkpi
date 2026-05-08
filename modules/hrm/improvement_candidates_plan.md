# Kế hoạch Cải tiến Trang Danh sách Ứng viên (Candidates)
Mục tiêu: Đạt được độ tương đồng 100% với giao diện và tính năng của `hiring.base.vn/candidates`.

## 1. Phân tích hiện trạng & Khoảng cách (Gap Analysis)
Dựa trên so sánh giữa hệ thống hiện tại và Base Hiring:

| Thành phần | Hiện tại | Mục tiêu (Base Hiring) | Ưu tiên |
| :--- | :--- | :--- | :--- |
| **Bố cục Header** | Sidebar trái cố định cho bộ lọc. | Bộ lọc linh hoạt hơn, header thoáng, có bộ đếm CV/Quota. | Cao |
| **Bảng Ứng viên** | Cột đơn giản, text-based. | Cột thông tin liên hệ (icon), Trạng thái kèm thanh tiến trình (1/5). | Cao |
| **Thanh Bulk Action** | Thanh đen đơn giản ở dưới. | Thanh xanh nổi (floating bar) với số lượng và hành động rõ ràng. | Trung bình |
| **Side-over (Drawer)** | 1 cột đơn giản, tabs đơn điệu. | Drawer rộng, phân khu chức năng (Thông tin, Hoạt động, CV Preview). | Rất Cao |
| **Xem hồ sơ (CV)** | Link mở tab mới. | Tích hợp PDF Preview trực tiếp trong Side-over. | Rất Cao |
| **Hoạt động & Ghi chú** | Timeline đơn giản. | Nhật ký chi tiết, phân loại (Email, Call, Comment, Stage change). | Trung bình |

---

## 2. Các bước thực hiện chi tiết

### Bước 1: Nâng cấp Giao diện Danh sách (Table View)
- **Cột Trạng thái (Stage):** Thay thế text đơn thuần bằng tên bước + chỉ số tiến trình (ví dụ: `Phỏng vấn chuyên môn 2/6`) kèm các chấm tròn hoặc thanh progress nhỏ.
- **Cột Liên hệ:** Thêm icon Email, Điện thoại nhanh cạnh thông tin.
- **Header Filters:** Chuyển bộ lọc từ Sidebar vào các dropdown thông minh ở Header hoặc tích hợp lọc ngay tại đầu cột.
- **Styling:** Cập nhật font weight, màu sắc border và hover theo đúng tone màu của Base (#2563eb cho primary).

### Bước 2: Đại tu Side-over (Candidate Detail Drawer)
- **Header Drawer:** Hiển thị Rating (Star), Trạng thái hiện tại, và các nút "Chuyển bước", "Từ chối", "Gửi Email" nổi bật.
- **CV Previewer:** Sử dụng `<iframe>` hoặc thư viện PDF.js để hiển thị CV ngay bên trong Drawer khi click vào ứng viên.
- **Cấu trúc Tab:**
    - **Thông tin hồ sơ:** Hiển thị thông tin từ Form ứng tuyển và các thông tin hệ thống.
    - **Hoạt động (Activity Log):** Xây dựng timeline chuyên nghiệp hơn, có avatar người thực hiện và icon loại hoạt động.
    - **Đánh giá & Test:** Hiển thị kết quả các bài test/phỏng vấn (nếu có).

### Bước 3: Hoàn thiện Tính năng & Luồng xử lý
- **Chuyển bước hàng loạt:** Cải tiến modal chọn bước tiếp theo, cho phép gửi email tự động khi chuyển bước.
- **Tìm kiếm nâng cao:** Thêm lọc theo Người phụ trách (Owner), Nguồn (Source), Nhãn (Tags).
- **Phân quyền:** Chỉ hiển thị các hành động dựa trên quyền của người dùng (Recruiter vs Admin).

---

## 3. Lộ trình Triển khai (Roadmap)

1. **Giai đoạn 1 (Giao diện bảng):** Cập nhật Table layout, Progress stage, và Header filters.
2. **Giai đoạn 2 (Drawer & CV):** Xây dựng Side-over mới với PDF Preview.
3. **Giai đoạn 3 (Hoạt động & Bulk):** Hoàn thiện timeline hoạt động và thanh hành động hàng loạt.
4. **Giai đoạn 4 (Polish):** Micro-interactions, tối ưu mobile và kiểm tra lỗi.

---

**Ghi chú:** Tôi đã sẵn sàng thực hiện từng bước. Bạn vui lòng xem qua kế hoạch này, nếu đồng ý, tôi sẽ bắt đầu với **Giai đoạn 1: Cập nhật Giao diện Bảng và Trạng thái Tiến trình**.
