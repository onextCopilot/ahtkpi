# Khảo sát: Kênh tuyển dụng tại VN có thể auto-post qua API

> Cập nhật: 22/06/2026. Nguồn: hiểu biết thị trường + tài liệu nền tảng (mốc kiến thức ~5/2025).
> ⚠️ Đa số API của job board VN là **partner/enterprise-gated** (chỉ mở cho đối tác/ATS theo hợp đồng), không có cổng đăng ký tự phục vụ và **điều khoản thay đổi thường xuyên**. Hãy xác minh lại bằng cách liên hệ bộ phận Sales/Partnership của từng bên trước khi đầu tư tích hợp.

## TL;DR — Khuyến nghị thực tế

Phân loại theo khả năng một agency/DN nhỏ **thực sự** tích hợp auto-post:

| Mức độ | Kênh | Ghi chú |
|---|---|---|
| ✅ Làm được ngay / dễ | **Facebook Page** (Graph API), **Google for Jobs** (structured data), **Telegram** (Bot API) | Miễn phí, tự phục vụ |
| 🟡 Làm được nhưng cần duyệt | **LinkedIn** (Posts API), **Zalo OA** (Open API) | Miễn phí nhưng phải được duyệt app/OA |
| 🟠 Phải là đối tác (liên hệ Sales) | **VietnamWorks, TopCV, CareerViet, TopDev, JobsGO, Việc Làm Tốt** | API có nhưng không tự phục vụ; thường kèm gói dịch vụ trả phí |
| 🔴 Khó / không khả dụng tự phục vụ | **ITviec, Indeed (organic free đã siết), JobStreet/SEEK** | Chủ yếu qua dashboard hoặc đối tác lớn |

**Chiến lược gợi ý:** thay vì chờ API từng job board, dùng mô hình **"đăng owned + syndication bằng feed"**:
1. Tự động đăng lên kênh **social/owned** mình kiểm soát: Facebook (đã có), LinkedIn (đang xin), Zalo OA, Telegram.
2. Đảm bảo **Google for Jobs** index tin qua structured data trên website career (t.arrowhitech.com) — gần như "miễn phí mà phủ rộng nhất".
3. Với job board lớn: xuất **XML/JSON job feed** từ hệ thống để các bên (hoặc ATS trung gian) pull về — đây là cách tích hợp phổ biến nhất thay cho "API đẩy".

---

## Chi tiết từng kênh

### Nhóm ✅ — Tự phục vụ, miễn phí

**Facebook Page — Graph API** *(đã tích hợp trong hệ thống)*
- `POST /{page_id}/feed`, cần `pages_manage_posts`. Miễn phí. Đăng lên Page tuyển dụng của công ty.
- Độ tin cậy: cao (đã làm được).

**Google for Jobs — Structured Data (không phải API đẩy)**
- Không có "API đăng tin". Cách hoạt động: gắn dữ liệu có cấu trúc **schema.org `JobPosting`** (JSON-LD) vào trang chi tiết tin trên website career → Google tự crawl và hiển thị trong Google for Jobs / Google Search.
- Chi phí: miễn phí. Phủ rộng nhất với người tìm việc qua Google.
- Hành động: kiểm tra website (WordPress t.arrowhitech.com) đã xuất `JobPosting` JSON-LD chưa; nhiều plugin tuyển dụng WP hỗ trợ sẵn. Đăng ký Google Search Console để theo dõi index.
- Độ tin cậy: cao (cơ chế ổn định, chuẩn công khai).

**Telegram — Bot API**
- Tạo bot (BotFather) → `sendMessage` vào channel/group tuyển dụng. Miễn phí, không cần duyệt.
- Phù hợp làm kênh cộng đồng/nội bộ, không phải job board chính thống.
- Độ tin cậy: cao.

### Nhóm 🟡 — Miễn phí nhưng cần duyệt

**LinkedIn — Posts API** *(đang trong quá trình xin duyệt)*
- `POST /rest/posts` với `w_organization_social`. Cần app được duyệt **Community Management API** và app đó **chỉ chứa duy nhất** product này.
- Chi phí: miễn phí. Rào cản: quy trình duyệt.

**Zalo Official Account — Zalo Open API**
- Zalo OA cho phép gọi API để **gửi broadcast / tạo bài viết** tới người quan tâm OA. Cần tạo **Official Account** (loại doanh nghiệp), tạo app trên developers.zalo.me, lấy access token (OAuth, token refresh định kỳ).
- Lưu ý: chính sách & endpoint của Zalo OA thay đổi khá thường xuyên; một số tính năng broadcast bị giới hạn theo loại OA và có thể tính phí ZNS cho tin nhắn. **Cần xác minh lại tài liệu hiện hành** tại developers.zalo.me.
- Chi phí: tạo OA & API cơ bản miễn phí; một số loại tin nhắn có phí.
- Độ tin cậy: trung bình (API tồn tại nhưng chi tiết cần verify).

