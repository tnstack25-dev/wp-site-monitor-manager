(function () {
  "use strict";
  const qs = (selector, root = document) => root.querySelector(selector);
  const api = (path, options = {}) => fetch(WPSMM.rest + path, Object.assign({ headers: { "X-WP-Nonce": WPSMM.nonce, "Content-Type": "application/json" } }, options)).then((response) => response.json());
  if (WPSMM.darkMode) document.body.classList.add("wpsmm-dark");

  document.addEventListener("click", function (event) {
    const eye = event.target.closest(".wpsmm-eye");
    if (eye) {
      const input = eye.closest(".wpsmm-secret").querySelector("input");
      input.type = input.type === "password" ? "text" : "password";
    }
    const dark = event.target.closest(".wpsmm-toggle-switch");
    if (dark) saveDarkMode(!dark.classList.contains("is-on"));
    const theme = event.target.closest(".wpsmm-theme-choice");
    if (theme) saveDarkMode(theme.dataset.theme === "dark");
    const settingsTab = event.target.closest(".wpsmm-settings-tab");
    if (settingsTab) scrollToSettings(settingsTab);
    const extensionTab = event.target.closest("[data-extension-tab]");
    if (extensionTab) switchExtensionTab(extensionTab);
    const check = event.target.closest(".wpsmm-check-site");
    if (check) checkSite(check);
    const openLog = event.target.closest(".wpsmm-open-log");
    if (openLog) openLogDetail(openLog);
    if (event.target.closest("[data-close-log]")) closeLogDetail();
  });

  function saveDarkMode(enabled) {
    const field = qs("#wpsmm-dark-mode-field");
    document.querySelectorAll(".wpsmm-toggle-switch").forEach((button) => button.classList.toggle("is-on", enabled));
    document.querySelectorAll(".wpsmm-theme-choice").forEach((button) => button.classList.toggle("is-active", button.dataset.theme === (enabled ? "dark" : "light")));
    document.body.classList.toggle("wpsmm-dark", enabled);
    if (field) field.value = enabled ? "1" : "0";
    api("/settings/dark-mode", { method: "POST", body: JSON.stringify({ enabled }) });
  }

  function scrollToSettings(button) {
    const target = document.getElementById(button.dataset.settingsTarget);
    if (!target) return;
    document.querySelectorAll(".wpsmm-settings-tab").forEach((tab) => tab.classList.toggle("is-active", tab === button));
    target.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function switchExtensionTab(button) {
    document.querySelectorAll("[data-extension-tab]").forEach((tab) => tab.classList.toggle("is-active", tab === button));
    document.querySelectorAll("[data-extension-panel]").forEach((panel) => { panel.hidden = panel.dataset.extensionPanel !== button.dataset.extensionTab; });
  }

  const state = { sites: [], filter: "all", search: "", page: 1, perPage: 7, chart: null };
  const good = ["online", "redirect"];
  const warning = ["client_error", "not_found", "title_changed", "suspicious", "ssl_expiring"];
  const bad = ["offline", "server_error", "ssl_error"];
  const esc = (value) => String(value || "").replace(/[&<>"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[char]);
  const isIn = (status, group) => group.includes(status);
  const percent = (value) => `${Number(value || 0).toFixed(2)}%`;
  const response = (value) => value ? `${Math.round(Number(value) * 1000)} ms` : "-";
  const label = (status) => ({ online: "Đang hoạt động", redirect: "Chuyển hướng", offline: "Ngừng hoạt động", server_error: "Lỗi máy chủ", ssl_error: "Lỗi SSL", ssl_expiring: "SSL sắp hết hạn", not_found: "Không tìm thấy", client_error: "Lỗi truy cập", title_changed: "Tiêu đề thay đổi", suspicious: "Nội dung đáng ngờ", unknown: "Chưa kiểm tra" })[status] || status;
  const tone = (status) => isIn(status, good) ? "success" : isIn(status, bad) ? "danger" : status === "unknown" ? "muted" : "warning";
  const icon = (status) => isIn(status, good) ? "yes-alt" : isIn(status, bad) ? "dismiss" : "warning";

  function renderKpis(stats) {
    const sites = state.sites;
    const avgUptime = sites.length ? sites.reduce((sum, site) => sum + Number(site.uptime_percent || 0), 0) / sites.length : 0;
    const items = [
      ["admin-site-alt3", "green", "Tổng website", stats.total, "Tất cả website đang quản lý"],
      ["yes-alt", "green", "Đang hoạt động", stats.online, `${ratio(stats.online, stats.total)} tổng website`],
      ["warning", "orange", "Gặp sự cố", stats.warning, `${ratio(stats.warning, stats.total)} cần kiểm tra`],
      ["dismiss", "red", "Ngừng hoạt động", stats.offline, `${ratio(stats.offline, stats.total)} cần xử lý ngay`],
      ["performance", "blue", "Uptime trung bình", percent(avgUptime), "Tổng hợp từ lịch sử giám sát"],
      ["clock", "purple", "Phản hồi trung bình", response(stats.avgResponse), "Thời gian tải trang"],
    ];
    qs("#wpsmm-kpis").innerHTML = items.map(([ico, color, title, value, note]) => `<article class="wpsmm-summary-card"><span class="wpsmm-summary-icon ${color} dashicons dashicons-${ico}"></span><div><small>${title}</small><strong>${value}</strong><em>${note}</em></div></article>`).join("");
  }

  function ratio(value, total) {
    return total ? `${((Number(value) / Number(total)) * 100).toFixed(1)}%` : "0%";
  }

  function renderDistribution(stats) {
    const total = Math.max(1, Number(stats.total || 0));
    const online = Number(stats.online || 0);
    const warn = Number(stats.warning || 0);
    const offline = Number(stats.offline || 0);
    const a = (online / total) * 360;
    const b = a + (warn / total) * 360;
    const donut = qs("#wpsmm-status-donut");
    donut.style.background = `conic-gradient(#10b981 0 ${a}deg,#f59e0b ${a}deg ${b}deg,#ef4444 ${b}deg 360deg)`;
    donut.innerHTML = `<div><strong>${stats.total}</strong><span>Tổng</span></div>`;
    qs("#wpsmm-status-legend").innerHTML = [["#10b981", "Đang hoạt động", online], ["#f59e0b", "Gặp sự cố", warn], ["#ef4444", "Ngừng hoạt động", offline]].map(([color, text, value]) => `<p><i style="background:${color}"></i><span>${text}<small>${value} (${ratio(value, stats.total)})</small></span></p>`).join("");
  }

  function renderIncidents(logs) {
    const incidents = logs.filter((log) => !isIn(log.status, good)).slice(0, 4);
    qs("#wpsmm-incidents").innerHTML = incidents.length ? incidents.map((log) => `<article class="wpsmm-incident"><span class="dashicons dashicons-${icon(log.status)} ${tone(log.status)}"></span><div><strong>${esc(log.site_name || "#" + log.site_id)}</strong><small>${esc(log.message || label(log.status))}</small></div><time>${esc(log.checked_at)}</time></article>`).join("") : `<div class="wpsmm-empty-state">Không có sự cố mới.</div>`;
  }

  function renderTabs() {
    const counts = { all: state.sites.length, online: state.sites.filter((site) => isIn(site.status, good)).length, warning: state.sites.filter((site) => isIn(site.status, warning)).length, offline: state.sites.filter((site) => isIn(site.status, bad)).length };
    qs("#wpsmm-site-tabs").innerHTML = [["all", "Tất cả"], ["online", "Đang hoạt động"], ["warning", "Gặp sự cố"], ["offline", "Ngừng hoạt động"]].map(([key, text]) => `<button type="button" class="${state.filter === key ? "is-active" : ""}" data-filter="${key}">${text} <b>${counts[key]}</b></button>`).join("");
    qs("#wpsmm-site-tabs").onclick = (event) => {
      const button = event.target.closest("button");
      if (!button) return;
      state.filter = button.dataset.filter;
      state.page = 1;
      renderTabs();
      renderSites();
    };
  }

  function filteredSites() {
    return state.sites.filter((site) => {
      const matchesSearch = `${site.name} ${site.url}`.toLowerCase().includes(state.search);
      const matchesFilter = state.filter === "all" || (state.filter === "online" && isIn(site.status, good)) || (state.filter === "warning" && isIn(site.status, warning)) || (state.filter === "offline" && isIn(site.status, bad));
      return matchesSearch && matchesFilter;
    });
  }

  function renderSites() {
    const rows = filteredSites();
    const pages = Math.max(1, Math.ceil(rows.length / state.perPage));
    state.page = Math.min(state.page, pages);
    const visible = rows.slice((state.page - 1) * state.perPage, state.page * state.perPage);
    qs("#wpsmm-sites-table tbody").innerHTML = visible.length ? visible.map((site) => `<tr><td><div class="wpsmm-site-cell"><span class="dashicons dashicons-admin-site-alt3"></span><div><strong><a href="${WPSMM.adminSites}&action=view&id=${site.id}">${esc(site.name)}</a></strong><small>${esc(site.url)}</small></div></div></td><td><span class="wpsmm-status-pill ${tone(site.status)}"><i></i>${label(site.status)}</span></td><td><strong>${percent(site.uptime_percent)}</strong></td><td><strong class="${Number(site.response_time) > 2 ? "wpsmm-text-danger" : "wpsmm-text-success"}">${response(site.response_time)}</strong></td><td>${ssl(site)}</td><td><small>${esc(site.last_checked || "Chưa kiểm tra")}</small></td><td><div class="wpsmm-row-actions"><a class="wpsmm-icon-button" href="${WPSMM.adminSites}&action=view&id=${site.id}" title="Xem chi tiết"><span class="dashicons dashicons-visibility"></span></a><button class="wpsmm-icon-button wpsmm-check-site" data-id="${site.id}" title="Kiểm tra ngay"><span class="dashicons dashicons-update"></span></button><a class="wpsmm-icon-button" href="${WPSMM.adminSites}&id=${site.id}" title="Sửa website"><span class="dashicons dashicons-edit"></span></a><a class="wpsmm-icon-button" href="${WPSMM.adminLogs}&site_id=${site.id}" title="Xem nhật ký"><span class="dashicons dashicons-chart-line"></span></a></div></td></tr>`).join("") : `<tr><td colspan="7"><div class="wpsmm-empty-state">Không tìm thấy website phù hợp.</div></td></tr>`;
    qs("#wpsmm-table-count").textContent = `Hien thi ${visible.length} / ${rows.length} website`;
    qs("#wpsmm-site-pagination").innerHTML = Array.from({ length: pages }, (_, index) => `<button type="button" class="${state.page === index + 1 ? "is-active" : ""}" data-page="${index + 1}">${index + 1}</button>`).join("");
    qs("#wpsmm-site-pagination").onclick = (event) => { const button = event.target.closest("button"); if (button) { state.page = Number(button.dataset.page); renderSites(); } };
  }

  function ssl(site) {
    if (site.ssl_days_left === null || site.ssl_days_left === undefined || site.ssl_days_left === "") return `<span class="wpsmm-ssl muted"><span class="dashicons dashicons-lock"></span>Chưa có dữ liệu</span>`;
    const days = Number(site.ssl_days_left);
    return `<span class="wpsmm-ssl ${days <= 14 ? "danger" : "success"}"><span class="dashicons dashicons-lock"></span>${days < 0 ? "Hết hạn" : `Còn ${days} ngày`}</span>`;
  }

  function renderChart(data) {
    const canvas = qs("#wpsmm-uptime-chart");
    if (!canvas) return;
    if (state.chart) state.chart.destroy();
    state.chart = new Chart(canvas, { type: "line", data: { labels: data.map((item) => item.label), datasets: [{ data: data.map((item) => item.uptime), borderColor: "#10b981", backgroundColor: "rgba(16,185,129,.13)", fill: true, pointRadius: 3, pointBackgroundColor: "#10b981", tension: 0.35 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100, ticks: { callback: (value) => `${value}%` }, grid: { color: "#edf2f7" } }, x: { grid: { display: false } } } } });
  }

  function checkSite(button) {
    button.disabled = true;
    button.classList.add("is-loading");
    api(`/check/${button.dataset.id}`, { method: "POST", body: "{}" }).finally(() => { button.disabled = false; button.classList.remove("is-loading"); refreshDashboard(); });
  }

  function refreshDashboard() {
    if (!qs('[data-wpsmm-page="dashboard"]')) return;
    const hours = qs("#wpsmm-chart-range").value;
    Promise.all([api("/stats"), api("/sites"), api(`/chart?hours=${hours}`), api("/logs?per_page=20")]).then(([stats, sites, chart, logs]) => {
      state.sites = sites;
      renderKpis(stats);
      renderDistribution(stats);
      renderIncidents(logs);
      renderTabs();
      renderSites();
      renderChart(chart);
    });
  }

  if (qs('[data-wpsmm-page="dashboard"]')) {
    qs("#wpsmm-chart-range").addEventListener("change", refreshDashboard);
    qs("#wpsmm-site-search").addEventListener("input", (event) => { state.search = event.target.value.toLowerCase(); state.page = 1; renderSites(); });
    refreshDashboard();
    setInterval(refreshDashboard, WPSMM.pollInterval || 10000);
  }

  function setupManageSites() {
    const table = qs("#wpsmm-manage-table");
    if (!table) return;
    const rows = Array.from(table.querySelectorAll("[data-manage-row]"));
    const search = qs("#wpsmm-manage-search");
    const tabs = qs("#wpsmm-manage-tabs");
    let filter = "all";
    const apply = () => {
      const query = (search.value || "").trim().toLowerCase();
      let visible = 0;
      rows.forEach((row) => {
        const match = (!query || row.dataset.search.includes(query)) && (filter === "all" || row.dataset.statusGroup === filter);
        row.hidden = !match;
        if (match) visible++;
      });
      qs("#wpsmm-manage-count").textContent = `${visible} website`;
    };
    search.addEventListener("input", apply);
    tabs.addEventListener("click", (event) => {
      const button = event.target.closest("[data-manage-filter]");
      if (!button) return;
      filter = button.dataset.manageFilter;
      tabs.querySelectorAll("button").forEach((item) => item.classList.toggle("is-active", item === button));
      apply();
    });
  }

  function setupSiteEditor() {
    const editor = qs("#wpsmm-site-editor");
    if (!editor) return;
    const modes = Array.from(editor.querySelectorAll("[data-monitor-mode]"));
    const advanced = qs("#wpsmm-advanced-fields");
    const setMode = (mode) => {
      modes.forEach((button) => button.classList.toggle("is-active", button.dataset.monitorMode === mode));
      advanced.classList.toggle("is-open", mode === "advanced");
      qs("#wpsmm-summary-mode").textContent = mode === "advanced" ? "Tùy chỉnh HTTP và tiêu đề" : "Giám sát cơ bản";
    };
    modes.forEach((button) => button.addEventListener("click", () => setMode(button.dataset.monitorMode)));
    [["#wpsmm-site-name", "#wpsmm-summary-name", "Chưa nhập"], ["#wpsmm-site-url", "#wpsmm-summary-url", "Chưa nhập"], ["#wpsmm-site-group", "#wpsmm-summary-group", "Tất cả website"]].forEach(([inputSelector, outputSelector, fallback]) => {
      const input = qs(inputSelector);
      const output = qs(outputSelector);
      input.addEventListener("input", () => { output.textContent = input.value.trim() || fallback; });
    });
    if (advanced.querySelector('input[name="expected_title"]').value || advanced.querySelector('input[name="expected_status"]').value !== "200") setMode("advanced");
  }

  setupManageSites();
  setupSiteEditor();

  function openLogDetail(button) {
    const modal = qs("#wpsmm-log-modal");
    if (!modal) return;
    let data = {};
    try { data = JSON.parse(button.dataset.log || "{}"); } catch (error) {}
    const labels = { time: "Thời gian", site: "Website", url: "URL", status: "Trạng thái", http: "HTTP", response: "Thời gian phản hồi", message: "Thông điệp" };
    qs("#wpsmm-log-detail").innerHTML = Object.keys(labels).map((key) => `<div><dt>${labels[key]}</dt><dd>${esc(data[key] || "-")}</dd></div>`).join("");
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeLogDetail() {
    const modal = qs("#wpsmm-log-modal");
    if (!modal) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
  }
})();
