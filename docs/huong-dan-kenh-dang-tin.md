# Hướng dẫn cấu hình Kênh đăng tin tuyển dụng

Tính năng này cho phép đăng tin tuyển dụng **trực tiếp qua API** của Facebook và LinkedIn (không cần Zapier/Make/n8n), và vẫn hỗ trợ Webhook tùy chỉnh nếu cần.

- **Cấu hình kênh:** `HRM → Cấu hình → Kênh đăng tin` (`/hrm/settings?tab=channels_cfg`) — chỉ admin.
- **Đăng tin:** mở 1 tin tại `HRM → Tin tuyển dụng → [tin]`, bấm **"Đăng lên kênh"**, chọn các kênh rồi bấm **Đăng tin**. Trạng thái đăng (thành công/lỗi, thời gian, link bài) được lưu lại cho từng kênh.

Mỗi kênh có công tắc Bật/Tắt. Chỉ kênh đang **Bật** mới hiện trong hộp thoại đăng tin.

> ⚠️ Access token được lưu và hiển thị dạng plaintext trong trang cấu hình (chỉ admin xem được). Không chia sẻ ảnh chụp màn hình trang này.

---

## 1. Facebook Page

Đăng bài lên Trang (Page) bằng Graph API `POST /{page_id}/feed`. Đây là cách **dễ làm nhất**.

### Cần chuẩn bị
- Một **Facebook Page** (Trang) mà bạn là **Admin**.
- Một **Facebook App** (tạo miễn phí).

### Các bước

1. **Tạo App**
   - Vào https://developers.facebook.com/apps → **Create App**.
   - Chọn loại **Business** → đặt tên app → tạo xong.

2. **Mở Graph API Explorer**
   - Vào https://developers.facebook.com/tools/explorer
   - Ở góc phải chọn **App** vừa tạo.
   - Mục **User or Page** → chọn **Get Page Access Token** → chọn Page của bạn.
   - Ở **Permissions**, thêm: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`.
   - Bấm **Generate Access Token** → đăng nhập & cấp quyền. Bạn sẽ nhận một **token ngắn hạn**.

3. **Lấy Page ID**
   - Trong Graph API Explorer gọi `GET me/accounts` → kết quả liệt kê các Page kèm trường `id` (đó là **Page ID**) và `access_token` (token của riêng Page đó).
   - Hoặc vào Trang → **About / Giới thiệu** → kéo xuống thấy **Page ID**.

4. **Đổi sang token dài hạn (khuyến nghị, ~60 ngày → token Page gần như không hết hạn)**

   a. Đổi token user ngắn hạn → user dài hạn:
   ```
   GET https://graph.facebook.com/v25.0/oauth/access_token
       ?grant_type=fb_exchange_token
       &client_id={APP_ID}
       &client_secret={APP_SECRET}
       &fb_exchange_token={SHORT_LIVED_USER_TOKEN}
   ```
   b. Dùng token user dài hạn gọi lại `GET /me/accounts` → lấy `access_token` của Page. Token Page lấy theo cách này thường **không hết hạn** (miễn là bạn còn là admin và app còn hoạt động).

   *(APP_ID và APP_SECRET xem tại Settings → Basic của app.)*

5. **Điền vào hệ thống** (`Kênh đăng tin → + Thêm kênh`)
   - **Loại kênh:** Facebook Page
   - **Tên kênh:** ví dụ `Facebook ArrowHiTech`
   - **Facebook Page ID:** dán Page ID
   - **Page Access Token:** dán token Page (dài hạn)
   - **Graph API version:** để mặc định `v25.0`
   - Bấm **Thêm kênh**.

### Lưu ý
- Để app post lên Page của chính bạn (bạn là admin), thường **không cần App Review** nếu bạn là Admin/Developer/Tester của app. Nếu muốn nhiều người khác dùng hoặc post lên Page không thuộc quyền quản trị, Facebook yêu cầu **App Review** cho `pages_manage_posts`.
- Bài đăng thành công sẽ có link dạng `https://www.facebook.com/{post_id}`.

---

## 2. LinkedIn (Company Page)

Đăng bài lên Trang công ty bằng Posts API `POST /rest/posts`. **Khó hơn Facebook** vì cần app được LinkedIn duyệt.

### Cần chuẩn bị
- Một **LinkedIn Company Page** mà bạn có vai trò **Admin (Super Admin / Content Admin)**.
- Một **LinkedIn Developer App** liên kết với Company Page đó.
- App được duyệt **Community Management API**.

### Các bước

1. **Tạo App**
   - Vào https://www.linkedin.com/developers/apps → **Create app**.
   - Ở mục **Company**, gắn app với Company Page của bạn (bắt buộc).
   - Xác minh app với Page (LinkedIn gửi link xác minh cho admin Page).

2. **Xin quyền (Products)**
   - Tab **Products** → request **Community Management API**.
   - Cần quyền (scopes): `w_organization_social` (đăng bài) và `r_organization_social` (đọc bài).
   - ⏳ LinkedIn xét duyệt — có thể mất vài ngày và **có thể bị từ chối** nếu chưa đủ điều kiện. Đây là rào cản lớn nhất.

