// This script runs on the checkgia.id.vn website
// It listens for a custom event from the web page and forwards it to the service worker

function handleContextInvalidated(message) {
  const m = String(message || "").toLowerCase();
  if (!m.includes("extension context invalidated")) return false;
  try {
    const key = "__checkgia_ext_reload_once";
    if (sessionStorage.getItem(key) === "1") return true;
    sessionStorage.setItem(key, "1");
    setTimeout(() => window.location.reload(), 250);
  } catch (e) {}
  return true;
}

function triggerPollNow(reason) {
  try {
    if (!chrome || !chrome.runtime || typeof chrome.runtime.sendMessage !== "function") return;
    chrome.runtime.sendMessage({ type: "pollNow", reason: reason || "" }, (response) => {
      if (chrome.runtime.lastError) {
        const msg = chrome.runtime.lastError.message || "";
        if (handleContextInvalidated(msg)) return;
        console.warn("[CheckGia Extension] Error sending message:", msg);
        return;
      }
      console.log("[CheckGia Extension] Poll triggered successfully", response);
    });
  } catch (e) {
    const msg = e && e.message ? e.message : String(e || "");
    if (handleContextInvalidated(msg)) return;
    console.warn("[CheckGia Extension] Error sending message:", msg);
  }
}

window.addEventListener("message", (event) => {
  // Only accept messages from the same window
  if (event.source !== window) return;

  if (event.data && event.data.type === "CHECKGIA_TRIGGER_POLL") {
    console.log("[CheckGia Extension] Received trigger poll message from website");
    triggerPollNow("website_message");
  }
});

// Also trigger once on load if we are on the shopee dashboard
if (window.location.pathname.includes("/shopee")) {
    console.log("[CheckGia Extension] Auto-triggering poll on Shopee dashboard load");
    triggerPollNow("dashboard_load");
}
