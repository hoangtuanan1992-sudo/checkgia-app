function $(id){return document.getElementById(id)}
const DEFAULT_SERVER_URL = "https://checkgia.id.vn";

async function load() {
  const { serverUrl } = await chrome.storage.sync.get(["serverUrl"]);
  $("serverUrl").value = serverUrl || DEFAULT_SERVER_URL;
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
  await chrome.storage.sync.set({ serverUrl });
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
