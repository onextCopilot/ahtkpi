# HRM Module Notes

## Tóm tắt chức năng (Summary of Functions)

### 1. Đăng tin tuyển dụng (`/hrm/job-post-create`)
- **Quy trình 7 bước (Stepper)**: Giao diện hiện đại, chia nhỏ quá trình đăng tin giúp người dùng không bị ngộp thông tin.
- **Bước 1 (Thông tin cơ bản)**: Lưu các thông tin cốt lõi như Tiêu đề, Phòng ban, Văn phòng, Mức lương, Miêu tả công việc (sử dụng TinyMCE), Talent Pool, Thành viên quản lý.
- **Bước 2 (Tiêu chí đánh giá)**: 
    - Lựa chọn tiêu chí từ danh sách hệ thống.
    - Thiết lập **Điểm kỳ vọng (1/5 - 5/5)** và **Trọng số** cho từng tiêu chí để tính điểm ứng viên sau này.
    - Thêm **Yêu cầu bắt buộc (Mandatory)**: Các tiêu chí loại nhanh ứng viên (ví dụ: "Có 2 năm kinh nghiệm").
- **Cơ chế lưu trữ**: Sử dụng AJAX để lưu từng bước. Bước 1 tạo `jobId`, các bước sau dựa vào `jobId` này để lưu dữ liệu liên quan.

### 2. Quản lý Tiêu chí hệ thống (`/hrm/evaluation-criteria`)
- Quản lý danh mục các tiêu chí đánh giá chung của công ty.
- Phân nhóm tiêu chí (Kỹ năng chuyên môn, Kỹ năng mềm, v.v.) để dễ dàng tìm kiếm khi đăng tin.

---

### 3. Tin Tuyển dụng (`/hrm/openings`)
- **Giao diện**: Tương tự Base Hiring, tập trung vào việc quản lý danh sách các tin tuyển dụng hiện có.
- **Tính năng chính**:
    - **Phân loại sở hữu**: "Tôi quản lý", "Tôi đã tạo", "Tất cả tin".
    - **Bộ lọc đa dạng**: Tìm kiếm theo từ khóa/mã job, lọc theo Bộ phận, Văn phòng, Trạng thái (Công khai, Nháp, Đã đóng).
    - **Tabs trạng thái**: Phân nhóm nhanh "Tất cả", "Đang tuyển", "Đã đóng", "Bản nháp" kèm số lượng.
    - **Thống kê nhanh**: Mỗi job card hiển thị các chỉ số quan trọng (Ứng viên, Đã tuyển, Đang xử lý, Phỏng vấn).
- **Technical**:
    - **File**: `modules/hrm/openings.php`
    - **Handler**: `ajax-handler?action=get_jobs`

---

### 4. Chi tiết tin tuyển dụng (`/hrm/job-detail`)
- **Giao diện Pipeline**: Hiển thị quy trình tuyển dụng dưới dạng Kanban Board.
- **Header Thông tin**: Hiển thị các thông số quan trọng (ID, Deadline, Chỉ tiêu, Văn phòng).
- **Tính năng**: 
    - Xem nhanh trạng thái các ứng viên trong từng giai đoạn (Pipeline).
    - Nút **"Thiết lập tin"** (icon bánh răng) để truy cập nhanh vào trang chỉnh sửa cấu hình tin.
- **Technical**:
    - **File**: `modules/hrm/job_detail.php`
    - **Route**: Được cấu hình trong `hrm/index.php`.

### 5. Chỉnh sửa tin tuyển dụng (`/hrm/job-edit`)
- **Giao diện**: Tương tự trang tạo tin nhưng cho phép nhảy nhanh giữa các bước thông qua sidebar trái.
- **Đồng bộ dữ liệu**: Tự động load lại toàn bộ cấu hình đã lưu (Thông tin cơ bản, Tiêu chí đánh giá, v.v.).
- **Tính năng**: Cho phép cập nhật từng phần và lưu thay đổi ngay lập tức.
- **Technical**:
    - **File**: `modules/hrm/job_edit.php`
    - **Route**: Được cấu hình trong `index.php` gốc.

---

