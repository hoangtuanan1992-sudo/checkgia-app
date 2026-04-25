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

async function scrapeTab(tabId, variantPath, preferMax) {
  const [{ result }] = await chrome.scripting.executeScript({
    target: { tabId },
    args: [variantPath || null, !!preferMax],
    func: (variantPath, preferMax) => {
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
        
        const re = /(?:₫\s*)?(\d{1,3}(?:[.,\s]\d{3})+|\d+)(?:\s*(?:₫|đ|k))?/gi;
        const out = [];
        let m;
        while ((m = re.exec(t)) !== null) {
          const raw = m[0] || "";
          const numPart = m[1] || "";
          
          const nextChar = t.charAt(re.lastIndex);
          if (nextChar === '+') continue;

          let value = normalizePriceNumber(numPart);
          if (value == null) continue;

          const hasCurrency = /₫|đ|vnđ/i.test(raw);

          // Handle 'k' suffix (e.g., 150k)
          if (raw.toLowerCase().endsWith("k")) {
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
          // ONLY filter if it DOES NOT have a currency symbol
          if (!hasCurrency) {
            const beforeMatch = t.substring(Math.max(0, m.index - 12), m.index).toLowerCase();
            if (beforeMatch.includes("đã bán") || beforeMatch.includes("sold") || beforeMatch.includes("đánh giá")) {
                continue;
            }
          }

          out.push({ value, raw, hasCurrency });
        }
        return out;
      }

      function pickBestCandidate(cands, preferMax) {
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
            return preferMax ? (b.value - a.value) : (a.value - b.value);
          });

        return scored[0];
      }

      function pickFromLdJson(json, preferMax) {
        if (!json) return null;
        const j = Array.isArray(json) ? json : [json];
        for (const obj of j) {
          if (!obj || typeof obj !== "object") continue;
          const offers = obj.offers || obj.Offers;
          if (!offers) continue;
          const o = Array.isArray(offers) ? offers[0] : offers;
          const price = o && (o.price || (preferMax ? o.highPrice : o.lowPrice) || o.lowPrice || o.highPrice);
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
        if (cleaned && !cleaned.toLowerCase().includes("shopee việt nam")) return cleaned;
        const rawTitle = String(t || "").trim();
        if (rawTitle) return rawTitle.slice(0, 255);
        return null;
      }

      function getPriceFromShopeeRangeClass(preferMax) {
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
                  cands.sort((a, b) => a.value - b.value);
                  return (preferMax ? cands[cands.length - 1] : cands[0]).value;
              }
            }
          }
        }
        return null;
      }

      function getPriceTextFromShopeeRangeClass() {
        const selectors = [
          ".IZPeQz.B67UQ0",
          "._44qnta",
          ".pqm66d",
          ".G27LRz",
          "div[class*='product-briefing'] span[class*='price']",
          "div[class*='product_details'] span[class*='price']"
        ];
        for (const s of selectors) {
          const el = document.querySelector(s);
          if (el) {
            const txt = el.textContent ? el.textContent.trim() : "";
            if (txt) return txt;
          }
        }
        return "";
      }

      function getSinglePriceFromText(text) {
        const t = String(text || "").trim();
        if (!t) return null;
        const cands = extractPriceCandidates(t).map((x) => x.value);
        if (!cands.length) return null;
        const uniq = Array.from(new Set(cands));
        if (uniq.length === 1) return uniq[0];
        return null;
      }

      function getPriceFromMeta() {
        const itemprop = document.querySelector('meta[itemprop="price"], meta[property="product:price:amount"]');
        if (itemprop && itemprop.getAttribute("content")) {
          const n = normalizePriceNumber(itemprop.getAttribute("content"));
          if (n != null && n > 500) return n;
        }
        const m = document.querySelector('meta[property="product:price:amount"]');
        const c = m ? m.getAttribute("content") : null;
        if (c) {
           const n = normalizePriceNumber(c);
           if (n != null && n > 500) return n;
        }
        return null;
      }

      function getPriceFromLd() {
        const scripts = Array.from(document.querySelectorAll('script[type="application/ld+json"]'));
        for (const s of scripts) {
          const txt = s.textContent || "";
          try {
            const json = JSON.parse(txt);
            const n = pickFromLdJson(json, preferMax);
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
        const best = pickBestCandidate(candidates, preferMax);
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
        
        const best = pickBestCandidate(candidates, preferMax);
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

      async function fetchItemApi(variantPath, uiVariantNames, uiVariantGroups) {
        try {
          const ids = parseShopeeIdsFromPath(location.pathname + location.search);
          if (!ids) return null;

          const modelIdFromUrl = (() => {
            try {
              const sp = new URLSearchParams(location.search || "");
              const extra = sp.get("extraParams");
              if (!extra) return null;
              const parsed = JSON.parse(extra);
              const mid = parsed && parsed.display_model_id ? Number(parsed.display_model_id) : null;
              return Number.isFinite(mid) ? mid : null;
            } catch (e) {
              return null;
            }
          })();

          const baseV4 = `/api/v4/item/get?shopid=${encodeURIComponent(ids.shopId)}&itemid=${encodeURIComponent(ids.itemId)}`;
          const baseV2 = `/api/v2/item/get?shopid=${encodeURIComponent(ids.shopId)}&itemid=${encodeURIComponent(ids.itemId)}`;
          const urlSet = new Set();
          const pushUrl = (u) => { if (u) urlSet.add(u); };
          pushUrl(baseV4);
          pushUrl(baseV2);
          if (modelIdFromUrl) {
            pushUrl(`${baseV4}&selected_model_id=${encodeURIComponent(String(modelIdFromUrl))}`);
            pushUrl(`${baseV4}&modelid=${encodeURIComponent(String(modelIdFromUrl))}`);
            pushUrl(`${baseV4}&display_model_id=${encodeURIComponent(String(modelIdFromUrl))}`);
            pushUrl(`${baseV2}&selected_model_id=${encodeURIComponent(String(modelIdFromUrl))}`);
            pushUrl(`${baseV2}&modelid=${encodeURIComponent(String(modelIdFromUrl))}`);
            pushUrl(`${baseV2}&display_model_id=${encodeURIComponent(String(modelIdFromUrl))}`);
          }
          const urls = Array.from(urlSet);

          const fetchJson = async (url) => {
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 10000);
            try {
              const res = await fetch(url, {
                credentials: "include",
                signal: ctrl.signal,
                headers: {
                  "accept": "application/json",
                  "x-api-source": "pc",
                  "x-requested-with": "XMLHttpRequest"
                },
                referrer: location.href,
                referrerPolicy: "strict-origin-when-cross-origin",
              });
              if (!res.ok) return null;
              return await res.json().catch(() => null);
            } catch (e) {
              return null;
            } finally {
              clearTimeout(t);
            }
          };

          let json = null;
          try {
            for (const u of urls) {
              json = await fetchJson(u);
              if (json) break;
            }
            const item =
              (json && json.data && json.data.item ? json.data.item : null) ??
              (json && json.item ? json.item : null) ??
              (json && json.data && json.data.data && json.data.data.item ? json.data.data.item : null) ??
              null;
            if (!item) return null;

            const name = item.name ? String(item.name).slice(0, 255) : null;

            const normalizeTextLite = (s) => String(s || "").replace(/\s+/g, " ").trim().toLowerCase();
            const normalizeKey = (s) => {
              let x = normalizeTextLite(s);
              try {
                x = x.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
              } catch (e) {}
              x = x.replace(/[^a-z0-9]+/g, " ").replace(/\s+/g, " ").trim();
              return x;
            };

            const pickModelId = (m) => {
              if (!m || typeof m !== "object") return null;
              const id =
                (m.modelid != null ? m.modelid : null) ??
                (m.model_id != null ? m.model_id : null) ??
                (m.modelId != null ? m.modelId : null) ??
                (m.id != null ? m.id : null) ??
                null;
              const n = Number(id);
              return Number.isFinite(n) ? n : null;
            };

            const pickPriceValue = (v) => {
              if (v == null) return null;
              if (Array.isArray(v)) {
                const nums = [];
                for (const x of v) {
                  const n = pickPriceValue(x);
                  if (n != null) nums.push(n);
                }
                if (!nums.length) return null;
                return nums.reduce((a, b) => (a > b ? a : b), nums[0]);
              }
              if (typeof v === "object") {
                const candidates = [
                  v.price_after_discount,
                  v.price,
                  v.price_min,
                  v.price_max,
                  v.price_before_discount,
                  v.price_min_before_discount,
                ];
                for (const c of candidates) {
                  const n = pickPriceValue(c);
                  if (n != null) return n;
                }
                return null;
              }
              const n = Number(v);
              return Number.isFinite(n) ? n : null;
            };

            const pickRawPriceFromModel = (m) => {
              if (!m || typeof m !== "object") return null;
              const candidates = [
                m.price_after_discount,
                m.price,
                m.price_before_discount,
              ];
              for (const c of candidates) {
                const n = pickPriceValue(c);
                if (n != null) return n;
              }
              return null;
            };

            const path = String(variantPath || "").trim();
            const parts = path ? path.split("-").map((x) => x.trim()).filter(Boolean) : [];
            const idxFromPath = parts
              .map((x) => {
                const n = parseInt(x, 10);
                if (!Number.isFinite(n) || n <= 0) return null;
                return n - 1;
              })
              .filter((x) => x != null);

            const tierVars = Array.isArray(item.tier_variations) ? item.tier_variations : [];

            const idxFromGroups = (() => {
              const groups = Array.isArray(uiVariantGroups) ? uiVariantGroups : [];
              if (!path || !groups.length || !tierVars.length) return null;

              const uiIdx = parts
                .map((x) => {
                  const n = parseInt(x, 10);
                  if (!Number.isFinite(n) || n <= 0) return null;
                  return n - 1;
                })
                .filter((x) => x != null);
              if (!uiIdx.length) return null;

              const groupOptions = groups.map((g) => {
                const title = g && typeof g.title === "string" ? g.title : "";
                const titleKey = normalizeKey(title);
                const opts = Array.isArray(g && g.options) ? g.options : [];
                const set = new Set(opts.map(normalizeKey).filter(Boolean));
                return { titleKey, opts, set };
              });

              const tierOptions = tierVars.map((tv) => {
                const name = tv && typeof tv.name === "string" ? tv.name : "";
                const nameKey = normalizeKey(name);
                const opts = tv && Array.isArray(tv.options) ? tv.options : [];
                const set = new Set(opts.map(normalizeKey).filter(Boolean));
                return { nameKey, opts, set };
              });

              const titleScore = (a, b) => {
                if (!a || !b) return 0;
                if (a === b) return 2000;
                if (a.includes(b) || b.includes(a)) return 800;
                return 0;
              };

              const pairs = [];
              for (let gi = 0; gi < groupOptions.length; gi++) {
                for (let ti = 0; ti < tierOptions.length; ti++) {
                  let score = titleScore(groupOptions[gi].titleKey, tierOptions[ti].nameKey);
                  for (const k of groupOptions[gi].set) {
                    if (tierOptions[ti].set.has(k)) score++;
                  }
                  if (score > 0) pairs.push({ gi, ti, score });
                }
              }
              pairs.sort((a, b) => b.score - a.score);

              const groupToTier = new Array(groupOptions.length).fill(null);
              const usedTier = new Set();
              const usedGroup = new Set();
              for (const p of pairs) {
                if (usedTier.has(p.ti) || usedGroup.has(p.gi)) continue;
                groupToTier[p.gi] = p.ti;
                usedTier.add(p.ti);
                usedGroup.add(p.gi);
              }

              const selectionByTier = new Array(tierVars.length).fill(null);
              for (let gi = 0; gi < uiIdx.length && gi < groupOptions.length; gi++) {
                const ti = groupToTier[gi];
                if (ti == null) continue;
                const k = uiIdx[gi];
                const gOpts = groupOptions[gi].opts;
                if (!Array.isArray(gOpts) || k < 0 || k >= gOpts.length) continue;
                const wanted = normalizeKey(gOpts[k]);
                if (!wanted) continue;
                const tOpts = tierOptions[ti].opts;
                let found = -1;
                for (let j = 0; j < tOpts.length; j++) {
                  const cur = normalizeKey(tOpts[j]);
                  if (!cur) continue;
                  if (cur === wanted || cur.includes(wanted) || wanted.includes(cur)) {
                    found = j;
                    break;
                  }
                }
                if (found >= 0) selectionByTier[ti] = found;
              }

              return selectionByTier;
            })();
            const idxFromNames = [];
            const uiNames = Array.isArray(uiVariantNames) ? uiVariantNames : [];
            for (let i = 0; i < uiNames.length && i < tierVars.length; i++) {
              const wanted = normalizeTextLite(uiNames[i]);
              if (!wanted) continue;
              const tv = tierVars[i];
              const opts = tv && Array.isArray(tv.options) ? tv.options : [];
              const isNumber = /^\d+$/.test(wanted);
              let found = -1;
              for (let j = 0; j < opts.length; j++) {
                const o = normalizeTextLite(opts[j]);
                if (!o) continue;
                if (isNumber) {
                  if (o === wanted) { found = j; break; }
                } else {
                  if (o === wanted || o.includes(wanted) || wanted.includes(o)) { found = j; break; }
                }
              }
              if (found >= 0) {
                idxFromNames.push(found);
              } else {
                break;
              }
            }

            const idx = Array.isArray(idxFromGroups) && idxFromGroups.some((x) => x != null)
              ? idxFromGroups
              : (idxFromNames.length ? idxFromNames : idxFromPath);

            const variantClickNames = uiNames.length ? uiNames.map((x) => String(x || "").trim()).filter(Boolean) : [];
            if (!variantClickNames.length) {
              for (let i = 0; i < idx.length; i++) {
                const tv = tierVars[i];
                const opts = tv && Array.isArray(tv.options) ? tv.options : [];
                const opt = opts[idx[i]];
                if (typeof opt === "string" && opt.trim()) {
                  variantClickNames.push(opt.trim());
                }
              }
            }

            let usedVariantModel = false;
            let modelRawPrice = null;
            let modelTierIndex = null;
            let usedModelId = null;
            let variantIncomplete = false;
            if (Array.isArray(idxFromGroups) && idxFromGroups.some((x) => x != null)) {
              const required = tierVars
                .map((tv, i) => ({ i, n: (tv && Array.isArray(tv.options) ? tv.options.length : 0) }))
                .filter((x) => x.n > 1)
                .map((x) => x.i);
              variantIncomplete = required.some((ti) => idxFromGroups[ti] == null);
            } else if (idx.length && tierVars.length && idx.length < tierVars.length) {
              const remain = tierVars.slice(idx.length);
              variantIncomplete = remain.some((tv) => Array.isArray(tv && tv.options ? tv.options : null) && (tv.options || []).length > 1);
            }

            if (modelIdFromUrl) {
              const models = Array.isArray(item.models) ? item.models : [];
              const picked = models.find((m) => pickModelId(m) === Number(modelIdFromUrl));
              if (picked) {
                const raw = pickRawPriceFromModel(picked);
                if (raw != null) {
                  usedVariantModel = true;
                  modelRawPrice = raw;
                  usedModelId = pickModelId(picked);
                  modelTierIndex = Array.isArray(picked.tier_index) ? picked.tier_index.map((x) => Number(x)).filter((x) => Number.isFinite(x) && x >= 0) : null;
                }
              }
            } else if (idx.length && !variantIncomplete) {
              const models = Array.isArray(item.models) ? item.models : [];
              if (models.length) {
                const candidates = models
                  .map((mm) => {
                    const ti = Array.isArray(mm.tier_index) ? mm.tier_index : [];
                    if (ti.length < idx.length) return null;
                    if (Array.isArray(idxFromGroups) && idxFromGroups.some((x) => x != null)) {
                      for (let j = 0; j < idxFromGroups.length; j++) {
                        if (idxFromGroups[j] == null) continue;
                        if (Number(ti[j]) !== Number(idxFromGroups[j])) return null;
                      }
                    } else {
                      for (let i = 0; i < idx.length; i++) {
                        if (Number(ti[i]) !== Number(idx[i])) return null;
                      }
                    }
                    return { mm, ti };
                  })
                  .filter(Boolean);


                const lex = (a, b) => {
                  const al = a.ti.length;
                  const bl = b.ti.length;
                  const n = Math.min(al, bl);
                  for (let i = 0; i < n; i++) {
                    if (a.ti[i] !== b.ti[i]) return a.ti[i] - b.ti[i];
                  }
                  return al - bl;
                };

                candidates.sort(lex);

                const picked = candidates[0] ? candidates[0].mm : null;
                if (picked) {
                  const raw = pickRawPriceFromModel(picked);
                  if (raw != null) {
                    usedVariantModel = true;
                    modelRawPrice = raw;
                    usedModelId = pickModelId(picked);
                    modelTierIndex = Array.isArray(picked.tier_index) ? picked.tier_index.map((x) => Number(x)).filter((x) => Number.isFinite(x) && x >= 0) : null;
                  }
                }
              }
            }

            // Shopee API returns price as an integer.
            // Usually it's multiplied by 100,000 (e.g., 150,000 VND is 15,000,000,000)
            const rawPrice =
              (modelRawPrice != null ? modelRawPrice : null) ??
              (item.price_min != null ? item.price_min :
                item.price != null ? item.price :
                  item.price_max != null ? item.price_max :
                    item.price_min_before_discount != null ? item.price_min_before_discount :
                      null);

            if (rawPrice == null) {
              return { name, price: null, raw_text: "", used_variant_model: false, used_model_id: usedModelId, model_tier_index: modelTierIndex, variant_click_names: variantClickNames, variant_incomplete: variantIncomplete, tier_count: tierVars.length };
            }

            let p = Number(rawPrice);
            if (!Number.isFinite(p) || p <= 0) {
              return { name, price: null, raw_text: String(rawPrice), used_variant_model: usedVariantModel, used_model_id: usedModelId, model_tier_index: modelTierIndex, variant_click_names: variantClickNames, variant_incomplete: variantIncomplete, tier_count: tierVars.length };
            }

            // Smart normalization:
            // Shopee often returns raw prices multiplied (commonly *100000, sometimes *100).
            // Keep it heuristic-based to avoid returning 11000000000 as 11,000,000,000đ.
            if (p >= 1000000000) {
              p = Math.round(p / 100000);
            } else if (p >= 10000000) {
              p = Math.round(p / 100);
            } else if (p >= 1000000 && p % 100000 === 0) {
              p = p / 100000;
            }

            return { name, price: Math.trunc(p), raw_text: String(rawPrice), used_variant_model: usedVariantModel, used_model_id: usedModelId, model_tier_index: modelTierIndex, variant_click_names: variantClickNames, variant_incomplete: variantIncomplete, tier_count: tierVars.length };
          } catch (e) {
            return null;
          }
        } catch (e) { 
          return null;
        }
      }

      function detectBlock() {
        try {
          const href = String(location && location.href ? location.href : "");
          if (href.includes("/verify/traffic")) {
            return "Shopee chặn (verify/traffic).";
          }
        } catch (e) {}

        const html = document.documentElement ? document.documentElement.innerText || "" : "";
        const s = html.toLowerCase();
        if (s.includes("verify/traffic") || s.includes("unusual traffic")) {
          return "Shopee chặn (verify/traffic).";
        }
        if (s.includes("trang không khả dụng") || s.includes("page not available")) {
          return "Shopee chặn (trang không khả dụng).";
        }
        if (s.includes("captcha") || s.includes("xác minh") || s.includes("verify") || s.includes("unusual traffic")) {
          return "Shopee chặn (captcha/verify).";
        }
        if (s.includes("quay lại trang chủ") || s.includes("trở về trang chủ") || s.includes("something went wrong") || s.includes("đã xảy ra lỗi")) {
          return "Shopee báo lỗi và bắt quay về trang chủ.";
        }
        if (s.includes("enable cookies") || s.includes("cookies")) {
          return "Shopee yêu cầu cookies.";
        }
        return null;
      }

      async function waitForName(maxMs) {
        const deadline = Date.now() + maxMs;
        while (Date.now() < deadline) {
          const n = getName();
          if (n) return n;
          await sleep(500);
        }
        return getName();
      }

      async function waitForPrice(maxMs, opts) {
        const deadline = Date.now() + maxMs;
        const variantMode = !!(opts && opts.variantMode);
        const requireSingle = !!(opts && opts.requireSingle);
        while (Date.now() < deadline) {
          if (!variantMode) {
            const metaPrice = getPriceFromMeta();
            if (metaPrice != null) return metaPrice;

            const ldPrice = getPriceFromLd();
            if (ldPrice != null) return ldPrice;
          }

          const rangeText = getPriceTextFromShopeeRangeClass();
          const isRangeText = /(\s-\s|–|—|đến|to)/i.test(rangeText);
          const singleFromRangeText = getSinglePriceFromText(rangeText);
          const isRange = variantMode && isRangeText && singleFromRangeText == null;
          const rangePrice = getPriceFromShopeeRangeClass(!!preferMax);
          if (rangePrice != null && !isRange && (!requireSingle || singleFromRangeText != null)) return (singleFromRangeText != null ? singleFromRangeText : rangePrice);

          if (variantMode && isRange) {
            await sleep(450);
            continue;
          }

          const domPrice = getPriceFromDom();
          if (domPrice != null) return domPrice;

          const textPrice = getPriceFromText();
          if (textPrice != null) return textPrice;

          await sleep(1000);
        }
        return null;
      }

      return (async () => {
        // Force visibility state to trick lazy loading
        try {
          Object.defineProperty(document, 'visibilityState', { get: function() { return 'visible'; } });
          Object.defineProperty(document, 'hidden', { get: function() { return false; } });
          document.dispatchEvent(new Event('visibilitychange'));
        } catch (e) {}

        const blockReason = detectBlock();
        if (blockReason) {
          return {
            price: null,
            name: await waitForName(8000),
            raw_text: "",
            block_reason: blockReason
          };
        }

        const modelIdFromUrl = (() => {
          try {
            const sp = new URLSearchParams(location.search || "");
            const extra = sp.get("extraParams");
            if (!extra) return null;
            const parsed = JSON.parse(extra);
            const mid = parsed && parsed.display_model_id ? Number(parsed.display_model_id) : null;
            return Number.isFinite(mid) ? mid : null;
          } catch (e) {
            return null;
          }
        })();
        const hasDisplayModelId = modelIdFromUrl != null;

        const pickCapturedPrice = (() => {
          const pickModelId = (m) => {
            if (!m || typeof m !== "object") return null;
            const id =
              (m.modelid != null ? m.modelid : null) ??
              (m.model_id != null ? m.model_id : null) ??
              (m.modelId != null ? m.modelId : null) ??
              (m.id != null ? m.id : null) ??
              null;
            const n = Number(id);
            return Number.isFinite(n) ? n : null;
          };
          const pickPriceValue = (v) => {
            if (v == null) return null;
            if (Array.isArray(v)) {
              const nums = [];
              for (const x of v) {
                const n = pickPriceValue(x);
                if (n != null) nums.push(n);
              }
              if (!nums.length) return null;
              return nums.reduce((a, b) => (a > b ? a : b), nums[0]);
            }
            if (typeof v === "object") {
              const candidates = [
                v.price_after_discount,
                v.price,
                v.price_min,
                v.price_max,
                v.price_before_discount,
                v.price_min_before_discount,
              ];
              for (const c of candidates) {
                const n = pickPriceValue(c);
                if (n != null) return n;
              }
              return null;
            }
            const n = Number(v);
            return Number.isFinite(n) ? n : null;
          };
          const pickRawPriceFromModel = (m) => {
            if (!m || typeof m !== "object") return null;
            const candidates = [
              m.price_after_discount,
              m.price,
              m.price_before_discount,
            ];
            for (const c of candidates) {
              const n = pickPriceValue(c);
              if (n != null) return n;
            }
            return null;
          };
          const normalizeRaw = (raw) => {
            let p = Number(raw);
            if (!Number.isFinite(p) || p <= 0) return null;
            if (p >= 1000000000) {
              p = Math.round(p / 100000);
            } else if (p >= 10000000) {
              p = Math.round(p / 100);
            } else if (p >= 1000000 && p % 100000 === 0) {
              p = p / 100000;
            }
            return Math.trunc(p);
          };
          const extractItem = (json) => {
            return (
              (json && json.data && json.data.item ? json.data.item : null) ??
              (json && json.item ? json.item : null) ??
              (json && json.data && json.data.data && json.data.data.item ? json.data.data.item : null) ??
              null
            );
          };
          const getEntries = () => {
            try {
              const net = window.__checkgia && window.__checkgia.net ? window.__checkgia.net : null;
              const arr = net && Array.isArray(net.entries) ? net.entries : [];
              return arr.slice(-25);
            } catch (e) {
              return [];
            }
          };
          return () => {
            if (!hasDisplayModelId) return null;
            const entries = getEntries();
            for (let i = entries.length - 1; i >= 0; i--) {
              const e = entries[i];
              const item = extractItem(e && e.json ? e.json : null);
              if (!item) continue;
              const models = Array.isArray(item.models) ? item.models : [];
              const picked = models.find((m) => pickModelId(m) === Number(modelIdFromUrl));
              if (!picked) continue;
              const raw = pickRawPriceFromModel(picked);
              const price = normalizeRaw(raw);
              if (price != null) {
                return { price, name: item.name ? String(item.name).slice(0, 255) : null, raw_text: String(raw) };
              }
            }
            return null;
          };
        })();
        
        const captured = pickCapturedPrice();
        if (captured && captured.price != null) {
          return {
            price: captured.price,
            name: captured.name || await waitForName(8000),
            raw_text: captured.raw_text || "",
            block_reason: blockReason
          };
        }

        function getButtonLabel(btn) {
          if (!btn) return "";
          const aria = btn.getAttribute ? (btn.getAttribute("aria-label") || "") : "";
          const a = String(aria || "").trim();
          if (a) return a;
          const t = btn.textContent ? String(btn.textContent).trim() : "";
          return t;
        }

        function parseVariantPathIndices(path) {
          const s = String(path || "").trim();
          if (!s) return [];
          return s
            .split("-")
            .map((x) => x.trim())
            .filter(Boolean)
            .map((x) => {
              const n = parseInt(x, 10);
              if (!Number.isFinite(n) || n <= 0) return null;
              return n - 1;
            })
            .filter((x) => x != null);
        }

        function getVariantSections() {
          const sections = Array.from(document.querySelectorAll("section"));

          const clean = (s) => String(s || "").replace(/\s+/g, " ").trim().toLowerCase();
          const isQtyLabel = (t) => {
            const x = clean(t);
            return x.includes("số lượng") || x.includes("quantity");
          };

          const candidates = [];
          for (const sec of sections) {
            const h2 = sec.querySelector("h2");
            if (h2 && isQtyLabel(h2.textContent || "")) continue;

            const btns = Array.from(sec.querySelectorAll("button.sApkZm, button[class*='selection-box']"))
              .filter((b) => {
                if (!b) return false;
                const aria = clean(b.getAttribute("aria-label") || "");
                if (aria === "increase" || aria === "decrease") return false;
                if (b.closest(".shopee-input-quantity")) return false;
                if (b.disabled) return false;
                const ariaDisabled = b.getAttribute("aria-disabled");
                if (ariaDisabled && ariaDisabled !== "false") return false;
                return true;
              });

            if (btns.length >= 1) {
              candidates.push({ sec, btns });
            }
          }

          if (candidates.length) return candidates;

          const wraps = Array.from(document.querySelectorAll("div"))
            .filter((d) => d.querySelector("button.sApkZm, button[class*='selection-box']"));
          for (const w of wraps) {
            const btns = Array.from(w.querySelectorAll("button.sApkZm, button[class*='selection-box']"))
              .filter((b) => {
                if (!b) return false;
                const aria = clean(b.getAttribute("aria-label") || "");
                if (aria === "increase" || aria === "decrease") return false;
                if (b.closest(".shopee-input-quantity")) return false;
                if (b.disabled) return false;
                const ariaDisabled = b.getAttribute("aria-disabled");
                if (ariaDisabled && ariaDisabled !== "false") return false;
                return true;
              });
            if (btns.length >= 1) {
              candidates.push({ sec: w, btns });
            }
          }

          return candidates;
        }

        async function waitForVariantGroupsForPath(path) {
          const idx = parseVariantPathIndices(path);
          const deadline = Date.now() + 12000;
          let last = [];
          while (Date.now() < deadline) {
            const groups = getVariantSections();
            last = groups;
            if (!idx.length) return groups;
            if (groups.length >= idx.length) {
              let ok = true;
              for (let i = 0; i < idx.length; i++) {
                const k = idx[i];
                const list = groups[i] && Array.isArray(groups[i].btns) ? groups[i].btns : [];
                if (!(list.length && k < list.length)) {
                  ok = false;
                  break;
                }
              }
              if (ok) return groups;
            }
            await sleep(250);
          }
          return last;
        }

        function buildUiVariantGroups(groups) {
          const g = Array.isArray(groups) ? groups : [];
          return g.map((x) => {
            const titleEl = x && x.sec && x.sec.querySelector ? x.sec.querySelector("h2") : null;
            const title = titleEl && titleEl.textContent ? String(titleEl.textContent).trim() : "";
            const btns = x && Array.isArray(x.btns) ? x.btns : [];
            const options = btns.map(getButtonLabel).filter((t) => String(t || "").trim() !== "");
            return { title, options };
          });
        }

        function isVariantSelected(btn) {
          if (!btn) return false;
          const cls = btn.classList ? Array.from(btn.classList).join(" ") : "";
          if (cls.includes("selection-box-selected")) return true;
          if (cls.includes("selected")) return true;
          const ariaChecked = btn.getAttribute ? btn.getAttribute("aria-checked") : null;
          if (ariaChecked === "true") return true;
          const ariaPressed = btn.getAttribute ? btn.getAttribute("aria-pressed") : null;
          if (ariaPressed === "true") return true;
          return false;
        }

        function smartClick(el) {
          if (!el) return;
          try { el.focus && el.focus(); } catch (e) {}
          const rects = el.getClientRects && el.getClientRects();
          const rect = rects && rects.length ? rects[0] : null;
          const clientX = rect ? Math.floor(rect.left + Math.min(10, rect.width / 2)) : 1;
          const clientY = rect ? Math.floor(rect.top + Math.min(10, rect.height / 2)) : 1;
          try { el.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true, cancelable: true, pointerType: "mouse", clientX, clientY })); } catch (e) {}
          try { el.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, cancelable: true, clientX, clientY })); } catch (e) {}
          try { el.dispatchEvent(new PointerEvent("pointerup", { bubbles: true, cancelable: true, pointerType: "mouse", clientX, clientY })); } catch (e) {}
          try { el.dispatchEvent(new MouseEvent("mouseup", { bubbles: true, cancelable: true, clientX, clientY })); } catch (e) {}
          try { el.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, clientX, clientY })); } catch (e) {}
          try { el.click(); } catch (e) {}
        }

        async function collectVariantNamesByIndex(path) {
          const idx = parseVariantPathIndices(path);
          if (!idx.length) return [];
          const names = [];
          const deadline = Date.now() + 12000;
          for (let i = 0; i < idx.length; i++) {
            const k = idx[i];
            if (k == null || k < 0) continue;
            let btn = null;
            while (Date.now() < deadline) {
              const groups = getVariantSections();
              const group = groups[i];
              const list = group && Array.isArray(group.btns) ? group.btns : [];
              if (list.length && k < list.length) {
                btn = list[k];
                break;
              }
              await sleep(250);
            }
            if (!btn) break;
            const label = getButtonLabel(btn);
            if (!label) break;
            names.push(label);
          }
          return names;
        }

        const groupsForPath = variantPath ? await waitForVariantGroupsForPath(variantPath) : [];
        const uiVariantGroups = variantPath ? buildUiVariantGroups(groupsForPath) : [];
        const uiVariantNames = variantPath ? await collectVariantNamesByIndex(variantPath) : [];

        // Try API first
        const api = await fetchItemApi(variantPath, uiVariantNames, uiVariantGroups);
        if (!variantPath && api && api.price != null && (!hasDisplayModelId || api.used_variant_model)) {
          return { price: api.price, name: api.name ?? getName(), raw_text: api.raw_text || "", block_reason: blockReason };
        }

        async function applyVariantClicksByIndex(path) {
          const idx = parseVariantPathIndices(path);
          if (!idx.length) return false;

          let clickedAny = false;
          const deadline = Date.now() + 12000;
          for (let i = 0; i < idx.length; i++) {
            const k = idx[i];
            if (k == null || k < 0) continue;

            let el = null;
            while (Date.now() < deadline) {
              const groups = getVariantSections();
              const group = groups[i];
              const list = group && Array.isArray(group.btns) ? group.btns : [];
              if (list.length && k < list.length) {
                el = list[k];
                break;
              }
              await sleep(250);
            }
            if (!el) continue;

            clickedAny = true;
            try { el.scrollIntoView({ block: "center", inline: "center" }); } catch (e) {}
            smartClick(el);
            await sleep(Math.floor(Math.random() * 350) + 350);
            if (!isVariantSelected(el)) {
              smartClick(el);
              await sleep(Math.floor(Math.random() * 350) + 350);
            }
          }

          return clickedAny;
        }

        const variantIncompleteReason = (variantPath && api && api.variant_incomplete)
          ? `Chưa chọn đủ biến thể. Sản phẩm có ${api.tier_count || 0} nhóm biến thể, bạn nhập ${String(variantPath).split('-').filter(Boolean).length}.`
          : "";

        async function applyVariantClicksByTierIndex(tierIndex) {
          const idx = Array.isArray(tierIndex) ? tierIndex.map((x) => Number(x)).filter((x) => Number.isFinite(x) && x >= 0) : [];
          if (!idx.length) return false;

          let clickedAny = false;
          const deadline = Date.now() + 12000;
          for (let i = 0; i < idx.length; i++) {
            const k = idx[i];
            if (k == null || k < 0) continue;

            let el = null;
            while (Date.now() < deadline) {
              const groups = getVariantSections();
              const group = groups[i];
              const list = group && Array.isArray(group.btns) ? group.btns : [];
              if (list.length && k < list.length) {
                el = list[k];
                break;
              }
              await sleep(250);
            }
            if (!el) continue;

            clickedAny = true;
            try { el.scrollIntoView({ block: "center", inline: "center" }); } catch (e) {}
            smartClick(el);
            await sleep(Math.floor(Math.random() * 350) + 350);
            if (!isVariantSelected(el)) {
              smartClick(el);
              await sleep(Math.floor(Math.random() * 350) + 350);
            }
          }

          return clickedAny;
        }

        const apiTierIndex = api && Array.isArray(api.model_tier_index) ? api.model_tier_index : null;
        const apiModelId = api && api.used_model_id != null ? api.used_model_id : null;
        if (apiTierIndex && apiTierIndex.length) {
          const didClick = await applyVariantClicksByTierIndex(apiTierIndex);
          if (didClick) {
            await sleep(Math.floor(Math.random() * 500) + 600);
            const priceAfter = await waitForPrice(20000, { variantMode: true, requireSingle: true });
            if (priceAfter != null) {
              return {
                price: priceAfter,
                name: (api && api.name) ? api.name : await waitForName(8000),
                raw_text: api && api.raw_text ? String(api.raw_text) : "",
                block_reason: blockReason
              };
            }
          }
          if (api && api.price != null && api.used_variant_model) {
            return { price: api.price, name: api.name ?? getName(), raw_text: api.raw_text || "", block_reason: blockReason };
          }
          return { price: null, name: (api && api.name) ? api.name : getName(), raw_text: api && api.raw_text ? String(api.raw_text) : "", block_reason: blockReason || `Không lấy được giá theo model_id ${String(apiModelId || "")}` };
        }

        if (variantPath && api && api.price != null && api.used_variant_model) {
          return { price: api.price, name: api.name ?? getName(), raw_text: api.raw_text || "", block_reason: blockReason };
        }

        if (variantPath) {
          const didClick = await applyVariantClicksByIndex(variantPath);
          if (didClick) {
            await sleep(Math.floor(Math.random() * 500) + 600);
            const priceAfter = await waitForPrice(20000, { variantMode: true, requireSingle: true });
            if (priceAfter != null) {
              return {
                price: priceAfter,
                name: (api && api.name) ? api.name : await waitForName(8000),
                raw_text: api && api.raw_text ? String(api.raw_text) : "",
                block_reason: blockReason
              };
            }
          }
        }

        // If API fails, wait for DOM to render (up to 15 seconds)
        const price = await waitForPrice(25000, { variantMode: !!variantPath || hasDisplayModelId, requireSingle: hasDisplayModelId || !!variantPath });
        const name = (api && api.name) ? api.name : await waitForName(8000);
        
        return { 
          price, 
          name, 
          raw_text: price != null ? String(price) : (api && api.raw_text ? api.raw_text : ""), 
          block_reason: blockReason || variantIncompleteReason
        };
      })();
    }
  });

  return result || null;
}

