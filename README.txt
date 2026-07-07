WP Site Monitor Manager
Phiên bản: 2.2.0

Tính năng chính:
- Giám sát thời gian hoạt động, trạng thái HTTP, SSL, tiêu đề và từ khóa của website
- Trạng thái partial (hoạt động một phần) và kiểm tra song song homepage, REST API, Agent
- Quản lý nhóm website: tạo nhóm, gán website, lọc theo nhóm trên danh sách
- Bảng điều khiển thời gian thực với truy vấn REST định kỳ và cập nhật WebSocket tùy chọn
- Biểu đồ thời gian phản hồi và thời gian hoạt động
- Nhật ký giám sát với thiết lập thời gian lưu trữ
- Giám sát DNS, SLA và cảnh báo theo mức độ P1/P2/P3 với cooldown và thông báo phục hồi
- Nhận heartbeat từ Agent để theo dõi sức khỏe website con
- Cảnh báo qua email, Telegram và Zalo
- Tối ưu kiểm tra hàng loạt: bulk log, prefetch uptime/incident, giới hạn SSL/batch
- Cập nhật thông qua GitHub Releases
- Giao tiếp Agent có chữ ký theo từng website và chống phát lại yêu cầu

Cấu hình Agent:
- Sao chép khóa kết nối Manager gồm 64 ký tự từ phần cài đặt WP Site Monitor Agent của từng website con.
- Dán khóa vào cấu hình website tương ứng trong Manager.
- URL Manager trên Agent có thể tự nhận khi Manager gọi API có chữ ký.
- Chỉ bật đăng nhập nhanh SSO trên Agent khi cần thiết và chọn duy nhất một quản trị viên được phép đăng nhập.
- Giao tiếp trong môi trường production yêu cầu HTTPS. Không bật cờ phát triển cục bộ kém an toàn trên môi trường production.

Các mô-đun đã loại bỏ:
- Sao lưu website
- Quét mã độc
- Quản lý VPS/máy chủ

Các bảng cơ sở dữ liệu cũ hoặc tệp sao lưu hiện có không tự động bị xóa khi nâng cấp.

== Changelog ==

= 2.2.0 =
- Quản lý nhóm website: CRUD nhóm, gán website, lọc theo nhóm, gán hàng loạt
- Trạng thái partial và kiểm tra multi-probe (homepage, /wp-json/, Agent health)
- Giám sát DNS, SLA, cảnh báo P1/P2/P3, cooldown và thông báo phục hồi
- Endpoint heartbeat nhận trạng thái từ Agent
- Tối ưu hiệu suất batch check và schema 1.0.7

= 2.1.1 =
- Cải thiện giao diện bảng điều khiển và chi tiết website

= 2.1.0 =
- Chuẩn hóa văn bản tiếng Việt và cải thiện giao diện quản trị