### 6. Import dữ liệu Tuyển dụng (Excel/XLSX)
- **Tính năng**: Cho phép import hàng loạt dữ liệu "Tin Tuyển dụng" từ file Excel (`.xlsx`) xuất từ các hệ thống khác (ví dụ: Base Hiring) vào database `hrm_job_posts`.
- **Đặc điểm nổi bật**:
    - Sử dụng script PHP độc lập (`run_import.php`), tận dụng `ZipArchive` và `SimpleXML` để đọc file XLSX nguyên bản mà **không phụ thuộc thư viện thứ 3** (như PhpSpreadsheet hay Python modules).
    - Tự động đọc danh sách phòng ban, nếu chưa có sẽ tự động khởi tạo mới vào `hrm_departments`.
    - Map dữ liệu trạng thái chuẩn (Draft, Public, Closed) và định dạng ngày tháng sang chuẩn SQL.
- **Khắc phục lỗi CLI**: Chạy script trực tiếp qua URL trình duyệt (vd: `http://localhost/AHT%20KPI/run_import.php`) để tận dụng kết nối Database của hệ thống, vượt qua các giới hạn kết nối của macOS Command Line (`Operation not permitted`).

---

## Technical Details

### Routing (`index.php`)
- Thư mục gốc chứa file `index.php` đóng vai trò là **Front Controller** xử lý định tuyến cho toàn bộ hệ thống (đặc biệt khi chạy live trên Nginx/Apache).
- **Khắc phục lỗi 404 (Nginx)**: Để các module mới (như `/hrm/openings`, `/hrm/job-detail`, `/hrm/job-edit`) hoạt động được trên môi trường live (Nginx), bắt buộc phải khai báo chúng vào mảng `$routes` trong file `index.php` ở thư mục gốc.
- Các request sẽ được điều hướng tới các file logic bên trong `modules/hrm/`.

### Module: Job Post Management (`/hrm/openings`)
- **File**: `modules/hrm/openings.php`
- **Logic**: Fetch jobs via AJAX based on filters (ownership, department, office, status).
- **Stats**: Currently using mock statistics for candidates (will be integrated with `hrm_candidates` table in future steps).

### Module: Job Detail (`/hrm/job-detail`)
- **File**: `modules/hrm/job_detail.php`
- **Pipeline**: Sử dụng `action=get_hiring_steps` để render các cột của quy trình tuyển dụng.

### Module: Job Edit (`/hrm/job-edit`)
- **File**: `modules/hrm/job_edit.php`
- **Logic**: Sử dụng `action=get_jobs&id=[id]` để lấy thông tin cơ bản và `action=get_job_criteria` để lấy tiêu chí.

### AJAX Handler (`modules/hrm/ajax_handler.php`)
- **Action `get_jobs`**: Đã được cập nhật để hỗ trợ tham số `id` giúp lấy dữ liệu của một job duy nhất phục vụ trang Edit/Detail.

### Module: Job Post Creation (`/hrm/job-post-create`)
- **File**: `modules/hrm/job_post_create.php`
- **Tables**: `hrm_job_posts`
- **Handling**: `saveStep1()` -> `ajax-handler?action=save_job_post`.

#### Step 1: Recruitment Information (Thông tin tuyển dụng)
- **Fields**: Basic info + SEO fields + Talent Pool + Managers.
- **Update**: Added `created_by` to track the user who created the job post.

#### Step 2: Evaluation Criteria (Tiêu chí)
- **File**: `modules/hrm/job_post_create.php`
- **Tables**: `hrm_job_evaluation_criteria`, `hrm_job_mandatory_requirements`
- **Functions**:
    - `loadEvaluationData()`: Load master list of criteria.
    - `addSelectedCriterion()`: Add one criterion.
    - `addMultiCriteria()`: Modal selection with checkboxes.
    - `addMandatoryRow()`: Screening requirements.
- **Handling**: `saveStep2()` -> `ajax-handler?action=save_job_criteria`.

### Database Schema
- `hrm_job_posts`: Main table. Updated to include `created_by` (INT) and `status` (VARCHAR).
- `hrm_job_evaluation_criteria`: Pivot table linking jobs to criteria (`job_id`, `criterion_id`, `expected_score`, `weight`).
- `hrm_job_mandatory_requirements`: List of quick screening rules for a job.
- `hrm_evaluation_groups` & `hrm_evaluation_criteria`: Master data for evaluation.

### AJAX Handler (`modules/hrm/ajax_handler.php`)
- **Centralized Logic**: All HRM actions go through this handler.
- **Auto-Initialization**: The script automatically checks and creates missing tables and columns (e.g., `ALTER TABLE` to add `created_by` if it doesn't exist).
- **New Actions**:
    - `get_jobs`: Retrieves job openings with filtering and counts for tabs.
    - `save_job_post`: Updated to store `created_by` from `$_SESSION['user_id']`.