async function readCapturedModelPrice(tabId, url) {
  const u = String(url || "");
  const m = u.match(/"display_model_id"%3A(\d+)/i) || u.match(/display_model_id(?:%22)?%3A(\d+)/i);
  const m2 = u.match(/display_model_id[=:](\d+)/i);
  const modelId = m ? Number(m[1]) : (m2 ? Number(m2[1]) : null);
  if (!Number.isFinite(modelId) || modelId <= 0) return null;

  try {
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId },
      world: "MAIN",
      args: [modelId],
      func: async (modelId) => {
        const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
        const pickModelId = (m) => {
          if (!m || typeof m !== "object") return null;
          const id =
            (m.modelid != null ? m.modelid : null) ??
            (m.model_id != null ? m.model_id : null) ??
            (m.modelId != null ? m.modelId : null) ??
            (m.id != null ? m.id : null) ??
            null;
          const n = Number(id);
          return Number.isFinite(n) ? n : null;
        };
        const pickPriceValue = (v) => {
          if (v == null) return null;
          if (Array.isArray(v)) {
            const nums = [];
            for (const x of v) {
              const n = pickPriceValue(x);
              if (n != null) nums.push(n);
            }
            if (!nums.length) return null;
            return nums.reduce((a, b) => (a > b ? a : b), nums[0]);
          }
          if (typeof v === "object") {
            const candidates = [
              v.price_after_discount,
              v.price,
              v.price_min,
              v.price_max,
              v.price_before_discount,
              v.price_min_before_discount,
            ];
            for (const c of candidates) {
              const n = pickPriceValue(c);
              if (n != null) return n;
            }
            return null;
          }
          const n = Number(v);
          return Number.isFinite(n) ? n : null;
        };
        const pickRawPriceFromModel = (m) => {
          if (!m || typeof m !== "object") return null;
          const candidates = [
            m.price_after_discount,
            m.price,
            m.price_before_discount,
          ];
          for (const c of candidates) {
            const n = pickPriceValue(c);
            if (n != null) return n;
          }
          return null;
        };
        const normalizeRaw = (raw) => {
          let p = Number(raw);
          if (!Number.isFinite(p) || p <= 0) return null;
          if (p >= 1000000000) {
            p = Math.round(p / 100000);
          } else if (p >= 10000000) {
            p = Math.round(p / 100);
          } else if (p >= 1000000 && p % 100000 === 0) {
            p = p / 100000;
          }
          return Math.trunc(p);
        };
        const extractItem = (json) => {
          return (
            (json && json.data && json.data.item ? json.data.item : null) ??
            (json && json.item ? json.item : null) ??
            (json && json.data && json.data.data && json.data.data.item ? json.data.data.item : null) ??
            null
          );
        };

        const deadline = Date.now() + 12000;
        while (Date.now() < deadline) {
          try {
            const net = window.__checkgia && window.__checkgia.net ? window.__checkgia.net : null;
            const entries = net && Array.isArray(net.entries) ? net.entries.slice(-25) : [];
            for (let i = entries.length - 1; i >= 0; i--) {
              const item = extractItem(entries[i] && entries[i].json ? entries[i].json : null);
              if (!item) continue;
              const models = Array.isArray(item.models) ? item.models : [];
              const picked = models.find((m) => pickModelId(m) === Number(modelId));
              if (!picked) continue;
              const raw = pickRawPriceFromModel(picked);
              const price = normalizeRaw(raw);
              if (price != null) {
                return {
                  price,
                  name: item.name ? String(item.name).slice(0, 255) : null,
                  raw_text: raw != null ? String(raw) : ""
                };
              }
            }
          } catch (e) {}
          await sleep(300);
        }
        return null;
      }
    });

    return result || null;
  } catch (e) {
    return null;
  }
}

