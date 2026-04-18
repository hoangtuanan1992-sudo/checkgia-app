## CheckGia Shopee Scraper (Chrome Extension)

### Cài đặt

1. Mở Chrome → `chrome://extensions`
2. Bật `Developer mode`
3. Chọn `Load unpacked` → trỏ tới thư mục `chrome-extension-shopee`

### Cấu hình

1. Bấm vào extension → `Options`
2. Nhập:
   - Server URL: `https://checkgia.id.vn` (hoặc URL localhost)
   - Token: lấy trong trang `Shopee` → `Cài đặt` (admin)

### Cách hoạt động

- Extension tự đăng ký `agent_key` và poll API:
  - `POST /api/shopee/agent/heartbeat`
  - `POST /api/shopee/agent/pull`
  - `POST /api/shopee/agent/report`
- Admin dùng trang `Shopee` → `Cài đặt` để bật/tắt, đặt chu kỳ cào, thời gian nghỉ, và gán agent cào cho 1 user hoặc tất cả.