3. **Lấy Access Token (OAuth 2.0 — 3-legged)**
   - Tab **Auth**: lấy **Client ID**, **Client Secret**, và đặt **Redirect URL**.
   - Bước 1 — lấy authorization code (mở trên trình duyệt, đăng nhập admin Page):
     ```
     https://www.linkedin.com/oauth/v2/authorization
        ?response_type=code
        &client_id={CLIENT_ID}
        &redirect_uri={REDIRECT_URI}
        &scope=w_organization_social%20r_organization_social
     ```
   - Bước 2 — đổi code lấy access token:
     ```
     POST https://www.linkedin.com/oauth/v2/accessToken
     Content-Type: application/x-www-form-urlencoded

     grant_type=authorization_code
     &code={CODE}
     &client_id={CLIENT_ID}
     &client_secret={CLIENT_SECRET}
     &redirect_uri={REDIRECT_URI}
     ```
   - Phản hồi chứa `access_token` (thường hạn ~60 ngày, kèm refresh token nếu app được bật refresh).
   - *(Có thể dùng nút "Generate token" trong tab Auth của app để lấy nhanh token thử nghiệm.)*

4. **Lấy Organization ID**
   - Cách nhanh: vào trang quản trị Company Page, URL có dạng `.../company/{ID}/admin/` → `{ID}` chính là Organization ID.
   - Hoặc gọi API:
     ```
     GET https://api.linkedin.com/rest/organizationAcls?q=roleAssignee
     Header: Authorization: Bearer {TOKEN}
             X-Restli-Protocol-Version: 2.0.0
             LinkedIn-Version: 202606
     ```
     → trường `organization` dạng `urn:li:organization:5515715` (số `5515715` là Org ID).

5. **Kết nối trong hệ thống bằng OAuth (khuyến nghị — token tự lấy & tự làm mới)** (`Kênh đăng tin → + Thêm kênh`)
   - **Loại kênh:** LinkedIn (Company Page); **Tên kênh:** ví dụ `LinkedIn ArrowHiTech`.
   - Copy **Redirect URL** hệ thống hiển thị → vào app LinkedIn tab **Auth → Add redirect URL** → dán → **Update**.
   - Nhập **Organization ID** (vd `5515715`), **Client ID**, **Client Secret**, **LinkedIn-Version** (mặc định `202606`) → bấm **Thêm kênh**.
   - Trong danh sách kênh, bấm **Kết nối** → đăng nhập admin Company Page → cấp quyền. Hệ thống tự lưu access token + refresh token và **tự làm mới** khi hết hạn (~2 tháng).
   - *(Tùy chọn)* Không muốn OAuth thì dán **Access Token** thủ công vào ô riêng — nhưng phải tự thay mỗi ~2 tháng.

### Lưu ý
- Bài đăng thành công có link dạng `https://www.linkedin.com/feed/update/urn:li:share:.../`.
- Access token LinkedIn hết hạn theo chu kỳ (~60 ngày) → cần cập nhật lại token khi báo lỗi `401`. Nếu thấy lỗi `403 ACCESS_DENIED`: kiểm tra quyền `w_organization_social` và vai trò admin trên Company Page.

---

## 3. Webhook (tùy chỉnh / dự phòng)

Dùng khi bạn tự xây dịch vụ trung gian hoặc kênh khác. Hệ thống gửi `POST` JSON tới URL bạn cấu hình.

- **Webhook URL:** địa chỉ nhận dữ liệu.
- **Secret (tùy chọn):** nếu điền, sẽ gửi kèm header `X-Webhook-Secret` để bạn xác thực.

### Cấu trúc JSON gửi đi
```json
{
  "id": 123,
  "code": "JOB-2026-0007",
  "title": "Senior PHP Developer",
  "department": "IT",
  "level": "Senior",
  "location": "AHT Head Office",
  "salary": "20,000,000 - 35,000,000 VND",
  "headcount": 2,
  "deadline": "31/07/2026",
  "description": "....",
  "jd_skills": "....",
  "status": "open",
  "apply_url": "https://t.arrowhitech.com/....",
  "channel": "Tên kênh"
}
```
Nếu webhook trả về JSON có một trong các trường `url` / `link` / `permalink` / `post_url`, hệ thống sẽ lưu link đó làm link bài đăng.

---

## Nội dung bài đăng tự sinh

Với Facebook và LinkedIn, hệ thống tự dựng nội dung từ dữ liệu tin:

```
📢 {Tiêu đề} | ArrowHiTech tuyển dụng
📍 {Địa điểm}  💼 {Cấp bậc}
💰 {Mức lương}
⏰ Hạn nộp: {Hạn}

{Mô tả rút gọn ~600 ký tự}

👉 Ứng tuyển: {apply_url}
```

`apply_url` là link tin trên website (nếu tin đã được **Publish lên Website** trước đó). Nên Publish website trước, rồi mới đăng lên Facebook/LinkedIn để bài có link ứng tuyển.

---

## Khắc phục sự cố

| Hiện tượng | Nguyên nhân thường gặp |
|---|---|
| FB: `(#200) ...permission` | Token thiếu `pages_manage_posts`, hoặc dùng token User thay vì token Page |
| FB: `access token does not belong to application` | Token được tạo bởi **app khác** với App ID/Secret bạn nhập. Vào Graph API Explorer → chọn đúng **Meta App** (đúng App ID) → tạo lại Page Access Token rồi đổi token dài hạn lại |
| FB: token hết hạn | Dùng token ngắn hạn — hãy đổi sang token Page dài hạn (mục 1.4) |
| LinkedIn `401` | Access token hết hạn → lấy token mới |
| LinkedIn `403 ACCESS_DENIED` | Thiếu scope `w_organization_social` hoặc không phải admin Company Page |
| LinkedIn `400 INVALID_URN` | Organization ID sai định dạng — dùng số hoặc `urn:li:organization:{id}` |
| Webhook lỗi HTTP ≠ 2xx | Kiểm tra URL và phía nhận |

Lỗi chi tiết của lần đăng gần nhất được lưu và hiển thị trong hộp thoại "Đăng lên kênh" của từng tin.