async function readDirectModelPrice(tabId, url) {
  const u = String(url || "");
  const m = u.match(/"display_model_id"%3A(\d+)/i) || u.match(/display_model_id(?:%22)?%3A(\d+)/i);
  const m2 = u.match(/display_model_id[=:](\d+)/i);
  const modelId = m ? Number(m[1]) : (m2 ? Number(m2[1]) : null);
  const ids = u.match(/i\.(\d+)\.(\d+)/);
  const shopId = ids ? Number(ids[1]) : null;
  const itemId = ids ? Number(ids[2]) : null;
  if (!Number.isFinite(modelId) || modelId <= 0) return null;
  if (!Number.isFinite(shopId) || shopId <= 0) return null;
  if (!Number.isFinite(itemId) || itemId <= 0) return null;

  try {
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId },
      world: "MAIN",
      args: [shopId, itemId, modelId],
      func: async (shopId, itemId, modelId) => {
      const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

      const pickModelId = (m) => {
        if (!m || typeof m !== "object") return null;
        const id =
          (m.modelid != null ? m.modelid : null) ??
          (m.model_id != null ? m.model_id : null) ??
          (m.modelId != null ? m.modelId : null) ??
          (m.id != null ? m.id : null) ??
          null;
        const n = Number(id);
        return Number.isFinite(n) ? n : null;
      };

      const pickPriceValue = (v) => {
        if (v == null) return null;
        if (Array.isArray(v)) {
          const nums = [];
          for (const x of v) {
            const n = pickPriceValue(x);
            if (n != null) nums.push(n);
          }
          if (!nums.length) return null;
          return nums.reduce((a, b) => (a > b ? a : b), nums[0]);
        }
        if (typeof v === "object") {
          const candidates = [
            v.price_after_discount,
            v.price,
            v.price_min,
            v.price_max,
            v.price_before_discount,
            v.price_min_before_discount,
          ];
          for (const c of candidates) {
            const n = pickPriceValue(c);
            if (n != null) return n;
          }
          return null;
        }
        const n = Number(v);
        return Number.isFinite(n) ? n : null;
      };

      const pickRawPriceFromModel = (m) => {
        if (!m || typeof m !== "object") return null;
        const candidates = [
          m.price_after_discount,
          m.price,
          m.price_before_discount,
        ];
        for (const c of candidates) {
          const n = pickPriceValue(c);
          if (n != null) return n;
        }
        return null;
      };

      const normalizeRaw = (raw) => {
        let p = Number(raw);
        if (!Number.isFinite(p) || p <= 0) return null;
        if (p >= 1000000000) {
          p = Math.round(p / 100000);
        } else if (p >= 10000000) {
          p = Math.round(p / 100);
        } else if (p >= 1000000 && p % 100000 === 0) {
          p = p / 100000;
        }
        return Math.trunc(p);
      };

      const extractItem = (json) => {
        return (
          (json && json.data && json.data.item ? json.data.item : null) ??
          (json && json.item ? json.item : null) ??
          (json && json.data && json.data.data && json.data.data.item ? json.data.data.item : null) ??
          null
        );
      };

      const urls = [
        `/api/v4/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}`,
        `/api/v2/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}`,
        `/api/v4/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}&selected_model_id=${encodeURIComponent(String(modelId))}`,
        `/api/v4/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}&modelid=${encodeURIComponent(String(modelId))}`,
        `/api/v4/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}&display_model_id=${encodeURIComponent(String(modelId))}`,
        `/api/v2/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}&selected_model_id=${encodeURIComponent(String(modelId))}`,
        `/api/v2/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}&modelid=${encodeURIComponent(String(modelId))}`,
        `/api/v2/item/get?shopid=${encodeURIComponent(String(shopId))}&itemid=${encodeURIComponent(String(itemId))}&display_model_id=${encodeURIComponent(String(modelId))}`,
      ];

      const fetchJson = async (url) => {
        try {
          const res = await fetch(url, {
            credentials: "include",
            headers: {
              "accept": "application/json",
              "x-api-source": "pc",
              "x-requested-with": "XMLHttpRequest"
            }
          });
          if (!res.ok) return null;
          return await res.json().catch(() => null);
        } catch (e) {
          return null;
        }
      };

      const deadline = Date.now() + 12000;
      while (Date.now() < deadline) {
        for (const u of urls) {
          const json = await fetchJson(u);
          const item = extractItem(json);
          if (!item) continue;
          const models = Array.isArray(item.models) ? item.models : [];
          const picked = models.find((m) => pickModelId(m) === Number(modelId));
          if (!picked) continue;
          const raw = pickRawPriceFromModel(picked);
          const price = normalizeRaw(raw);
          if (price != null) {
            return {
              price,
              name: item.name ? String(item.name).slice(0, 255) : null,
              raw_text: raw != null ? String(raw) : ""
            };
          }
        }
        await sleep(300);
      }
      return null;
      }
    });

    return result || null;
  } catch (e) {
    return null;
  }
}

