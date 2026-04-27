<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PriceScraper
{
    public int $timeoutSeconds;

    public int $connectTimeoutSeconds;

    public function __construct(?int $timeoutSeconds = null, ?int $connectTimeoutSeconds = null)
    {
        $this->timeoutSeconds = max(1, (int) ($timeoutSeconds ?? 7));
        $this->connectTimeoutSeconds = max(1, (int) ($connectTimeoutSeconds ?? 7));
    }

    private function pendingRequest()
    {
        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->connectTimeout($this->connectTimeoutSeconds)->timeout($this->timeoutSeconds)->retry(1, 100);
    }

    public function fetchHtml(string $url): string
    {
        $response = $this->pendingRequest()->get($url);

        $response->throw();

        return (string) $response->body();
    }

    public function fetchHtmlPool(array $urlsByKey, int $concurrency = 10): array
    {
        $filtered = [];
        foreach ($urlsByKey as $key => $url) {
            $key = (string) $key;
            $url = trim((string) $url);
            if ($key === '' || $url === '') {
                continue;
            }
            $filtered[$key] = $url;
        }

        if ($filtered === []) {
            return [];
        }

        $responses = $this->pendingRequest()->pool(function (Pool $pool) use ($filtered, $concurrency) {
            $pool->concurrency(max(1, (int) $concurrency));

            $reqs = [];
            foreach ($filtered as $key => $url) {
                $reqs[$key] = $pool->as($key)->get($url);
            }

            return $reqs;
        });

        $out = [];
        foreach ($filtered as $key => $_url) {
            $res = $responses[$key] ?? null;
            if ($res instanceof Response && $res->successful()) {
                $out[$key] = (string) $res->body();
            } else {
                $out[$key] = null;
            }
        }

        return $out;
    }

    public function scrapeTgddPriceAndName(string $productUrl): ?array
    {
        $host = parse_url($productUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';
        if ($host === '' || ! str_contains($host, 'thegioididong.com')) {
            return null;
        }

        try {
            $jar = new CookieJar;
            $req = $this->pendingRequest()->withOptions([
                'cookies' => $jar,
                'allow_redirects' => true,
            ]);

            $pageRes = $req->get($productUrl);
            if (! $pageRes->successful()) {
                return null;
            }

            $pageHtml = (string) $pageRes->body();
            $productId = null;
            $categoryId = null;

            if (preg_match('/\/Products\/Images\/(\d+)\/(\d+)\//i', $pageHtml, $m) === 1) {
                $categoryId = (int) $m[1];
                $productId = (int) $m[2];
            } elseif (preg_match('/\bdata-id=["\'](\d{3,})["\']/i', $pageHtml, $m) === 1) {
                $productId = (int) $m[1];
            } elseif (preg_match('/\bproductId\b[^0-9]{0,20}(\d{3,})/i', $pageHtml, $m) === 1) {
                $productId = (int) $m[1];
            }

            if (! $productId || $productId <= 0) {
                return null;
            }

            $ajaxRes = $req
                ->withHeaders([
                    'Accept' => '*/*',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Origin' => 'https://www.thegioididong.com',
                    'Referer' => $productUrl,
                ])
                ->asForm()
                ->post('https://www.thegioididong.com/Ajax/GetViewedHistory', [
                    'customerId' => '',
                    'productIds[]' => (string) $productId,
                    'categoryIds[]' => (string) max(0, (int) ($categoryId ?? 0)),
                    'viewName' => 'detail',
                    'shortNameCus' => '',
                    'cateId' => '0',
                ]);

            if (! $ajaxRes->successful()) {
                return null;
            }

            $snippet = (string) $ajaxRes->body();
            if (trim($snippet) === '') {
                return null;
            }

            $dom = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            $dom->loadHTML($snippet);
            libxml_clear_errors();

            $xp = new \DOMXPath($dom);
            $node = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viewed-product ') and @data-id='".(int) $productId."']")?->item(0);
            if (! $node) {
                $node = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' viewed-product ')]")?->item(0);
            }

            if (! $node) {
                return null;
            }

            $anchor = $xp->query('.//a', $node)?->item(0);
            $dataPrice = $anchor instanceof \DOMElement ? trim((string) $anchor->getAttribute('data-price')) : '';
            $dataName = $anchor instanceof \DOMElement ? trim((string) $anchor->getAttribute('data-name')) : '';

            $price = null;
            if ($dataPrice !== '' && preg_match('/^\d+(?:\.\d+)?$/', $dataPrice) === 1) {
                $f = (float) $dataPrice;
                if ($f > 0) {
                    $price = (int) round($f);
                }
            }

            if (is_null($price)) {
                $priceText = trim((string) ($xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' viewed-product-price ')]", $node)?->item(0)?->textContent));
                $price = $this->parsePriceToInt($priceText);
            }

            $name = $dataName !== '' ? html_entity_decode($dataName, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
            if (! $name) {
                $nameText = trim((string) ($xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' viewed-product-title ')]", $node)?->item(0)?->textContent));
                $name = $nameText !== '' ? $nameText : null;
            }

            if (is_null($price)) {
                return null;
            }

            return [
                'price' => $price,
                'name' => $name,
                'product_id' => $productId,
                'category_id' => $categoryId,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function extractFirstByXPath(string $html, string $xpath): ?string
    {
        $xpath = trim($xpath);
        if ($xpath === '') {
            return null;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xp = new \DOMXPath($dom);
        $result = $xp->evaluate($xpath);
        $value = null;

        if ($result instanceof \DOMNodeList) {
            if ($result->length === 0) {
                return null;
            }
            $value = (string) $result->item(0)?->textContent;
        } elseif (is_string($result) || is_int($result) || is_float($result)) {
            $value = (string) $result;
        } elseif (is_bool($result)) {
            $value = $result ? '1' : '0';
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function extractFirstByXPaths(string $html, array $xpaths): ?string
    {
        foreach ($xpaths as $xpath) {
            $value = $this->extractFirstByXPath($html, (string) $xpath);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function extractFirstByXPathsWithDebug(string $html, array $xpaths): array
    {
        foreach ($xpaths as $i => $xpath) {
            $value = $this->extractFirstByXPath($html, (string) $xpath);
            if ($value !== null) {
                return [
                    'value' => $value,
                    'matched_index' => (int) $i,
                    'matched_xpath' => (string) $xpath,
                    'tried' => array_values(array_map('strval', $xpaths)),
                ];
            }
        }

        return [
            'value' => null,
            'matched_index' => null,
            'matched_xpath' => null,
            'tried' => array_values(array_map('strval', $xpaths)),
        ];
    }

    public function extractTitle(string $html): ?string
    {
        return $this->extractFirstByXPath($html, '//title');
    }

    public function extractProductNameAndPriceFromStructuredData(string $html): array
    {
        $result = [
            'name' => null,
            'price_raw' => null,
        ];

        if (! preg_match_all('/<script[^>]*type=[\"\']application\/ld\+json[\"\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            $result['price_raw'] = $this->extractPriceRawFromCommonMeta($html);

            return $result;
        }

        foreach ($m[1] as $json) {
            $json = trim((string) $json);
            if ($json === '') {
                continue;
            }

            $data = $this->decodeJsonLd($json);
            if (! is_array($data)) {
                continue;
            }

            foreach ($this->flattenJsonLdNodes($data) as $node) {
                if (! is_array($node) || ! $this->isJsonLdProductNode($node)) {
                    continue;
                }

                if (! $result['name'] && isset($node['name']) && is_string($node['name'])) {
                    $name = trim($node['name']);
                    if ($name !== '') {
                        $result['name'] = $name;
                    }
                }

                if (! $result['price_raw']) {
                    $priceRaw = $this->extractPriceRawFromJsonLdOffers($node['offers'] ?? null);
                    if ($priceRaw !== null) {
                        $result['price_raw'] = $priceRaw;
                    }
                }

                if ($result['name'] && $result['price_raw']) {
                    return $result;
                }
            }
        }

        if (! $result['price_raw']) {
            $result['price_raw'] = $this->extractPriceRawFromCommonMeta($html);
        }

        return $result;
    }

    private function decodeJsonLd(string $json): ?array
    {
        $clean = trim($json);
        if ($clean === '') {
            return null;
        }

        $clean = preg_replace('/^\s*<!--/u', '', $clean) ?? $clean;
        $clean = preg_replace('/-->\s*$/u', '', $clean) ?? $clean;
        $clean = preg_replace('/^\s*\/\*\s*<!\[CDATA\[\s*\*\/\s*/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*\/\*\s*\]\]>\s*\*\/\s*$/u', '', $clean) ?? $clean;
        $clean = trim($clean);

        $data = json_decode($clean, true);
        if (is_array($data)) {
            return $data;
        }

        $o1 = strpos($clean, '{');
        $o2 = strrpos($clean, '}');
        if ($o1 !== false && $o2 !== false && $o2 > $o1) {
            $sub = substr($clean, $o1, $o2 - $o1 + 1);
            $data = json_decode($sub, true);
            if (is_array($data)) {
                return $data;
            }
        }

        $a1 = strpos($clean, '[');
        $a2 = strrpos($clean, ']');
        if ($a1 !== false && $a2 !== false && $a2 > $a1) {
            $sub = substr($clean, $a1, $a2 - $a1 + 1);
            $data = json_decode($sub, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    private function flattenJsonLdNodes(mixed $data): array
    {
        $out = [];
        $stack = [$data];
        $visited = 0;

        while ($stack !== []) {
            $visited++;
            if ($visited > 5000) {
                break;
            }

            $cur = array_pop($stack);
            if (! is_array($cur)) {
                continue;
            }

            if (array_key_exists('@type', $cur)) {
                $out[] = $cur;
            }

            foreach ($cur as $v) {
                if (is_array($v)) {
                    $stack[] = $v;
                }
            }
        }

        return $out;
    }

    private function isJsonLdProductNode(array $node): bool
    {
        $type = $node['@type'] ?? null;
        $types = [];

        if (is_string($type)) {
            $types = [$type];
        } elseif (is_array($type)) {
            $types = array_values(array_filter($type, fn ($v) => is_string($v)));
        }

        foreach ($types as $t) {
            if (mb_strtolower($t) === 'product') {
                return true;
            }
        }

        return false;
    }

    private function extractPriceRawFromJsonLdOffers(mixed $offers): ?string
    {
        $offerNodes = [];

        if (is_array($offers)) {
            $isList = array_keys($offers) === range(0, count($offers) - 1);
            if ($isList) {
                $offerNodes = array_values(array_filter($offers, fn ($v) => is_array($v)));
            } else {
                $offerNodes = [$offers];
            }
        }

        foreach ($offerNodes as $offer) {
            $direct = $this->extractPriceRawFromJsonLdNode($offer);
            if ($direct !== null) {
                return $direct;
            }

            $priceSpec = $offer['priceSpecification'] ?? null;
            if (is_array($priceSpec)) {
                $specNodes = array_keys($priceSpec) === range(0, count($priceSpec) - 1)
                    ? array_values(array_filter($priceSpec, fn ($v) => is_array($v)))
                    : [$priceSpec];

                foreach ($specNodes as $spec) {
                    $specPrice = $this->extractPriceRawFromJsonLdNode($spec);
                    if ($specPrice !== null) {
                        return $specPrice;
                    }
                }
            }
        }

        return null;
    }

    private function extractPriceRawFromJsonLdNode(array $node): ?string
    {
        foreach (['sale_price', 'price', 'lowPrice', 'highPrice', 'minPrice', 'maxPrice', 'currentPrice'] as $k) {
            if (! array_key_exists($k, $node)) {
                continue;
            }

            $v = $node[$k];
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }
            if (is_string($v)) {
                $s = trim($v);
                if ($s !== '') {
                    return $s;
                }
            }
            if (is_array($v)) {
                foreach (['@value', 'value', 'price'] as $vk) {
                    if (array_key_exists($vk, $v) && (is_string($v[$vk]) || is_int($v[$vk]) || is_float($v[$vk]))) {
                        $s = trim((string) $v[$vk]);
                        if ($s !== '') {
                            return $s;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function extractPriceRawFromCommonMeta(string $html): ?string
    {
        $xpaths = [
            '//meta[@property="product:price:amount"]/@content',
            '//meta[@property="og:price:amount"]/@content',
            '//meta[@property="og:price:standard_amount"]/@content',
            '//meta[@property="og:price"]/@content',
            '//meta[@name="price"]/@content',
            '//meta[@itemprop="price"]/@content',
            '//*[@itemprop="price"]/@content',
            '//*[@itemprop="price"]',
        ];

        return $this->extractFirstByXPaths($html, $xpaths);
    }

    public function parsePriceToInt(?string $raw, ?string $regex = null): ?int
    {
        if ($raw === null) {
            return null;
        }

        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        if (is_string($regex) && trim($regex) !== '') {
            $pattern = trim($regex);
            $ok = @preg_match($pattern, $text, $m);
            if ($ok === 1 && isset($m[1])) {
                $text = (string) $m[1];
            }
        }

        $digits = preg_replace('/[^\d]/u', '', $text);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        $value = (int) $digits;

        return $value >= 0 ? $value : null;
    }
}
