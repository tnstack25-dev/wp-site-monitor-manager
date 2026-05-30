WP Site Monitor Manager

Main features:
- Website uptime, HTTP status, SSL, title, and keyword monitoring
- Realtime dashboard with REST polling and optional WebSocket updates
- Response time and uptime charts
- Monitoring logs with retention settings
- Email, Telegram, and Zalo alerts
- GitHub Releases updater
- Per-site signed Agent communication with replay protection

Agent setup:
- Copy the 64-character Manager connection key from each child site's WP Site Monitor Agent settings.
- Paste it into the matching website configuration in the manager.
- Enable one-click SSO on the child agent only when needed and select the single allowed administrator.
- Production communication requires HTTPS. Do not enable insecure local-development flags in production.

Removed modules:
- Website backup
- Malware scan
- VPS/server management

Existing legacy database tables or backup files are not deleted automatically during upgrades.
