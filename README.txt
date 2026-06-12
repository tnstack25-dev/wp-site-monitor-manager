WP Site Monitor Manager

Tính năng chính:
- Giám sát thời gian hoạt động, trạng thái HTTP, SSL, tiêu đề và từ khóa của website
- Bảng điều khiển thời gian thực với truy vấn REST định kỳ và cập nhật WebSocket tùy chọn
- Biểu đồ thời gian phản hồi và thời gian hoạt động
- Nhật ký giám sát với thiết lập thời gian lưu trữ
- Cảnh báo qua email, Telegram và Zalo
- Cập nhật thông qua GitHub Releases
- Giao tiếp Agent có chữ ký theo từng website và chống phát lại yêu cầu

Cấu hình Agent:
- Sao chép khóa kết nối Manager gồm 64 ký tự từ phần cài đặt WP Site Monitor Agent của từng website con.
- Dán khóa vào cấu hình website tương ứng trong Manager.
- Chỉ bật đăng nhập nhanh SSO trên Agent khi cần thiết và chọn duy nhất một quản trị viên được phép đăng nhập.
- Giao tiếp trong môi trường production yêu cầu HTTPS. Không bật cờ phát triển cục bộ kém an toàn trên môi trường production.

Các mô-đun đã loại bỏ:
- Sao lưu website
- Quét mã độc
- Quản lý VPS/máy chủ

Các bảng cơ sở dữ liệu cũ hoặc tệp sao lưu hiện có không tự động bị xóa khi nâng cấp.
