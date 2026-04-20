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
  if (text == null || text === "") return null;
  let s = String(text).trim();

  // Handle ISO format (e.g. 150000.00000 from meta tags)
  if (s.includes(".") && !s.includes(",")) {
    const parts = s.split(".");
    if (parts.length === 2 && parts[1].length !== 3) {
      return Math.trunc(parseFloat(s));
    }
  }

  // Fallback to removing all non-digits for display formats like "150.000"
  s = s.replace(/[^\d]/g, "");
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
        if (text == null || text === "") return null;
        let s = String(text).trim();

        // Handle ISO format (e.g. 150000.00000 from meta tags)
        if (s.includes(".") && !s.includes(",")) {
          const parts = s.split(".");
          if (parts.length === 2 && parts[1].length !== 3) {
            return Math.trunc(parseFloat(s));
          }
        }

        // Fallback to removing all non-digits for display formats like "150.000"
        s = s.replace(/[^\d]/g, "");
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
        
        // Improved regex:
        const re = /(?:₫\s*)?(\d{1,3}(?:[.,\s]\d{3})+|\d+)(?:\s*(?:₫|đ|k))?/gi;
        const out = [];
        let m;
        while ((m = re.exec(t)) !== null) {
          const raw = m[0] || "";
          const numPart = m[1] || "";
          
          // Check for "+" immediately after the match (e.g., "1k+")
          const nextChar = t.charAt(re.lastIndex);
          if (nextChar === '+') continue;

          let value = normalizePriceNumber(numPart);
          if (value == null) continue;

          // Handle 'k' suffix (e.g., 150k)
          if (raw.toLowerCase().endsWith("k")) {
             // If it's just "1k", "2k" and there's "đã bán" or "sold" nearby, ignore it.
             const context = t.toLowerCase();
             if (context.includes("đã bán") || context.includes("sold") || context.includes("đánh giá") || context.includes("rating")) {
                 continue; 
             }
             if (value < 10000) {
                value *= 1000;
             }
          }

          if (value < 500 || value > 1e12) continue;
          
          // Extra check: if the number is part of "Đã bán 1.200"
          // Only check a very small window to avoid false positives
          const beforeMatch = t.substring(Math.max(0, m.index - 12), m.index).toLowerCase();
          if (beforeMatch.includes("đã bán") || beforeMatch.includes("sold") || beforeMatch.includes("đánh giá")) {
              continue;
          }

          const hasCurrency = /₫|đ|vnđ/i.test(raw);
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
            if (c.hasCurrency) score += 6; // High priority for currency symbol
            if (c.value >= 1000) score += 1;
            if (c.value >= 10000) score += 2; // Real prices are usually > 10k
            if (c.value >= 100000) score += 1;
            
            // Penalize small numbers without currency symbol
            if (c.value < 10000 && !c.hasCurrency) score -= 5;
            
            score += Math.min(5, count);
            return { ...c, score, count };
          })
          .sort((a, b) => {
            if (b.score !== a.score) return b.score - a.score;
            if (b.count !== a.count) return b.count - a.count;
            // Prefer the LOWER price (usually the discounted one) if scores are tied
            return a.value - b.value;
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
        if (og && og.getAttribute("content")) {
          let c = String(og.getAttribute("content") || "").trim();
          c = c.replace(/\s+-\s+Shopee.*$/i, "").replace(/\s*\|\s*Shopee.*$/i, "").trim();
          if (c && !c.toLowerCase().includes("shopee việt nam")) return c;
        }

        const sel = [
          'h1',
          '[data-sqe="name"]',
          '[data-sqe="product_name"]',
          'div[class*="product"] h1',
          'div[class*="Product"] h1',
          '.vR6K3w' // User's HTML class
        ];
        for (const s of sel) {
          const el = document.querySelector(s);
          const t = el && el.textContent ? el.textContent.trim() : "";
          if (t && !t.toLowerCase().includes("shopee việt nam")) return t.slice(0, 255);
        }

        const t = document.title || "";
        const cleaned = t.replace(/\s+-\s+Shopee.*$/i, "").replace(/\s*\|\s*Shopee.*$/i, "").trim();
        if (!cleaned || cleaned.toLowerCase().includes("shopee việt nam")) return null;
        return cleaned;
      }

      function getPriceFromShopeeRangeClass() {
        const selectors = [
          ".IZPeQz.B67UQ0", // Main current price (single or range)
          "._44qnta",      // Current price container
          ".pqm66d",      // Another current price container
          ".G27LRz",      // Yet another
          "div[class*='product-briefing'] span[class*='price']",
          "div[class*='product_details'] span[class*='price']"
        ];
        for (const s of selectors) {
          const el = document.querySelector(s);
          if (el) {
            const txt = el.textContent ? el.textContent.trim() : "";
            if (txt) {
              const cands = extractPriceCandidates(txt);
              if (cands.length) {
                  // Sort by value to get the minimum if it's a range
                  return cands.sort((a, b) => a.value - b.value)[0].value;
              }
            }
          }
        }
        return null;
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

      function isNoisePrice(el) {
        if (!el) return false;
        
        const text = el.innerText ? el.innerText.toLowerCase() : "";
        const hasCurrency = /₫|đ|vnđ/i.test(text);
        
        // If it has currency symbol, it's likely a price, but check for very specific noise
        if (hasCurrency) {
            const verySpecificNoise = ["tặng voucher", "phí ship", "phí vận chuyển"];
            if (verySpecificNoise.some(kw => text.includes(kw))) return true;
            return false;
        }

        const noiseKeywords = ["đã bán", "sold", "đánh giá", "rating", "voucher", "phí ship", "vận chuyển"];
        if (noiseKeywords.some(kw => text.includes(kw))) return true;

        let parent = el.parentElement;
        let depth = 0;
        const parentNoiseKeywords = ["voucher", "phí ship", "vận chuyển"];
        while (parent && depth < 2) {
          const pText = parent.innerText ? parent.innerText.toLowerCase() : "";
          if (parentNoiseKeywords.some(kw => pText.includes(kw))) {
            if (!parent.classList.contains("IZPeQz") && !parent.classList.contains("B67UQ0")) {
                return true;
            }
          }
          parent = parent.parentElement;
          depth++;
        }
        return false;
      }

      function getPriceFromDom() {
        const candidates = [];
        const selectors = [
          '[data-sqe*="price"]',
          '[data-sqe*="item_price"]',
          '[data-sqe*="main_price"]',
          '.IZPeQz.B67UQ0',
          '._44qnta',
          '.pqm66d',
          '.G27LRz',
        ];
        for (const sel of selectors) {
          const els = Array.from(document.querySelectorAll(sel)).slice(0, 20);
          for (const el of els) {
            if (isNoisePrice(el)) continue;
            const t = el.textContent ? el.textContent.trim() : "";
            if (t) {
              const cands = extractPriceCandidates(t);
              candidates.push(...cands);
            }
          }
        }
        const best = pickBestCandidate(candidates);
        return best ? best.value : null;
      }

      function getPriceFromText() {
        // Try to focus on the product info section instead of whole body
        const main = document.querySelector('div[class*="product-briefing"]') || 
                     document.querySelector('div[class*="product_details"]') ||
                     document.querySelector('section[class*="YTDXQ0"]') || // Based on user's HTML
                     document.body;
        
        const bodyText = main ? main.innerText : "";
        const lines = bodyText.split("\n");
        const candidates = [];
        const noiseKeywords = ["voucher", "phí ship", "vận chuyển", "tặng", "ưu đãi", "giảm tối đa", "đơn tối thiểu", "đã bán", "sold", "đánh giá", "rating", "kho hàng", "stock"];
        
        for (const line of lines) {
            const l = line.trim().toLowerCase();
            if (!l) continue;
            if (noiseKeywords.some(kw => l.includes(kw))) continue;
            
            const cands = extractPriceCandidates(line);
            candidates.push(...cands);
        }
        
        const best = pickBestCandidate(candidates);
        return best ? best.value : null;
      }

      function parseShopeeIdsFromPath(pathname) {
        const p = String(pathname || "");
        
        // Handle /product/shopId/itemId
        const m1 = p.match(/\/product\/(\d+)\/(\d+)/);
        if (m1) return { shopId: m1[1], itemId: m1[2] };

        // Handle i.shopId.itemId
        const m2 = p.match(/i\.(\d+)\.(\d+)/);
        if (m2) return { shopId: m2[1], itemId: m2[2] };

        return null;
      }

      async function fetchItemApi() {
        try {
          const ids = parseShopeeIdsFromPath(location.pathname + location.search);
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

            // Shopee API returns price as an integer. 
            // Usually it's multiplied by 100,000 (e.g., 150,000 VND is 15,000,000,000)
            const rawPrice =
              item.price_min != null ? item.price_min :
                item.price != null ? item.price :
                  item.price_max != null ? item.price_max :
                    item.price_min_before_discount != null ? item.price_min_before_discount :
                      null;

            if (rawPrice == null) {
              return { name, price: null, raw_text: "" };
            }

            let p = Number(rawPrice);
            if (!Number.isFinite(p) || p <= 0) {
              return { name, price: null, raw_text: String(rawPrice) };
            }

            // Smart normalization: Shopee prices are usually price * 100,000.
            // In VN, prices are rarely below 1,000 VND.
            if (p >= 1000000) {
                p = p / 100000;
            }

            return { name, price: Math.trunc(p), raw_text: String(rawPrice) };
          } catch (e) {
            return null;
          } finally {
            clearTimeout(t);
          }
        } catch (e) { 
          return null;
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

        const price = getPriceFromShopeeRangeClass() ?? await waitForPrice(20000);
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
