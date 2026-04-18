async function getConfig() {
  const { serverUrl, token, agentKey } = await chrome.storage.sync.get([
    "serverUrl",
    "token",
    "agentKey"
  ]);
  return { serverUrl, token, agentKey };
}

async function ensureAgentKey() {
  const { agentKey } = await chrome.storage.sync.get(["agentKey"]);
  if (agentKey) return agentKey;
  const newKey = crypto.randomUUID();
  await chrome.storage.sync.set({ agentKey: newKey });
  return newKey;
}

function apiUrl(serverUrl, path) {
  const base = String(serverUrl || "").replace(/\/+$/, "");
  return base + "/api/" + path.replace(/^\/+/, "");
}

async function apiPost(serverUrl, token, path, body) {
  const res = await fetch(apiUrl(serverUrl, path), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": "Bearer " + token,
      "Accept": "application/json"
    },
    body: JSON.stringify(body || {})
  });

  const contentType = res.headers.get("content-type") || "";
  const data = contentType.includes("application/json") ? await res.json().catch(() => null) : null;
  return { ok: res.ok, status: res.status, data };
}

async function heartbeat(serverUrl, token, agentKey) {
  return apiPost(serverUrl, token, "shopee/agent/heartbeat", {
    agent_key: agentKey,
    name: "",
    version: chrome.runtime.getManifest().version,
    platform: navigator.platform || "",
    user_agent: navigator.userAgent || ""
  });
}

function normalizePriceNumber(text) {
  const s = String(text || "").replace(/[^\d]/g, "");
  if (!s) return null;
  const n = Number(s);
  if (!Number.isFinite(n)) return null;
  return Math.max(0, Math.trunc(n));
}

function pickFromLdJson(json) {
  if (!json) return null;
  const j = Array.isArray(json) ? json : [json];
  for (const obj of j) {
    if (!obj || typeof obj !== "object") continue;
    const offers = obj.offers || obj.Offers;
    if (!offers) continue;
    const o = Array.isArray(offers) ? offers[0] : offers;
    const price = o && (o.price || o.lowPrice || o.highPrice);
    if (price != null) {
      const n = normalizePriceNumber(price);
      if (n != null) return n;
    }
  }
  return null;
}

async function scrapeTab(tabId) {
  const [{ result }] = await chrome.scripting.executeScript({
    target: { tabId },
    func: () => {
      function normalizePriceNumber(text) {
        const s = String(text || "").replace(/[^\d]/g, "");
        if (!s) return null;
        const n = Number(s);
        if (!Number.isFinite(n)) return null;
        return Math.max(0, Math.trunc(n));
      }

      function pickFromLdJson(json) {
        if (!json) return null;
        const j = Array.isArray(json) ? json : [json];
        for (const obj of j) {
          if (!obj || typeof obj !== "object") continue;
          const offers = obj.offers || obj.Offers;
          if (!offers) continue;
          const o = Array.isArray(offers) ? offers[0] : offers;
          const price = o && (o.price || o.lowPrice || o.highPrice);
          if (price != null) {
            const n = normalizePriceNumber(price);
            if (n != null) return n;
          }
        }
        return null;
      }

      function getName() {
        const og = document.querySelector('meta[property="og:title"]');
        if (og && og.getAttribute("content")) return og.getAttribute("content");
        const t = document.title || "";
        return t.replace(/\s+-\s+Shopee.*$/i, "").trim() || null;
      }

      function getPriceFromMeta() {
        const m = document.querySelector('meta[property="product:price:amount"]');
        const c = m ? m.getAttribute("content") : null;
        return c ? normalizePriceNumber(c) : null;
      }

      function getPriceFromLd() {
        const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
        for (const s of scripts) {
          const txt = s.textContent || "";
          try {
            const json = JSON.parse(txt);
            const n = pickFromLdJson(json);
            if (n != null) return n;
          } catch (e) {
          }
        }
        return null;
      }

      function getPriceFromText() {
        const body = document.body ? document.body.innerText : "";
        const m = body.match(/(\d{1,3}(?:[.,\s]\d{3})+)\s*₫/);
        if (m && m[1]) return normalizePriceNumber(m[1]);
        return null;
      }

      const price = getPriceFromMeta() ?? getPriceFromLd() ?? getPriceFromText();
      return { price, name: getName(), raw_text: price != null ? String(price) : "" };
    }
  });

  return result || null;
}

async function openAndScrape(url) {
  const tab = await chrome.tabs.create({ url, active: false });
  const tabId = tab.id;
  if (!tabId) return null;

  try {
    await new Promise((resolve) => {
      const timeout = setTimeout(resolve, 30000);
      function onUpdated(id, info) {
        if (id !== tabId) return;
        if (info.status === "complete") {
          chrome.tabs.onUpdated.removeListener(onUpdated);
          clearTimeout(timeout);
          resolve();
        }
      }
      chrome.tabs.onUpdated.addListener(onUpdated);
    });

    const res = await scrapeTab(tabId);
    return res;
  } finally {
    chrome.tabs.remove(tabId).catch(() => {});
  }
}

async function scheduleNext(seconds) {
  const s = Math.max(5, Math.min(3600, Math.trunc(Number(seconds) || 60)));
  await chrome.alarms.clear("poll");
  await chrome.alarms.create("poll", { when: Date.now() + s * 1000 });
  await chrome.storage.local.set({ nextPollIn: s, nextPollAt: Date.now() + s * 1000 });
}

async function pollOnce() {
  const agentKey = await ensureAgentKey();
  const cfg = await getConfig();
  const serverUrl = cfg.serverUrl;
  const token = cfg.token;
  if (!serverUrl || !token) {
    await scheduleNext(120);
    return;
  }

  await heartbeat(serverUrl, token, agentKey).catch(() => null);

  const pullRes = await apiPost(serverUrl, token, "shopee/agent/pull", { agent_key: agentKey }).catch(() => null);
  if (!pullRes || !pullRes.ok || !pullRes.data) {
    await scheduleNext(120);
    return;
  }

  const sleepSeconds = Number(pullRes.data.sleep_seconds || 0);
  const task = pullRes.data.task;

  if (!task || !task.url || !task.type) {
    await scheduleNext(sleepSeconds || 60);
    return;
  }

  const scrape = await openAndScrape(task.url).catch(() => null);
  const price = scrape && scrape.price != null ? Number(scrape.price) : null;
  const name = scrape && scrape.name ? String(scrape.name).slice(0, 255) : null;
  const rawText = scrape && scrape.raw_text ? String(scrape.raw_text).slice(0, 20000) : null;

  if (price != null && Number.isFinite(price)) {
    const payload = {
      agent_key: agentKey,
      task_type: String(task.type || ""),
      price: Math.max(0, Math.trunc(price)),
      scraped_at: new Date().toISOString(),
      raw_text: rawText,
      name
    };

    if (task.type === "product" && task.product_id) {
      payload.product_id = Number(task.product_id);
    }
    if (task.type === "competitor" && task.competitor_id) {
      payload.competitor_id = Number(task.competitor_id);
    }

    await apiPost(serverUrl, token, "shopee/agent/report", {
      ...payload
    }).catch(() => null);
  }

  await scheduleNext(sleepSeconds || 60);
}

chrome.runtime.onInstalled.addListener(() => {
  scheduleNext(5).catch(() => {});
});

chrome.runtime.onStartup.addListener(() => {
  scheduleNext(5).catch(() => {});
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm && alarm.name === "poll") {
    pollOnce().catch(() => scheduleNext(120));
  }
});