function extractShopeeIdsFromUrl(url) {
  const u = String(url || "");

  const ids1 = u.match(/i\.(\d+)\.(\d+)/);
  const ids2 = u.match(/\/product\/(\d+)\/(\d+)/i);
  const shopId = ids1 ? Number(ids1[1]) : (ids2 ? Number(ids2[1]) : null);
  const itemId = ids1 ? Number(ids1[2]) : (ids2 ? Number(ids2[2]) : null);

  const m = u.match(/"display_model_id"%3A(\d+)/i) || u.match(/display_model_id(?:%22)?%3A(\d+)/i);
  const m2 = u.match(/display_model_id[=:](\d+)/i);
  const modelId = m ? Number(m[1]) : (m2 ? Number(m2[1]) : null);

  return {
    shopId: Number.isFinite(shopId) ? shopId : null,
    itemId: Number.isFinite(itemId) ? itemId : null,
    modelId: Number.isFinite(modelId) ? modelId : null,
  };
}

function normalizeShopeeRawPrice(raw) {
  let p = Number(raw);
  if (!Number.isFinite(p) || p <= 0) return null;
  if (p >= 1000000000) {
    p = Math.round(p / 100000);
  } else if (p >= 10000000) {
    p = Math.round(p / 100);
  } else if (p >= 1000000 && p % 100000 === 0) {
    p = p / 100000;
  }
  return Math.trunc(p);
}

