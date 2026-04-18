function $(id){return document.getElementById(id)}

async function load() {
  const { serverUrl, token } = await chrome.storage.sync.get(["serverUrl", "token"]);
  $("serverUrl").value = serverUrl || "";
  $("token").value = token || "";
}

function setStatus(text) {
  const el = $("status");
  if (!text) {
    el.style.display = "none";
    el.textContent = "";
    return;
  }
  el.style.display = "";
  el.textContent = text;
}

async function save() {
  const serverUrl = String($("serverUrl").value || "").trim();
  const token = String($("token").value || "").trim();
  await chrome.storage.sync.set({ serverUrl, token });
  setStatus("Đã lưu");
  setTimeout(() => setStatus(""), 1500);
}

document.addEventListener("DOMContentLoaded", () => {
  load();
  $("save").addEventListener("click", (e) => {
    e.preventDefault();
    save();
  });
  $("openAdmin").addEventListener("click", async (e) => {
    e.preventDefault();
    const serverUrl = String($("serverUrl").value || "").trim();
    if (!serverUrl) return;
    const url = serverUrl.replace(/\/+$/, "") + "/shopee/admin-settings";
    await chrome.tabs.create({ url });
  });
});
