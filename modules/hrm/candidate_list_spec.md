# Phân tích Chi tiết Chức năng: Danh sách ứng viên (Candidate List)

Dựa trên nghiên cứu từ `hiring.base.vn/candidates`, dưới đây là bản ghi chú chi tiết các thành phần và chức năng cần xây dựng.

## 1. Giao diện Tổng thể (Layout)
*   **Header (Thanh tiêu đề):**
    *   **Tabs:** Chuyển đổi giữa "TẤT CẢ" và "CHIA SẺ VỚI TÔI".
    *   **Bộ đếm CV:** Hiển thị số lượng CV đã sử dụng/hạn mức (nếu có).
    *   **Chế độ xem:** Nút chuyển đổi giữa dạng Danh sách (List) và Dạng lưới (Grid).
    *   **Tìm kiếm nhanh:** Ô nhập "Tìm kiếm ứng viên" (Họ tên, Email, SĐT).
    *   **Lọc theo Tin tuyển dụng:** Dropdown chọn tin đang mở (Active) hoặc đã đóng (Closed).
    *   **Nút Xuất dữ liệu (Export):** Cho phép xuất danh sách ra Excel.
*   **Main Content (Nội dung chính):**
    *   Bảng danh sách ứng viên có khả năng cuộn ngang nếu nhiều cột.
    *   Phân trang (Pagination) ở dưới cùng.
*   **Sidebar (Tùy chọn):** Bộ lọc nâng cao (Job, Stage, Source, Owner, Rating, Tags).

## 2. Các trường dữ liệu hiển thị (Table Columns)
Bảng cần hiển thị các thông tin sau (kèm bộ lọc/sắp xếp tại đầu cột):
1.  **Họ và tên:** Kèm Avatar, vị trí ứng tuyển.
2.  **Thông tin liên hệ:** Email, Số điện thoại.
3.  **Tin tuyển dụng:** Tên tin tuyển dụng mà ứng viên ứng tuyển.
4.  **Trạng thái hiện tại (Stage):** Hiển thị dưới dạng thanh tiến trình (Progress Bar), ví dụ: "Phỏng vấn (2/5)".
5.  **Nguồn ứng viên (Source):** Website, Facebook, LinkedIn, Recruiter...
6.  **Người phụ trách (Owner):** Nhân viên tuyển dụng quản lý ứng viên này.
7.  **Đánh giá (Rating):** Hiển thị sao (0-5 stars).
8.  **Nhãn (Tags):** Các từ khóa phân loại ứng viên.
9.  **Thời gian:** Ngày ứng tuyển hoặc ngày cập nhật cuối.
10. **Trạng thái:** (Đang xử lý, Đã từ chối - kèm lý do, Đã nhận việc).

## 3. Bộ lọc & Tìm kiếm (Filters & Search)
*   **Tìm kiếm toàn văn:** Tìm theo tên, email, sđt.
*   **Lọc tại cột (Column-level filter):** Mỗi cột trong bảng có một icon lọc (dropdown hoặc input).
*   **Lọc nâng cao:**
    *   Theo Tin tuyển dụng (Job Post).
    *   Theo Bước tuyển dụng (Stage).
    *   Theo Nguồn (Source).
    *   Theo Chiến dịch (Campaign).
    *   Theo Người phụ trách (Owner).
    *   Theo Xếp hạng (Rating).

## 4. Hành động hàng loạt (Bulk Actions)
Khi chọn một hoặc nhiều ứng viên (checkbox), một thanh công cụ (Action Bar) sẽ xuất hiện phía dưới:
*   **Chuyển trạng thái (Move Stage):** Chuyển hàng loạt ứng viên sang bước tiếp theo.
*   **Gửi Email:** Gửi thông báo hàng loạt theo mẫu.
*   **Từ chối (Reject):** Loại hàng loạt ứng viên (yêu cầu chọn lý do loại).
*   **Thêm vào Talent Pool:** Lưu trữ cho các nhu cầu tương lai.
*   **Đổi người phụ trách (Set Owner):** Giao ứng viên cho nhân viên khác.
*   **Xóa/In CV.**

## 5. Chi tiết ứng viên (Quick View/Detail)
Khi click vào tên ứng viên, hiển thị một Side-over (hoặc Modal) chứa:
*   **Hồ sơ ứng viên:** File CV (PDF preview), thông tin cá nhân.
*   **Các Tab nội dung:**
    *   **Đơn ứng tuyển:** Thông tin từ form ứng tuyển.
    *   **Kiểm tra - Phỏng vấn:** Lịch hẹn, kết quả test.
    *   **Đề xuất tuyển dụng:** Thông tin offer.
    *   **Lịch sử & Hoạt động:** Nhật ký tương tác của hệ thống và nhân viên.
*   **Khu vực tương tác:**
    *   Gửi Email trực tiếp.
    *   Lên lịch phỏng vấn.
    *   Thảo luận nội bộ (Comment).
    *   Ghi chú cuộc gọi (Call log).
*   **Nút thao tác nhanh:** "Loại" (Reject) và "Chuyển tiếp" (Next Stage).

---

# Kế hoạch thực hiện (Implementation Plan)

Tôi sẽ chia việc xây dựng thành 8 phần nhỏ. Xong mỗi phần tôi sẽ tạm dừng để bạn kiểm tra.

*   **Phần 1:** Thiết kế Cấu trúc Database (Bảng candidates, applications, logs, rejections).
*   **Phần 2:** Xây dựng API Backend (Lấy danh sách, Tìm kiếm, Lọc, Phân trang).
*   **Phần 3:** Xây dựng UI Layout & Header (Tabs, Search, Nút Export).
*   **Phần 4:** Xây dựng Bảng danh sách ứng viên (Table View với đầy đủ các cột và Progress Bar).
*   **Phần 5:** Tích hợp Bộ lọc nâng cao & Sắp xếp tại cột.
*   **Phần 6:** Xây dựng tính năng Chọn hàng loạt & Thanh Bulk Actions.
*   **Phần 7:** Xây dựng Giao diện Chi tiết ứng viên (Side-over) & Xem CV.
*   **Phần 8:** Hoàn thiện các hành động (Chuyển bước, Từ chối, Gửi Email, Comment).

**Yêu cầu:** Bạn vui lòng kiểm tra ghi chú này. Nếu đã đầy đủ và chính xác, hãy xác nhận để tôi bắt đầu **Phần 1: Thiết kế Cấu trúc Database**.