function extractItemFromApiJson(json) {
  return (
    (json && json.data && json.data.item ? json.data.item : null) ??
    (json && json.item ? json.item : null) ??
    (json && json.data && json.data.data && json.data.data.item ? json.data.data.item : null) ??
    null
  );
}

function pickModelId(model) {
  if (!model || typeof model !== "object") return null;
  const id =
    (model.modelid != null ? model.modelid : null) ??
    (model.model_id != null ? model.model_id : null) ??
    (model.modelId != null ? model.modelId : null) ??
    (model.id != null ? model.id : null) ??
    null;
  const n = Number(id);
  return Number.isFinite(n) ? n : null;
}

function pickPriceValue(v) {
  if (v == null) return null;
  if (Array.isArray(v)) {
    const nums = [];
    for (const x of v) {
      const n = pickPriceValue(x);
      if (n != null) nums.push(n);
    }
    if (!nums.length) return null;
    return nums.reduce((a, b) => (a > b ? a : b), nums[0]);
  }
  if (typeof v === "object") {
    const candidates = [
      v.price_after_discount,
      v.price,
      v.price_min,
      v.price_max,
      v.price_before_discount,
      v.price_min_before_discount,
      v.price_max_before_discount,
    ];
    for (const c of candidates) {
      const n = pickPriceValue(c);
      if (n != null) return n;
    }
    return null;
  }
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function pickRawPriceFromModel(model) {
  if (!model || typeof model !== "object") return null;
  const candidates = [
    model.price_after_discount,
    model.price,
    model.price_before_discount,
  ];
  for (const c of candidates) {
    const n = pickPriceValue(c);
    if (n != null) return n;
  }
  return null;
}

function pickNameFromItem(item) {
  if (!item || typeof item !== "object") return null;
  const n = item.name != null ? String(item.name).trim() : "";
  return n ? n.slice(0, 255) : null;
}

function pickPriceFromItem(item, modelId, preferMax) {
  if (!item || typeof item !== "object") return null;

  if (modelId != null && Number.isFinite(modelId) && modelId > 0) {
    const models = Array.isArray(item.models) ? item.models : [];
    const picked = models.find((m) => pickModelId(m) === Number(modelId));
    if (picked) {
      const raw = pickRawPriceFromModel(picked);
      const price = normalizeShopeeRawPrice(raw);
      if (price != null) return { price, raw_text: raw != null ? String(raw) : "" };
    }
  }

  const candidates = [
    item.price_after_discount,
    item.price,
    item.price_min,
    item.price_max,
    item.price_before_discount,
    item.price_min_before_discount,
    item.price_max_before_discount,
  ]
    .map((x) => normalizeShopeeRawPrice(pickPriceValue(x)))
    .filter((x) => x != null);

  if (!candidates.length) return null;
  const price = preferMax ? Math.max(...candidates) : Math.min(...candidates);
  return { price, raw_text: String(price) };
}

async function tryFetchPriceViaApi(url, preferMax) {
  const ids = extractShopeeIdsFromUrl(url);
  if (!ids.shopId || !ids.itemId) return null;

  const base = "https://shopee.vn";
  const candidates = [
    `${base}/api/v4/item/get?shopid=${encodeURIComponent(String(ids.shopId))}&itemid=${encodeURIComponent(String(ids.itemId))}&x_api_source=pc`,
    `${base}/api/v2/item/get?shopid=${encodeURIComponent(String(ids.shopId))}&itemid=${encodeURIComponent(String(ids.itemId))}`,
  ];

  for (const apiUrl of candidates) {
    try {
      const res = await fetch(apiUrl, {
        credentials: "include",
        headers: {
          accept: "application/json",
          "x-api-source": "pc",
          "x-requested-with": "XMLHttpRequest",
        },
      });

      if (!res.ok) {
        const txt = await res.text().catch(() => "");
        const low = String(txt || "").toLowerCase();
        if (low.includes("verify/traffic") || low.includes("unusual traffic") || low.includes("captcha") || low.includes("xác minh")) {
          return { price: null, name: null, raw_text: "", block_reason: "Shopee chặn (verify/traffic)." };
        }
        continue;
      }

      const json = await res.json().catch(() => null);
      const item = extractItemFromApiJson(json);
      if (!item) continue;

      const picked = pickPriceFromItem(item, ids.modelId, preferMax);
      if (picked && picked.price != null) {
        return {
          price: picked.price,
          name: pickNameFromItem(item),
          raw_text: picked.raw_text || "",
          block_reason: "",
        };
      }
    } catch (e) {}
  }

  return null;
}

async function openAndScrape(url, variantPath, pricePick) {
  try {
    const sleepMs = (ms) => new Promise((r) => setTimeout(r, ms));
    const isRetryableError = (e) => {
      const msg = String(e && e.message ? e.message : e || "");
      if (/tabs cannot be edited right now/i.test(msg)) return true;
      if (/dragging a tab/i.test(msg)) return true;
      if (/frame with id/i.test(msg) && /was removed/i.test(msg)) return true;
      if (/the frame was removed/i.test(msg)) return true;
      return false;
    };
    const withRetry = async (fn, attempts = 12, baseDelayMs = 200) => {
      let lastErr = null;
      for (let i = 0; i < attempts; i++) {
        try {
          return await fn();
        } catch (e) {
          lastErr = e;
          if (!isRetryableError(e)) throw e;
          await sleepMs(baseDelayMs + i * baseDelayMs);
        }
      }
      if (lastErr) throw lastErr;
      return null;
    };

    const preferMax = String(pricePick || "low").toLowerCase() === "high";
    const apiFirst = await tryFetchPriceViaApi(url, preferMax).catch(() => null);
    if (apiFirst && (apiFirst.price != null || apiFirst.block_reason)) {
      return apiFirst;
    }
    const needsVariant = !!(variantPath || (typeof url === "string" && url.includes("extraParams=") && url.includes("display_model_id")));
    const tab = await withRetry(() => chrome.tabs.create({ url, active: true }));
    const tabId = tab.id;
    if (!tabId) return null;

    let tabClosed = false;
    const onRemoved = (id) => {
      if (id !== tabId) return;
      tabClosed = true;
      chrome.tabs.onRemoved.removeListener(onRemoved);
    };
    chrome.tabs.onRemoved.addListener(onRemoved);

    const ensureTabExists = async () => {
      if (tabClosed) return false;
      try {
        await chrome.tabs.get(tabId);
        return true;
      } catch (e) {
        tabClosed = true;
        return false;
      }
    };

    const safeReturnTabClosed = () => {
      return {
        price: null,
        name: null,
        raw_text: "",
        block_reason: "Tab đã bị đóng hoặc trang bị chặn trước khi tải xong."
      };
    };

    const injectMainWorldHelpers = async () => {
      if (!(await ensureTabExists())) return;
      await chrome.scripting.executeScript({
        target: { tabId },
        world: 'MAIN',
        args: [needsVariant],
        func: (needsVariant) => {
          try {
            if (!window.__checkgia) window.__checkgia = {};
            if (!window.__checkgia.net) {
              window.__checkgia.net = { entries: [], max: 25 };

              const shouldCapture = (u) => {
                const s = String(u || "");
                return s.includes("/api/") && s.includes("/item/") && s.includes("/get");
              };
              const pushEntry = (url, json) => {
                try {
                  const net = window.__checkgia.net;
                  const arr = Array.isArray(net.entries) ? net.entries : [];
                  arr.push({ url: String(url || ""), ts: Date.now(), json });
                  while (arr.length > (net.max || 25)) arr.shift();
                  net.entries = arr;
                } catch (e) {}
              };

              try {
                const origFetch = window.fetch;
                if (typeof origFetch === "function") {
                  window.fetch = async function (...args) {
                    const res = await origFetch.apply(this, args);
                    try {
                      const u = (args && args[0]) ? (typeof args[0] === "string" ? args[0] : (args[0] && args[0].url ? args[0].url : "")) : "";
                      if (shouldCapture(u)) {
                        const cloned = res.clone();
                        cloned.json().then((j) => pushEntry(u, j)).catch(() => {});
                      }
                    } catch (e) {}
                    return res;
                  };
                }
              } catch (e) {}

              try {
                const origOpen = XMLHttpRequest.prototype.open;
                const origSend = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.open = function (method, url) {
                  try { this.__checkgia_url = url; } catch (e) {}
                  return origOpen.apply(this, arguments);
                };
                XMLHttpRequest.prototype.send = function () {
                  try {
                    this.addEventListener("load", () => {
                      try {
                        const u = this.__checkgia_url || "";
                        if (!shouldCapture(u)) return;
                        const txt = this.responseText;
                        if (!txt || typeof txt !== "string") return;
                        if (txt[0] !== "{" && txt[0] !== "[") return;
                        const j = JSON.parse(txt);
                        pushEntry(u, j);
                      } catch (e) {}
                    });
                  } catch (e) {}
                  return origSend.apply(this, arguments);
                };
              } catch (e) {}
            }

            // Force visibility state
            Object.defineProperty(document, 'visibilityState', { get: () => 'visible', configurable: true });
            Object.defineProperty(document, 'hidden', { get: () => false, configurable: true });
            document.hasFocus = () => true;

            // Avoid aggressive DOM mutations (can trigger anti-bot / break captcha). Only apply low-risk hints.
            try {
              document.querySelectorAll('img').forEach((img) => {
                try {
                  img.loading = 'lazy';
                  img.decoding = 'async';
                  img.fetchPriority = 'low';
                } catch (e) {}
              });
            } catch (e) {}

            if (!needsVariant) {
              const scrollInterval = setInterval(() => {
                const scrollAmount = Math.floor(Math.random() * 140) + 60;
                window.scrollBy({ top: scrollAmount, behavior: 'smooth' });
                if (window.innerHeight + window.scrollY >= document.body.offsetHeight) {
                  clearInterval(scrollInterval);
                }
              }, Math.random() * 2500 + 1400);
              setTimeout(() => clearInterval(scrollInterval), 12000);
            }
          } catch (e) {}
        }
      });
    };

    // Try once early (best-effort), then re-inject after page load to ensure hook is active.
    try { await withRetry(() => injectMainWorldHelpers(), 3, 150); } catch (e) {}

    const scrapeOnceFast = async () => {
      if (!(await ensureTabExists())) return null;
      const [{ result }] = await chrome.scripting.executeScript({
        target: { tabId },
        args: [!!preferMax],
        func: (preferMax) => {
          function normalizePriceNumber(text) {
            if (text == null || text === "") return null;
            let s = String(text).trim();
            if (s.includes(".") && !s.includes(",")) {
              const parts = s.split(".");
              if (parts.length === 2 && parts[1].length !== 3) {
                const n = parseFloat(s);
                return Number.isFinite(n) ? Math.trunc(n) : null;
              }
            }
            s = s.replace(/[^\d]/g, "");
            if (!s) return null;
            const n = Number(s);
            if (!Number.isFinite(n)) return null;
            return Math.max(0, Math.trunc(n));
          }

          function detectBlock() {
            const t = (document && document.body && document.body.innerText) ? String(document.body.innerText) : "";
            const s = t.toLowerCase();
            if (s.includes("trang không khả dụng") || s.includes("page not available")) return "Shopee chặn (trang không khả dụng).";
            if (s.includes("captcha") || s.includes("xác minh") || s.includes("verify") || s.includes("unusual traffic")) return "Shopee chặn (captcha/verify).";
            if (s.includes("enable cookies") || s.includes("cookies")) return "Shopee yêu cầu cookies.";
            if (s.includes("quay lại trang chủ") || s.includes("trở về trang chủ") || s.includes("trang chủ")) {
              if (s.includes("lỗi") || s.includes("error") || s.includes("something went wrong") || s.includes("đã xảy ra lỗi")) {
                return "Shopee báo lỗi và bắt quay về trang chủ.";
              }
            }
            if (s.includes("something went wrong") || s.includes("đã xảy ra lỗi")) return "Shopee báo lỗi (Something went wrong).";
            return null;
          }

          function pickFromMeta() {
            const metas = [
              'meta[itemprop="price"]',
              'meta[property="product:price:amount"]',
              'meta[property="og:price:amount"]',
              'meta[name="twitter:data1"]'
            ];
            for (const sel of metas) {
              const el = document.querySelector(sel);
              const c = el ? (el.getAttribute("content") || el.getAttribute("value") || "") : "";
              const n = normalizePriceNumber(c);
              if (n != null) return { price: n, raw_text: c };
            }
            return null;
          }

          function pickFromLdJson() {
            const nodes = Array.from(document.querySelectorAll('script[type="application/ld+json"]')).slice(0, 10);
            for (const n of nodes) {
              const txt = n && n.textContent ? String(n.textContent) : "";
              if (!txt || txt.length > 300000) continue;
              try {
                const json = JSON.parse(txt);
                const arr = Array.isArray(json) ? json : [json];
                for (const obj of arr) {
                  if (!obj || typeof obj !== "object") continue;
                  const offers = obj.offers || obj.Offers;
                  if (!offers) continue;
                  const o = Array.isArray(offers) ? offers[0] : offers;
                  const p = o && (o.price || (preferMax ? o.highPrice : o.lowPrice) || o.lowPrice || o.highPrice);
                  const nn = normalizePriceNumber(p);
                  if (nn != null) return { price: nn, raw_text: String(p) };
                }
              } catch (e) {}
            }
            return null;
          }

          function extractFromText() {
            const t = (document && document.body && document.body.innerText) ? String(document.body.innerText) : "";
            if (!t) return null;
            const re = /(?:₫\s*)?(\d{1,3}(?:[.,\s]\d{3})+|\d+)(?:\s*(?:₫|đ|k))?/gi;
            const cands = [];
            let m;
            while ((m = re.exec(t)) !== null) {
              const raw = m[0] || "";
              const numPart = m[1] || "";
              const nextChar = t.charAt(re.lastIndex);
              if (nextChar === '+') continue;
              let value = normalizePriceNumber(numPart);
              if (value == null) continue;
              const hasCurrency = /₫|đ|vnđ/i.test(raw);
              if (raw.toLowerCase().endsWith("k")) {
                const context = t.toLowerCase();
                if (context.includes("đã bán") || context.includes("sold") || context.includes("đánh giá") || context.includes("rating")) continue;
                if (value < 10000) value *= 1000;
              }
              if (value < 500 || value > 1e12) continue;
              if (!hasCurrency) {
                const beforeMatch = t.substring(Math.max(0, m.index - 12), m.index).toLowerCase();
                if (beforeMatch.includes("đã bán") || beforeMatch.includes("sold") || beforeMatch.includes("đánh giá")) continue;
              }
              cands.push({ value, raw, hasCurrency });
            }
            if (!cands.length) return null;
            const counts = new Map();
            for (const c of cands) counts.set(c.value, (counts.get(c.value) || 0) + 1);
            cands.sort((a, b) => {
              const ca = counts.get(a.value) || 0;
              const cb = counts.get(b.value) || 0;
              const sa = (a.hasCurrency ? 6 : 0) + (a.value >= 10000 ? 2 : 0) + Math.min(5, ca) + (a.value < 10000 && !a.hasCurrency ? -5 : 0);
              const sb = (b.hasCurrency ? 6 : 0) + (b.value >= 10000 ? 2 : 0) + Math.min(5, cb) + (b.value < 10000 && !b.hasCurrency ? -5 : 0);
              if (sb !== sa) return sb - sa;
              if (cb !== ca) return cb - ca;
              return preferMax ? (b.value - a.value) : (a.value - b.value);
            });
            return { price: cands[0].value, raw_text: cands[0].raw };
          }

          function getName() {
            const og = document.querySelector('meta[property="og:title"]');
            if (og && og.getAttribute("content")) {
              let c = String(og.getAttribute("content") || "").trim();
              c = c.replace(/\s+-\s+Shopee.*$/i, "").replace(/\s*\|\s*Shopee.*$/i, "").trim();
              if (c && !c.toLowerCase().includes("shopee việt nam")) return c;
            }
            const h1 = document.querySelector("h1");
            const t = h1 && h1.textContent ? String(h1.textContent).trim() : "";
            return t || null;
          }

          const blockReason = detectBlock();
          if (blockReason) {
            return { price: null, name: getName(), raw_text: "", block_reason: blockReason };
          }

          const meta = pickFromMeta();
          if (meta && meta.price != null) return { price: meta.price, name: getName(), raw_text: meta.raw_text || "", block_reason: "" };
          const ld = pickFromLdJson();
          if (ld && ld.price != null) return { price: ld.price, name: getName(), raw_text: ld.raw_text || "", block_reason: "" };
          const txt = extractFromText();
          if (txt && txt.price != null) return { price: txt.price, name: getName(), raw_text: txt.raw_text || "", block_reason: "" };
          return { price: null, name: getName(), raw_text: "", block_reason: "" };
        }
      });

      return result || null;
    };

    const quickDeadline = Date.now() + 9000;
    while (Date.now() < quickDeadline) {
      if (!(await ensureTabExists())) return safeReturnTabClosed();
      const quick = await scrapeOnceFast().catch(() => null);
      if (quick && quick.block_reason) {
        await withRetry(() => chrome.tabs.remove(tabId)).catch(() => {});
        return quick;
      }
      if (quick && quick.price != null) {
        await withRetry(() => chrome.tabs.remove(tabId)).catch(() => {});
        return quick;
      }
      await sleepMs(350);
    }

    // Wait for tab to complete loading
    await new Promise((resolve) => {
      const timeout = setTimeout(resolve, 25000);
      let interval = null;
      function onUpdated(id, info) {
        if (id !== tabId) return;
        if (info.status === "complete") {
          if (interval) clearInterval(interval);
          chrome.tabs.onUpdated.removeListener(onUpdated);
          clearTimeout(timeout);
          resolve();
        }
      }
      chrome.tabs.onUpdated.addListener(onUpdated);

      interval = setInterval(() => {
        if (!tabClosed) return;
        clearInterval(interval);
        chrome.tabs.onUpdated.removeListener(onUpdated);
        clearTimeout(timeout);
        resolve();
      }, 500);

      if (tabClosed) {
        clearInterval(interval);
        chrome.tabs.onUpdated.removeListener(onUpdated);
        clearTimeout(timeout);
        resolve();
      }
    });

    if (!(await ensureTabExists())) {
      return safeReturnTabClosed();
    }

    try { await withRetry(() => injectMainWorldHelpers(), 5, 200); } catch (e) {}

    await new Promise(r => setTimeout(r, Math.random() * 250 + 80));

    if (!(await ensureTabExists())) {
      return safeReturnTabClosed();
    }

    const direct = await readDirectModelPrice(tabId, url).catch(() => null);
    if (direct && direct.price != null) {
      const stayTime = Math.random() * 1600 + 700;
      await new Promise(r => setTimeout(r, stayTime));
      await withRetry(() => chrome.tabs.remove(tabId)).catch(() => {});
      return { price: direct.price, name: direct.name, raw_text: direct.raw_text, block_reason: "" };
    }

    if (!(await ensureTabExists())) {
      return safeReturnTabClosed();
    }

    const captured = await readCapturedModelPrice(tabId, url).catch(() => null);
    if (captured && captured.price != null) {
      const stayTime = Math.random() * 2000 + 800;
      await new Promise(r => setTimeout(r, stayTime));
      await withRetry(() => chrome.tabs.remove(tabId)).catch(() => {});
      return { price: captured.price, name: captured.name, raw_text: captured.raw_text, block_reason: "" };
    }

    // Scrape the tab
    if (!(await ensureTabExists())) {
      return safeReturnTabClosed();
    }

    const res = await scrapeTab(tabId, variantPath, preferMax).catch((e) => {
      const msg = String(e && e.message ? e.message : e || "");
      if (/no tab with id/i.test(msg)) {
        tabClosed = true;
        return safeReturnTabClosed();
      }
      throw e;
    });
    
    await new Promise(r => setTimeout(r, Math.random() * 500 + 150));

    // Close the tab
    await withRetry(() => chrome.tabs.remove(tabId)).catch(() => {});
    
    return res;
  } catch (e) {
    const msg = String(e && e.message ? e.message : e || "");
    if (/no tab with id/i.test(msg)) {
      return {
        price: null,
        name: null,
        raw_text: "",
        block_reason: "Tab đã bị đóng hoặc trang bị chặn trước khi tải xong."
      };
    }
    console.error("openAndScrape error:", e);
    return null;
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
  const scrape = await openAndScrape(task.url, null, task.price_pick || "low").catch((e) => {
    setLastError(e);
    return null;
  });
  
  const price = scrape && scrape.price != null ? Number(scrape.price) : null;
  const name = scrape && scrape.name ? String(scrape.name).slice(0, 255) : null;
  const rawText = scrape && scrape.raw_text ? String(scrape.raw_text).slice(0, 20000) : null;
  const blockReason = scrape && scrape.block_reason ? String(scrape.block_reason) : "";

  let nextSleep = sleepSeconds || 60;
  if (price != null && Number.isFinite(price) && price > 0) {
    const payload = {
      agent_key: agentKey,
      task_type: String(task.type || ""),
      lease_token: task.lease_token ? String(task.lease_token) : undefined,
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
    let errorMsg = blockReason || "Không tìm thấy giá trên trang";
    if (!scrape) {
      errorMsg = "Lỗi trình duyệt khi mở trang";
    } else if (scrape.price === null && !blockReason) {
      errorMsg = "Trang đã tải nhưng không nhận diện được giá";
    }

    await chrome.storage.local.set({
      lastError: errorMsg,
      lastTaskUrl: task.url
    });

    const low = String(errorMsg || "").toLowerCase();
    if (low.includes("verify/traffic") || low.includes("captcha") || low.includes("xác minh") || low.includes("unusual traffic")) {
      nextSleep = Math.max(nextSleep, 600);
    }
  }

  await scheduleNext(nextSleep);
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
