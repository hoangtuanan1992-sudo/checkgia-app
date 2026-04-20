// This script runs on the checkgia.id.vn website
// It listens for a custom event from the web page and forwards it to the service worker

window.addEventListener("checkgia:trigger_poll", () => {
  console.log("[CheckGia Extension] Received trigger poll event from website");
  chrome.runtime.sendMessage({ type: "pollNow" }, (response) => {
    if (chrome.runtime.lastError) {
      console.warn("[CheckGia Extension] Error sending message:", chrome.runtime.lastError.message);
    } else {
      console.log("[CheckGia Extension] Poll triggered successfully", response);
    }
  });
});

// Also trigger once on load if we are on the shopee dashboard
if (window.location.pathname.includes("/shopee")) {
    chrome.runtime.sendMessage({ type: "pollNow" });
}
