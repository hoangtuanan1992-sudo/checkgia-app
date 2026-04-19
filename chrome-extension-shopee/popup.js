function setStatus(t) {
  const el = document.getElementById("status");
  if (!el) return;
  if (!t) {
    el.style.display = "none";
    el.textContent = "";
    return;
  }
  el.style.display = "";
  el.textContent = t;
}

async function load() {
  const cfg = await chrome.storage.sync.get(["serverUrl", "agentKey"]);
  const local = await chrome.storage.local.get(["nextPollAt", "nextPollIn", "pairCode", "isApproved", "lastError"]);

  const agentEl = document.getElementById("agent");
  const nextEl = document.getElementById("next");

  if (agentEl) {
    const parts = ["Agent: " + (cfg.agentKey || "(chưa có)")];
    if (local.isApproved === true) {
      parts.push("Status: approved");
    } else if (local.pairCode) {
      parts.push("Pair code: " + local.pairCode);
    } else {
      parts.push("Status: pending");
    }
    agentEl.textContent = parts.join(" | ");
  }

  if (nextEl) {
    if (local.nextPollAt) {
      const d = new Date(local.nextPollAt);
      nextEl.textContent = "Next poll: " + d.toLocaleString();
    } else if (local.nextPollIn) {
      nextEl.textContent = "Next poll in: " + local.nextPollIn + "s";
    }
  }

  if (local.lastError) {
    setStatus(String(local.lastError));
  } else {
    setStatus("");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const optionsBtn = document.getElementById("options");
  const adminBtn = document.getElementById("admin");
  const DEFAULT_SERVER_URL = "https://checkgia.id.vn";

  if (optionsBtn) {
    optionsBtn.addEventListener("click", () => chrome.runtime.openOptionsPage());
  }

  if (adminBtn) {
    adminBtn.addEventListener("click", async () => {
      const { serverUrl } = await chrome.storage.sync.get(["serverUrl"]);
      const base = String(serverUrl || DEFAULT_SERVER_URL).trim();
      const url = base.replace(/\/+$/, "") + "/shopee/admin-settings";
      await chrome.tabs.create({ url });
    });
  }

  load();
});
