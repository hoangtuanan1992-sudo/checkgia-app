const DEFAULT_SERVER_URL = "https://checkgia.id.vn";
const DEFAULT_TOKEN = "";

async function getConfig() {
  const { serverUrl, token, agentKey } = await chrome.storage.sync.get([
    "serverUrl",
    "token",
    "agentKey"
  ]);
  return {
    serverUrl: String(serverUrl || DEFAULT_SERVER_URL || "").trim(),
    token: String(token || DEFAULT_TOKEN || "").trim(),
    agentKey
  };
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
  const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json"
  };
  if (token) {
    headers["Authorization"] = "Bearer " + token;
  }

  const res = await fetch(apiUrl(serverUrl, path), {
    method: "POST",
    headers,
    body: JSON.stringify(body || {})
  });

  const contentType = res.headers.get("content-type") || "";
  const data = contentType.includes("application/json") ? await res.json().catch(() => null) : null;
  return { ok: res.ok, status: res.status, data };
}

async function heartbeat(serverUrl, token, agentKey) {
  const local = await chrome.storage.local.get(["lastError", "lastTaskUrl"]);
  const payload = {
    agent_key: agentKey,
    name: "",
    version: chrome.runtime.getManifest().version,
    platform: navigator.platform || "",
    user_agent: navigator.userAgent || ""
  };

  if (local.lastError) {
    payload.last_error = String(local.lastError).slice(0, 2000);
  }
  if (local.lastTaskUrl) {
    payload.last_task_url = String(local.lastTaskUrl).slice(0, 5000);
  }

  return apiPost(serverUrl, token, "shopee/agent/heartbeat", payload);
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

      function sleep(ms) {
        return new Promise((r) => setTimeout(r, ms));
      }

      function extractPriceCandidates(text) {
        const t = String(text || "");
        const re = /(?:₫\s*)?(\d{1,3}(?:[.,\s]\d{3})+|\d{6,})(?:\s*(?:₫|đ))?/g;
        const out = [];
        let m;
        while ((m = re.exec(t)) !== null) {
          const raw = m[0] || "";
          const numPart = m[1] || "";
          const value = normalizePriceNumber(numPart);
          if (value == null) continue;
          if (value < 1000 || value > 1e12) continue;
          const hasCurrency = /₫|đ/.test(raw);
          out.push({ value, raw, hasCurrency });
        }
        return out;
      }

      function pickBestCandidate(cands) {
        if (!cands.length) return null;
        const counts = new Map();
        for (const c of cands) {
          counts.set(c.value, (counts.get(c.value) || 0) + 1);
        }

        const scored = cands
          .map((c) => {
            let score = 0;
            const count = counts.get(c.value) || 0;
            if (c.hasCurrency) score += 3;
            if (c.value >= 100000) score += 2;
            if (c.value >= 1000000) score += 1;
            score += Math.min(5, count);
            return { ...c, score, count };
          })
          .sort((a, b) => {
            if (b.score !== a.score) return b.score - a.score;
            if (b.count !== a.count) return b.count - a.count;
            return b.value - a.value;
          });

        return scored[0];
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
        const itemprop = document.querySelector('meta[itemprop="price"], meta[property="product:price:amount"]');
        if (itemprop && itemprop.getAttribute("content")) {
          const n = normalizePriceNumber(itemprop.getAttribute("content"));
          if (n != null) return n;
        }
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
        const candidates = extractPriceCandidates(body);
        const best = pickBestCandidate(candidates);
        return best ? best.value : null;
      }

      function getPriceFromDom() {
        const texts = [];
        const selectors = [
          '[data-sqe*="price"]',
          '[data-sqe*="item_price"]',
          '[data-sqe*="main_price"]',
          '[class*="price"]',
          '[class*="Price"]',
        ];
        for (const sel of selectors) {
          const els = Array.from(document.querySelectorAll(sel)).slice(0, 50);
          for (const el of els) {
            const t = (el && el.textContent) ? el.textContent.trim() : "";
            if (t) texts.push(t);
          }
        }
        const all = texts.join(" | ");
        const candidates = extractPriceCandidates(all);
        const best = pickBestCandidate(candidates);
        return best ? best.value : null;
      }

      function parseShopeeIdsFromPath(pathname) {
        const p = String(pathname || "");
        const m1 = p.match(/^\/product\/(\d+)\/(\d+)/);
        if (m1) return { shopId: m1[1], itemId: m1[2] };

        const m2 = p.match(/i\.(\d+)\.(\d+)/);
        if (m2) return { shopId: m2[1], itemId: m2[2] };

        return null;
      }

      async function fetchItemApi() {
        const ids = parseShopeeIdsFromPath(location.pathname);
        if (!ids) return null;

        const url = `/api/v4/item/get?shopid=${encodeURIComponent(ids.shopId)}&itemid=${encodeURIComponent(ids.itemId)}`;
        const ctrl = new AbortController();
        const t = setTimeout(() => ctrl.abort(), 10000);
        try {
          const res = await fetch(url, { credentials: "include", signal: ctrl.signal });
          if (!res.ok) return null;
          const json = await res.json().catch(() => null);
          const item = json && json.data && json.data.item ? json.data.item : null;
          if (!item) return null;

          const name = item.name ? String(item.name).slice(0, 255) : null;

          const rawPrice =
            item.price_min != null ? item.price_min :
              item.price != null ? item.price :
                item.price_max != null ? item.price_max :
                  item.price_min_before_discount != null ? item.price_min_before_discount :
                    null;

          if (rawPrice == null) {
            return { name, price: null, raw_text: "" };
          }

          const p = Number(rawPrice);
          if (!Number.isFinite(p) || p <= 0) {
            return { name, price: null, raw_text: String(rawPrice) };
          }

          const normalized = Math.max(0, Math.trunc(p / 100000));
          if (!normalized) {
            return { name, price: null, raw_text: String(rawPrice) };
          }

          return { name, price: normalized, raw_text: String(rawPrice) };
        } catch (e) {
          return null;
        } finally {
          clearTimeout(t);
        }
      }

      function detectBlock() {
        const html = document.documentElement ? document.documentElement.innerText || "" : "";
        const s = html.toLowerCase();
        if (s.includes("captcha") || s.includes("xác minh") || s.includes("verify") || s.includes("unusual traffic")) {
          return "Shopee chặn (captcha/verify).";
        }
        if (s.includes("enable cookies") || s.includes("cookies")) {
          return "Shopee yêu cầu cookies.";
        }
        return null;
      }

      async function waitForPrice(maxMs) {
        const deadline = Date.now() + maxMs;
        let lastPrice = null;
        while (Date.now() < deadline) {
          const price = getPriceFromMeta() ?? getPriceFromLd() ?? getPriceFromDom() ?? getPriceFromText();
          if (price != null) {
            return price;
          }
          lastPrice = price;
          await sleep(500);
        }
        return lastPrice;
      }

      return (async () => {
        const blockReason = detectBlock();
        const api = await fetchItemApi();
        if (api && api.price != null) {
          return { price: api.price, name: api.name ?? getName(), raw_text: api.raw_text || "", block_reason: blockReason };
        }

        const price = await waitForPrice(20000);
        const name = api && api.name ? api.name : getName();
        return { price, name, raw_text: price != null ? String(price) : (api && api.raw_text ? api.raw_text : ""), block_reason: blockReason };
      })();
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

async function setLastError(err) {
  const msg = err instanceof Error ? (err.message || String(err)) : String(err || "");
  await chrome.storage.local.set({ lastError: msg.slice(0, 500) });
}

async function clearLastError() {
  await chrome.storage.local.set({ lastError: "" });
}

async function pollOnce() {
  const agentKey = await ensureAgentKey();
  const cfg = await getConfig();
  const serverUrl = cfg.serverUrl;
  let token = cfg.token;
  if (!serverUrl) {
    await scheduleNext(120);
    return;
  }

  const hb = await heartbeat(serverUrl, token, agentKey).catch((e) => {
    setLastError(e);
    return null;
  });
  if (!hb) {
    await scheduleNext(120);
    return;
  }
  if (!hb.ok) {
    const msg = hb.data && hb.data.message ? String(hb.data.message) : "";
    await chrome.storage.local.set({ lastError: msg || ("Heartbeat HTTP " + hb.status) });
    await scheduleNext(hb.status === 403 ? 60 : 120);
    return;
  }
  if (hb.data && hb.data.agent) {
    const agent = hb.data.agent;
    if (agent.pair_code) {
      await chrome.storage.local.set({ pairCode: String(agent.pair_code) });
    }
    if (agent.is_approved != null) {
      await chrome.storage.local.set({ isApproved: Boolean(agent.is_approved) });
    }
    if (agent.api_token) {
      const newToken = String(agent.api_token).trim();
      if (newToken) {
        await chrome.storage.sync.set({ token: newToken });
        token = newToken;
      }
    }
  }

  const pullRes = await apiPost(serverUrl, token, "shopee/agent/pull", { agent_key: agentKey }).catch(() => null);
  if (!pullRes) {
    await scheduleNext(120);
    return;
  }
  if (!pullRes.ok) {
    const msg = pullRes.data && pullRes.data.message ? String(pullRes.data.message) : "";
    await chrome.storage.local.set({ lastError: msg || ("HTTP " + pullRes.status) });
    await scheduleNext(pullRes.status === 403 ? 60 : 120);
    return;
  }
  if (!pullRes.data) {
    await scheduleNext(120);
    return;
  }

  const sleepSeconds = Number(pullRes.data.sleep_seconds || 0);
  const task = pullRes.data.task;

  if (!task || !task.url || !task.type) {
    await scheduleNext(sleepSeconds || 60);
    return;
  }

  await chrome.storage.local.set({ lastTaskUrl: task.url });
  const scrape = await openAndScrape(task.url).catch((e) => {
    setLastError(e);
    return null;
  });
  const price = scrape && scrape.price != null ? Number(scrape.price) : null;
  const name = scrape && scrape.name ? String(scrape.name).slice(0, 255) : null;
  const rawText = scrape && scrape.raw_text ? String(scrape.raw_text).slice(0, 20000) : null;
  const blockReason = scrape && scrape.block_reason ? String(scrape.block_reason) : "";

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

    const rep = await apiPost(serverUrl, token, "shopee/agent/report", {
      ...payload
    }).catch(() => null);
    if (rep && rep.ok) {
      await chrome.storage.local.set({ lastError: "", lastTaskUrl: "" });
    }
  } else {
    await chrome.storage.local.set({
      lastError: blockReason || "Không lấy được giá từ trang Shopee",
      lastTaskUrl: task.url
    });
  }

  await scheduleNext(sleepSeconds || 60);
}

chrome.runtime.onInstalled.addListener(() => {
  scheduleNext(5).catch(() => {});
});

chrome.runtime.onStartup.addListener(() => {
  scheduleNext(5).catch(() => {});
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
  if (!alarm || alarm.name !== "poll") return;
  try {
    await pollOnce();
  } catch (e) {
    await setLastError(e);
    await scheduleNext(120);
  }
});

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg && msg.type === "pollNow") {
    pollOnce()
      .then(() => sendResponse({ ok: true }))
      .catch((e) => {
        setLastError(e);
        sendResponse({ ok: false, error: String(e && e.message ? e.message : e) });
      });
    return true;
  }
  return false;
});
