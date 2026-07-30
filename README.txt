WP Site Monitor Manager
Version: 2.2.2

Main features:
- Monitor uptime, HTTP status, SSL, page title, suspicious keywords, REST API, DNS, SLA, and Agent heartbeat.
- Manage website groups, filters, batch assignment, incidents, logs, and realtime dashboard updates.
- Send alerts through email, Telegram, and Zalo with priority cooldown and recovery notifications.
- Communicate with WP Site Monitor Agent through signed per-site requests with replay protection.
- Update through GitHub Releases after the plugin has been installed once.

Agent setup:
- Install WP Site Monitor Agent on each child WordPress site.
- Copy the 64-character Manager connection key from the Agent settings into the matching Manager site configuration.
- Enable SSO only when needed and select exactly one administrator account on the Agent site.
- Production Manager-Agent communication should use HTTPS.

Deployment:
- Push code to GitHub.
- Create and push a version tag such as v2.2.2.
- The GitHub Actions workflow builds wp-site-monitor-manager-{version}.zip and attaches it to the GitHub Release.
- Installed sites can then update directly inside wp-admin without uploading code again.

Removed modules:
- Website backup
- Malware scan
- VPS/server management

Existing legacy database tables or backup files are not automatically deleted during upgrades.

== Changelog ==

= 2.2.2 =
- Trigger automated GitHub Actions release from the default branch.

= 2.2.1 =
- Improve GitHub Releases updater asset selection.
- Add workflow to build and upload WordPress-ready release ZIP files.

= 2.2.0 =
- Add website groups, partial status, multi-probe checks, DNS/SLA monitoring, priority alerts, Agent heartbeat, and batch performance improvements.

= 2.1.1 =
- Improve dashboard and site detail UI.

= 2.1.0 =
- Normalize Vietnamese text and improve admin UI.
