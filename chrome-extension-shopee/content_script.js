// This script runs on the checkgia.id.vn website
// It listens for a custom event from the web page and forwards it to the service worker

window.addEventListener("message", (event) => {
  // Only accept messages from the same window
  if (event.source !== window) return;

  if (event.data && event.data.type === "CHECKGIA_TRIGGER_POLL") {
    console.log("[CheckGia Extension] Received trigger poll message from website");
    chrome.runtime.sendMessage({ type: "pollNow" }, (response) => {
      if (chrome.runtime.lastError) {
        console.warn("[CheckGia Extension] Error sending message:", chrome.runtime.lastError.message);
      } else {
        console.log("[CheckGia Extension] Poll triggered successfully", response);
      }
    });
  }
});

// Also trigger once on load if we are on the shopee dashboard
if (window.location.pathname.includes("/shopee")) {
    console.log("[CheckGia Extension] Auto-triggering poll on Shopee dashboard load");
    chrome.runtime.sendMessage({ type: "pollNow" });
}
