WP Site Monitor Manager v3.0.0

Kiến trúc plugin đã được refactor theo hướng dự án thực tế:
- includes/Services: MonitorService, BackupService, MalwareScannerService, ServerService, NotificationService
- includes/Repositories: SiteRepository, ServerRepository
- includes/Rest: REST API riêng namespace /wp-json/wpsmm/v1
- admin/: AdminController
- templates/admin/: tách giao diện dashboard, sites, logs, backup, malware, servers, settings
- assets/css, assets/js: UI SaaS, dark mode, realtime dashboard, charts

Tính năng chính:
- Dashboard kiểu SaaS
- REST API riêng
- Realtime bằng WebSocket URL tùy chọn, fallback REST polling
- Dark mode
- Biểu đồ realtime response time và uptime
- Monitor online/offline/HTTP/SSL/title/keyword nghi hack
- Logs tự giữ 7 ngày mặc định
- Backup chạy nền, tiến trình realtime, download/xóa local
- Malware scan nâng cao
- Quản lý nhiều VPS/server

Lưu ý:
- WebSocket cần server WS riêng nếu muốn realtime thật. Nếu không khai báo WebSocket URL, plugin tự dùng REST polling 10 giây/lần.
- SSH command thật cần PHP ssh2 extension. Nếu chưa có, plugin vẫn lưu server và test TCP port.
