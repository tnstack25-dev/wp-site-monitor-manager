(function () {
  "use strict";
  const qs = (s, r = document) => r.querySelector(s),
    qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
  const api = (path, opt = {}) =>
    fetch(
      WPSMM.rest + path,
      Object.assign(
        {
          headers: {
            "X-WP-Nonce": WPSMM.nonce,
            "Content-Type": "application/json",
          },
        },
        opt,
      ),
    ).then((r) => r.json());
  if (WPSMM.darkMode) {
    document.body.classList.add("wpsmm-dark");
  }
  document.addEventListener("click", function (e) {
    const eye = e.target.closest(".wpsmm-eye");
    if (eye) {
      const wrap = eye.closest(".wpsmm-secret");
      const input = wrap && wrap.querySelector("input");
      if (input) {
        input.type = input.type === "password" ? "text" : "password";
        wrap.classList.toggle("is-visible", input.type === "text");
      }
    }
    const gen = e.target.closest(".wpsmm-generate-secret");
    if (gen) {
      const row = gen.closest("label") || document;
      const input = row.querySelector(".wpsmm-secret input");
      if (input) {
        const a = new Uint8Array(24);
        crypto.getRandomValues(a);
        input.value = Array.from(a)
          .map((b) => ("0" + b.toString(16)).slice(-2))
          .join("");
        input.type = "text";
        input.closest(".wpsmm-secret").classList.add("is-visible");
      }
    }
    const toggle = e.target.closest(".wpsmm-picker-toggle");
    if (toggle) {
      toggle.closest(".wpsmm-picker").classList.toggle("is-open");
    }
    if (e.target.closest(".wpsmm-select-all")) {
      qsa(".wpsmm-picker-option input").forEach((i) => (i.checked = true));
    }
    if (e.target.closest(".wpsmm-clear-all")) {
      qsa(".wpsmm-picker-option input").forEach((i) => (i.checked = false));
    }
    const openModal = e.target.closest(".wpsmm-open-modal");
    if (openModal) {
      const target = qs(openModal.dataset.target);
      if (target) {
        target.classList.add("is-open");
        target.setAttribute("aria-hidden", "false");
        document.body.classList.add("wpsmm-modal-open");
      }
    }
    if (e.target.closest("[data-close-modal]")) {
      const modal = e.target.closest(".wpsmm-modal");
      if (modal) {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("wpsmm-modal-open");
      }
    }
    const detail = e.target.closest(".wpsmm-toggle-detail");
    if (detail) {
      const target = qs(detail.dataset.target);
      if (target) {
        const isHidden =
          target.style.display === "none" || !target.style.display;
        target.style.display = isHidden ? "table-row" : "none";
        detail.textContent = isHidden ? "Ẩn chi tiết" : "Xem chi tiết";
      }
    }
    const dark = e.target.closest(".wpsmm-toggle-switch");
    if (dark) {
      const next = !dark.classList.contains("is-on");
      dark.classList.toggle("is-on", next);
      dark.setAttribute("aria-pressed", next ? "true" : "false");
      document.body.classList.toggle("wpsmm-dark", next);
      const field = qs("#wpsmm-dark-mode-field");
      if (field) field.value = next ? "1" : "0";
      dark.classList.add("is-saving");
      api("/settings/dark-mode", {
        method: "POST",
        body: JSON.stringify({ enabled: next }),
      })
        .then((d) => {
          if (!d.success) {
            throw new Error("save failed");
          }
        })
        .catch(() => {
          dark.classList.toggle("is-on", !next);
          dark.setAttribute("aria-pressed", !next ? "true" : "false");
          document.body.classList.toggle("wpsmm-dark", !next);
          if (field) field.value = !next ? "1" : "0";
          alert("Không lưu được dark mode. Vui lòng kiểm tra REST API/nonce.");
        })
        .finally(() => dark.classList.remove("is-saving"));
    }
    const test = e.target.closest(".wpsmm-test-server");
    if (test) {
      test.disabled = true;
      test.textContent = "Testing...";
      api("/server/" + test.dataset.id + "/test", {
        method: "POST",
        body: "{}",
      })
        .then((d) => {
          alert(d.message || "Done");
        })
        .finally(() => {
          test.disabled = false;
          test.textContent = "Test";
        });
    }
  });
  document.addEventListener("input", function (e) {
    if (e.target.classList.contains("wpsmm-picker-search")) {
      const v = e.target.value.toLowerCase();
      qsa(".wpsmm-picker-option").forEach((o) => {
        o.style.display = o.textContent.toLowerCase().includes(v)
          ? "flex"
          : "none";
      });
    }
  });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    qsa(".wpsmm-modal.is-open").forEach((modal) => {
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
    });
    document.body.classList.remove("wpsmm-modal-open");
  });

  let responseChart = null,
    uptimeChart = null;
  function renderKpis(stats) {
    const root = qs("#wpsmm-kpis");
    if (!root) return;
    const items = [
      ["Tổng website", stats.total, "Tất cả site"],
      ["Online", stats.online, "Đang hoạt động"],
      ["Offline", stats.offline, "Cần xử lý"],
      ["Cảnh báo", stats.warning, "SSL/404/hack"],
      ["Health", stats.avgHealth + "%", "Điểm trung bình"],
    ];
    root.innerHTML = items
      .map(
        (i) =>
          `<div class="wpsmm-kpi"><span>${i[0]}</span><strong>${i[1]}</strong><em>${i[2]}</em></div>`,
      )
      .join("");
  }
  function badge(s) {
    return `<span class="wpsmm-badge wpsmm-badge-${s}">${s || "unknown"}</span>`;
  }
  function renderSites(rows) {
    const tb = qs("#wpsmm-sites-table tbody");
    if (!tb) return;
    tb.innerHTML = rows
      .map(
        (s) =>
          `<tr><td><strong>${esc(s.name)}</strong><br><small>${esc(s.url)}</small></td><td>${badge(s.status)}</td><td>${s.http_code || 0}</td><td>${s.response_time || 0}s</td><td>${s.uptime_percent || 0}%</td><td>${s.health_score || 0}%</td><td>${s.last_checked || "—"}</td></tr>`,
      )
      .join("");
  }
  function esc(str) {
    return String(str || "").replace(
      /[&<>"]/g,
      (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[m],
    );
  }
  function renderCharts(data) {
    if (!qs("#wpsmm-response-chart")) return;
    const labels = data.map((x) => x.label);
    const responses = data.map((x) => x.response);
    const uptimes = data.map((x) => x.uptime);
    if (!responseChart) {
      responseChart = new Chart(qs("#wpsmm-response-chart"), {
        type: "line",
        data: {
          labels,
          datasets: [
            {
              label: "Response time",
              data: responses,
              tension: 0.35,
              fill: false,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true } },
        },
      });
    } else {
      responseChart.data.labels = labels;
      responseChart.data.datasets[0].data = responses;
      responseChart.update();
    }
    if (!uptimeChart) {
      uptimeChart = new Chart(qs("#wpsmm-uptime-chart"), {
        type: "line",
        data: {
          labels,
          datasets: [
            { label: "Uptime %", data: uptimes, tension: 0.35, fill: false },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, max: 100 } },
        },
      });
    } else {
      uptimeChart.data.labels = labels;
      uptimeChart.data.datasets[0].data = uptimes;
      uptimeChart.update();
    }
  }
  function refreshDashboard() {
    if (!qs('[data-wpsmm-page="dashboard"]')) return;
    Promise.all([api("/stats"), api("/sites"), api("/chart?hours=24")]).then(
      ([st, sites, chart]) => {
        renderKpis(st);
        renderSites(sites);
        renderCharts(chart);
        const live = qs("#wpsmm-live-state");
        if (live) live.textContent = "Live via REST";
      },
    );
  }
  function refreshJobs() {
    if (qs("#wpsmm-backup-jobs"))
      api("/backup/jobs").then((rows) => {
        qs("#wpsmm-backup-jobs").innerHTML =
          rows
            .slice(0, 8)
            .map(
              (j) =>
                `<div class="wpsmm-job"><strong>${esc(j.file_name)}</strong><div class="wpsmm-progress"><span style="width:${parseInt(j.progress || 0)}%"></span></div><small>${badge(j.status)} ${esc(j.message)}</small></div>`,
            )
            .join("") || "<p>Chưa có job.</p>";
      });
    if (qs("#wpsmm-malware-jobs"))
      api("/malware/jobs").then((rows) => {
        qs("#wpsmm-malware-jobs").innerHTML =
          rows
            .slice(0, 8)
            .map(
              (j) =>
                `<div class="wpsmm-job"><strong>Scan #${j.id} - ${j.suspicious_count || 0} findings</strong><div class="wpsmm-progress"><span style="width:${parseInt(j.progress || 0)}%"></span></div><small>${badge(j.status)} ${esc(j.message)}</small></div>`,
            )
            .join("") || "<p>Chưa có job.</p>";
      });
  }
  function connectWS() {
    if (!WPSMM.websocketUrl || !qs('[data-wpsmm-page="dashboard"]'))
      return false;
    try {
      const ws = new WebSocket(WPSMM.websocketUrl);
      ws.onopen = () => {
        const live = qs("#wpsmm-live-state");
        if (live) live.textContent = "Live via WebSocket";
      };
      ws.onmessage = (ev) => {
        try {
          const data = JSON.parse(ev.data);
          if (data.stats) renderKpis(data.stats);
          if (data.sites) renderSites(data.sites);
          if (data.chart) renderCharts(data.chart);
        } catch (e) {}
      };
      ws.onerror = () => refreshDashboard();
      return true;
    } catch (e) {
      return false;
    }
  }
  const hasWS = connectWS();
  refreshDashboard();
  refreshJobs();
  setInterval(() => {
    if (!hasWS) refreshDashboard();
    refreshJobs();
  }, WPSMM.pollInterval || 10000);
})();