### Nhóm 🟠 — Phải là đối tác (liên hệ Sales/Partnership)

Các nền tảng dưới đây **có** năng lực tích hợp API/feed nhưng **không mở tự phục vụ**; thường đi kèm hợp đồng và gói đăng tin trả phí. Cách tiếp cận: liên hệ Sales, hỏi về "API đăng tin / job feed / tích hợp ATS".

- **VietnamWorks (Navigos Group)** — job board lớn, có tích hợp cho khách enterprise/ATS. Không có API công khai tự phục vụ.
- **TopCV** — nền tảng tuyển dụng lớn nhất VN hiện nay; có "TopCV Business" + ATS "TopCV Hiring"; tích hợp API cho đối tác/enterprise. Hỏi về multiposting/API.
- **CareerViet (CareerBuilder.vn)** — thuộc hệ CareerBuilder toàn cầu; lịch sử có hỗ trợ **XML job feed / job posting API** cho đối tác → có thể là một trong những bên dễ làm việc nhất về feed.
- **TopDev** — chuyên IT; làm việc theo gói enterprise/đối tác.
- **JobsGO** — quy mô nhỏ hơn, có quảng bá tính năng tích hợp/ATS; có thể cởi mở hơn với agency. Đáng hỏi thử.
- **Việc Làm Tốt (Chợ Tốt)** — mảng việc làm của Chợ Tốt; tích hợp qua đối tác.
- **Vieclam24h / nhóm Siêu Việt (TimViecNhanh, Mywork)** — tích hợp feed/đối tác.

### Nhóm 🔴 — Khó hoặc không khả dụng tự phục vụ

- **ITviec** — chủ yếu qua dashboard nhà tuyển dụng; không có API đăng tin công khai.
- **Indeed** — trước đây cho đăng organic miễn phí + XML feed cho ATS; gần đây **siết mạnh**: ưu tiên tin trả phí/sponsored, Publisher API đã ngừng, đăng tin qua đối tác ATS/Indeed Apply. Với agency nhỏ thường phải trả phí. **Verify hiện trạng.**
- **JobStreet / JobsDB (SEEK)** — có **SEEK API** cho đối tác; mức độ hoạt động tại VN giảm. Khó tự phục vụ.
- **Glints** — nền tảng SEA; chủ yếu employer dashboard.

---

## Đề xuất triển khai cho hệ thống HRM hiện tại

Hệ thống đã có cơ chế "kênh đăng tin" linh hoạt (loại: facebook / linkedin / webhook). Đề xuất mở rộng theo thứ tự ưu tiên (công/chi phí thấp → cao):

1. **Google for Jobs**: đảm bảo trang tin trên website xuất JSON-LD `JobPosting` (không cần thêm "kênh" trong hệ thống — chỉ cần website đúng chuẩn). Tác động lớn, chi phí ~0.
2. **Zalo OA**: thêm 1 loại kênh `zalo` (gọi Zalo Open API). Cần verify tài liệu hiện hành.
3. **Telegram**: thêm loại kênh `telegram` (Bot API) — rất nhanh, hữu ích cho cộng đồng.
4. **Job feed XML/JSON**: bổ sung 1 endpoint xuất feed tất cả tin đang mở (chuẩn để các job board/đối tác pull). Đây là "chìa khóa" để làm việc với VietnamWorks/TopCV/CareerViet mà không phụ thuộc API đẩy của họ.
5. **Job board lớn**: liên hệ Sales TopCV / VietnamWorks / CareerViet hỏi về job feed hoặc API đối tác; nếu có, dùng loại kênh `webhook` hoặc viết adapter riêng.

---

## Việc cần bạn xác minh (vì API đối tác thay đổi & nguồn tự phục vụ không truy cập được lúc khảo sát)

- Liên hệ Sales **TopCV, VietnamWorks, CareerViet** hỏi rõ: có job feed/API đăng tin cho đối tác không, điều kiện và chi phí.
- Kiểm tra tài liệu **Zalo OA** hiện hành (developers.zalo.me) về quyền đăng bài/broadcast và giới hạn theo loại OA.
- Kiểm tra hiện trạng **Indeed** cho thị trường VN (free vs paid, có còn nhận XML feed không).
- Kiểm tra website career đã có structured data `JobPosting` cho **Google for Jobs** chưa.
