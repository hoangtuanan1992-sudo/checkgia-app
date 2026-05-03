<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PriceScraper
{
    public function fetchHtml(string $url): string
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ];

        $response = Http::withHeaders($headers)->timeout(25)->retry(2, 250)->get($url);

        $response->throw();

        $body = (string) $response->body();
        $scriptCookie = $this->javascriptCookieHeader($body);
        if ($scriptCookie) {
            $retryHeaders = $headers + [
                'Cookie' => $scriptCookie,
                'Referer' => $this->originUrl($url) ?? $url,
            ];
            $retry = Http::withHeaders($retryHeaders)->timeout(25)->retry(2, 250)->get($url);
            $retry->throw();

            return (string) $retry->body();
        }

        return $body;
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

            $node = $result->item(0);
            $value = $node instanceof \DOMAttr ? $node->value : (string) $node?->textContent;
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

        $text = $this->stripTrailingDecimalZero($text);
        if (preg_match('/^\d+(?:[.,]\d+)?e[+-]?\d+$/i', $text) === 1) {
            return (int) round((float) str_replace(',', '.', $text));
        }

        $digits = preg_replace('/[^\d]/u', '', $text);
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        $value = (int) $digits;

        return $value >= 0 ? $value : null;
    }

    /**
     * @return array{name: string, price: int}|null
     */
    public function scrapeKnownSitePriceAndName(string $url): ?array
    {
        if ($this->isTopzoneUrl($url)) {
            return $this->scrapeTopzonePriceAndName($url);
        }

        if ($this->isViettelStoreUrl($url)) {
            return $this->scrapeViettelStorePriceAndName($url);
        }

        if ($this->isMiComUrl($url)) {
            return $this->scrapeMiComPriceAndName($url);
        }

        if ($this->isHoangHaMobileUrl($url)) {
            return $this->scrapeHoangHaMobilePriceAndName($url);
        }

        return null;
    }

    public function isTopzoneUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host === 'topzone.vn';
    }

    public function isViettelStoreUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host === 'viettelstore.vn';
    }

    public function isMiComUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host === 'mi.com';
    }

    public function isHoangHaMobileUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host === 'hoanghamobile.com';
    }

    /**
     * @return array{name: string, price: int}|null
     */
    public function scrapeTopzonePriceAndName(string $url, ?string $html = null): ?array
    {
        $html = $html ?? $this->fetchHtml($url);
        $name = $this->cleanText($this->extractFirstByXPath($html, '//h1'));
        if (! $name) {
            $name = $this->cleanText($this->metaContent($html, 'og:title') ?? $this->extractTitle($html));
        }

        $price = $this->extractTopzonePrice($html);
        if (! $name || is_null($price)) {
            return null;
        }

        return [
            'name' => $name,
            'price' => $price,
        ];
    }

    private function extractTopzonePrice(string $html): ?int
    {
        if (preg_match_all('/<[^>]*(?:bs_price|price[^"\']*)[^>]*>/iu', $html, $matches) === 1) {
            foreach ($matches[0] as $tag) {
                $disPrice = $this->attributeNumber($tag, 'data-disprice');
                $price = $this->attributeNumber($tag, 'data-price');
                if (! is_null($disPrice) && $disPrice > 0) {
                    return $disPrice;
                }
                if (! is_null($price) && $price > 0) {
                    return $price;
                }
            }
        }

        if (preg_match('/"priceSpecification"\s*:\s*\{.*?"price"\s*:\s*"?(?<price>\d+(?:[.,]\d+)?)"?/isu', $html, $match) === 1) {
            $price = $this->normalizeNumericPrice((string) ($match['price'] ?? ''));
            if (! is_null($price) && $price > 0) {
                return $price;
            }
        }

        if (preg_match('/<strong[^>]*class=["\'][^"\']*price[^"\']*["\'][^>]*>(?<price>.*?)<\/strong>/isu', $html, $match) === 1) {
            return $this->parsePriceToInt($this->cleanText((string) ($match['price'] ?? '')));
        }

        return null;
    }

    /**
     * @return array{name: string, price: int}|null
     */
    public function scrapeViettelStorePriceAndName(string $url, ?string $html = null): ?array
    {
        $html = $html ?? $this->fetchHtml($url);
        $jsonProduct = $this->viettelStoreJsonLdProduct($html);

        $name = $this->cleanText((string) ($jsonProduct['name'] ?? ''));
        if (! $name) {
            $name = $this->cleanText($this->attributeText($html, 'data-name'));
        }
        if (! $name) {
            $name = $this->cleanViettelStoreTitle($this->metaContent($html, 'og:title') ?? $this->extractTitle($html));
        }

        $price = $this->viettelStoreJsonLdPrice($jsonProduct);
        if (is_null($price)) {
            $price = $this->extractViettelStoreVisiblePrice($html);
        }
        if (is_null($price)) {
            $price = $this->attributeNumber($html, 'data-price');
        }

        if (! $name || is_null($price)) {
            return null;
        }

        return [
            'name' => $name,
            'price' => $price,
        ];
    }

    private function extractViettelStoreVisiblePrice(string $html): ?int
    {
        if (preg_match_all('/<(?<tag>[a-z0-9]+)[^>]*class=["\'](?=[^"\']*\bprice\b)(?![^"\']*\bprice-old\b)(?![^"\']*\bold\b)[^"\']*["\'][^>]*>(?<price>.*?)<\/\k<tag>>/isu', $html, $matches) !== 1) {
            return null;
        }

        foreach ($matches['price'] as $priceText) {
            $price = $this->parsePriceToInt($this->cleanText((string) $priceText));
            if (! is_null($price) && $price > 0) {
                return $price;
            }
        }

        return null;
    }

    private function viettelStoreJsonLdProduct(string $html): ?array
    {
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(?<json>.*?)<\/script>/isu', $html, $matches) !== 1) {
            return null;
        }

        foreach ($matches['json'] as $rawJson) {
            $data = json_decode(trim((string) $rawJson), true);
            $product = $this->findJsonLdProduct($data);
            if (is_array($product)) {
                return $product;
            }
        }

        return null;
    }

    private function findJsonLdProduct(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        foreach ($types as $typeValue) {
            if (is_string($typeValue) && mb_strtolower($typeValue) === 'product') {
                return $node;
            }
        }

        foreach ($node as $child) {
            $product = $this->findJsonLdProduct($child);
            if (is_array($product)) {
                return $product;
            }
        }

        return null;
    }

    private function viettelStoreJsonLdPrice(?array $product): ?int
    {
        if (! $product) {
            return null;
        }

        $offers = $product['offers'] ?? null;
        $offerNodes = array_is_list((array) $offers) ? (array) $offers : [$offers];
        foreach ($offerNodes as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            foreach (['price', 'Price', 'lowPrice', 'highPrice'] as $key) {
                if (array_key_exists($key, $offer)) {
                    $price = $this->normalizeNumericPrice((string) $offer[$key]);
                    if (! is_null($price) && $price > 0) {
                        return $price;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{name: string, price: int}|null
     */
    public function scrapeMiComPriceAndName(string $url, ?string $html = null, ?array $apiData = null): ?array
    {
        $tag = $this->miComProductTag($url);
        if (! $tag) {
            return null;
        }

        if ($html === null) {
            try {
                $html = $this->fetchHtml($url);
            } catch (\Throwable) {
                $html = '';
            }
        }

        $name = $this->miComProductName($html);
        if (! $name) {
            $name = $this->nameFromSlug($tag);
        }

        $apiData = $apiData ?? $this->fetchMiComProductInfo($url, $tag);
        $price = $this->miComApiPrice($apiData);

        if (! $name || is_null($price)) {
            return null;
        }

        return [
            'name' => $name,
            'price' => $price,
        ];
    }

    private function miComProductTag(string $url): ?string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $segments = array_values(array_filter(explode('/', $path), fn ($segment) => $segment !== ''));
        $index = array_search('product', $segments, true);
        if ($index === false || empty($segments[$index + 1])) {
            return null;
        }

        $tag = strtolower(trim((string) $segments[$index + 1]));

        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $tag) === 1 ? $tag : null;
    }

    private function miComLocale(string $url): string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $firstSegment = explode('/', $path, 2)[0] ?? '';

        return preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/i', $firstSegment) === 1
            ? strtolower($firstSegment)
            : 'vn';
    }

    private function fetchMiComProductInfo(string $url, string $tag): ?array
    {
        $locale = $this->miComLocale($url);
        $apiUrl = 'https://go.buy.mi.com/'.$locale.'/v2/item/productinfo';

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json',
                'Referer' => $this->originUrl($url) ?? 'https://www.mi.com/',
            ])->timeout(25)->retry(2, 250)->get($apiUrl, [
                'tag' => $tag,
                'is_bundle' => 'undefined',
            ]);

            $response->throw();
            $body = $response->json();

            return is_array($body) ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function miComApiPrice(?array $apiData): ?int
    {
        if (! is_array($apiData) || (int) ($apiData['errno'] ?? -1) !== 0 || ! is_array($apiData['data'] ?? null)) {
            return null;
        }

        foreach (['item_min_price', 'sale_price', 'price', 'market_price', 'rrp'] as $key) {
            if (! array_key_exists($key, $apiData['data'])) {
                continue;
            }

            $price = $this->normalizeNumericPrice((string) $apiData['data'][$key]);
            if (! is_null($price) && $price > 0) {
                return $price;
            }
        }

        return null;
    }

    private function miComProductName(string $html): ?string
    {
        $product = $this->jsonLdProduct($html);
        $name = $this->cleanText((string) ($product['name'] ?? ''));
        if ($name) {
            return $name;
        }

        $name = $this->cleanText($this->extractFirstByXPath($html, '//h1'));
        if ($name) {
            return preg_replace('/\s+Tổng quan$/iu', '', $name) ?? $name;
        }

        $title = $this->cleanText($this->metaContent($html, 'og:title') ?? $this->extractTitle($html));
        if (! $title) {
            return null;
        }

        $title = preg_replace('/^Tất cả thông số và tính năng của\s+/iu', '', $title) ?? $title;
        $title = preg_replace('/\s*\|\s*Xiaomi.*$/iu', '', $title) ?? $title;

        return $this->cleanText($title);
    }

    private function jsonLdProduct(string $html): ?array
    {
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(?<json>.*?)<\/script>/isu', $html, $matches) !== 1) {
            return null;
        }

        foreach ($matches['json'] as $rawJson) {
            $data = json_decode(trim((string) $rawJson), true);
            $product = $this->findJsonLdProduct($data);
            if (is_array($product)) {
                return $product;
            }
        }

        return null;
    }

    private function nameFromSlug(string $slug): string
    {
        $words = array_values(array_filter(explode('-', $slug), fn ($word) => $word !== ''));
        $words = array_map(function (string $word): string {
            return preg_match('/^[a-z]+$/i', $word) === 1 && mb_strlen($word) <= 4
                ? mb_strtoupper($word)
                : ucfirst($word);
        }, $words);

        return implode(' ', $words);
    }

    /**
     * @return array{name: string, price: int}|null
     */
    public function scrapeHoangHaMobilePriceAndName(string $url, ?string $html = null): ?array
    {
        $html = $html ?? $this->fetchHtml($url);
        $product = $this->hoangHaMobileInsiderProduct($html);

        $name = $this->cleanText((string) ($product['name'] ?? ''));
        if (! $name) {
            $name = $this->cleanText($this->extractFirstByXPath($html, '//div[contains(@class, "product-detail")]//h1'));
        }
        if (! $name) {
            $name = $this->cleanText($this->metaContent($html, 'og:title') ?? $this->extractTitle($html));
        }

        $price = $this->hoangHaMobileProductPrice($product);
        if (is_null($price)) {
            $price = $this->extractHoangHaMobileVisiblePrice($html);
        }

        if (! $name || is_null($price)) {
            return null;
        }

        return [
            'name' => $name,
            'price' => $price,
        ];
    }

    private function hoangHaMobileInsiderProduct(string $html): ?array
    {
        if (preg_match('/window\.insider_object\.product\s*=\s*(?<json>\{.*?\})\s*;/isu', $html, $match) !== 1) {
            return null;
        }

        $product = json_decode((string) ($match['json'] ?? ''), true);

        return is_array($product) ? $product : null;
    }

    private function hoangHaMobileProductPrice(?array $product): ?int
    {
        if (! $product) {
            return null;
        }

        foreach (['unit_sale_price', 'price', 'unit_price'] as $key) {
            if (! array_key_exists($key, $product)) {
                continue;
            }

            $price = $this->normalizeNumericPrice((string) $product[$key]);
            if (! is_null($price) && $price > 0) {
                return $price;
            }
        }

        foreach ((array) data_get($product, 'custom.sku', []) as $sku) {
            if (! is_array($sku) || ! array_key_exists('price', $sku)) {
                continue;
            }

            $price = $this->normalizeNumericPrice((string) $sku['price']);
            if (! is_null($price) && $price > 0) {
                return $price;
            }
        }

        return null;
    }

    private function extractHoangHaMobileVisiblePrice(string $html): ?int
    {
        if (preg_match('/<div[^>]*class=["\'][^"\']*\bbox-price\b[^"\']*["\'][^>]*>\s*<strong[^>]*class=["\'][^"\']*\bprice\b[^"\']*["\'][^>]*>(?<price>.*?)<\/strong>/isu', $html, $match) === 1) {
            $price = $this->parsePriceToInt($this->cleanText((string) ($match['price'] ?? '')));
            if (! is_null($price) && $price > 0) {
                return $price;
            }
        }

        return null;
    }

    private function attributeNumber(string $tag, string $attribute): ?int
    {
        if (preg_match('/\b'.preg_quote($attribute, '/').'\s*=\s*["\'](?<value>[^"\']+)["\']/iu', $tag, $match) !== 1) {
            return null;
        }

        return $this->normalizeNumericPrice((string) ($match['value'] ?? ''));
    }

    private function attributeText(string $html, string $attribute): ?string
    {
        if (preg_match('/\b'.preg_quote($attribute, '/').'\s*=\s*["\'](?<value>[^"\']+)["\']/iu', $html, $match) !== 1) {
            return null;
        }

        return $this->cleanText((string) ($match['value'] ?? ''));
    }

    private function normalizeNumericPrice(string $value): ?int
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return null;
        }

        $value = $this->stripTrailingDecimalZero($value);

        if (preg_match('/^\d+(?:[.,]\d+)?e[+-]?\d+$/i', $value) === 1) {
            return (int) round((float) str_replace(',', '.', $value));
        }

        if (preg_match('/^\d+(?:[.,]\d+)?$/', $value) === 1) {
            return (int) floor((float) str_replace(',', '.', $value));
        }

        return $this->parsePriceToInt($value);
    }

    private function stripTrailingDecimalZero(string $value): string
    {
        $boundary = '(?=\s*(?:\x{20AB}|\x{0111}|vnd)?(?:\s|$))';
        $value = preg_replace('/(\d{1,3}(?:[.,]\d{3})+)[.,]0{1,2}'.$boundary.'/iu', '$1', $value) ?? $value;

        return preg_replace('/(\d{5,})[.,]0{1,2}'.$boundary.'/iu', '$1', $value) ?? $value;
    }

    private function javascriptCookieHeader(string $html): ?string
    {
        if (preg_match('/document\.cookie\s*=\s*["\'](?<name>[^=;"\']+)=(?<value>[^;"\']+)/iu', $html, $match) !== 1) {
            return null;
        }

        $name = trim((string) ($match['name'] ?? ''));
        $value = trim((string) ($match['value'] ?? ''));

        return $name !== '' && $value !== '' ? $name.'='.$value : null;
    }

    private function originUrl(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($scheme) || ! is_string($host) || $scheme === '' || $host === '') {
            return null;
        }

        return $scheme.'://'.$host.'/';
    }

    private function cleanViettelStoreTitle(?string $title): ?string
    {
        $title = $this->cleanText($title);
        if (! $title) {
            return null;
        }

        $title = preg_replace('/\s+chính hãng\s+-\s+ViettelStore\.vn\s*$/iu', '', $title) ?? $title;
        $title = preg_replace('/\s+-\s+ViettelStore\.vn\s*$/iu', '', $title) ?? $title;

        return $this->cleanText($title);
    }

    private function metaContent(string $html, string $property): ?string
    {
        $quoted = preg_quote($property, '/');
        $patterns = [
            '/<meta[^>]+property=["\']'.$quoted.'["\'][^>]+content=["\'](?<content>[^"\']+)["\'][^>]*>/iu',
            '/<meta[^>]+content=["\'](?<content>[^"\']+)["\'][^>]+property=["\']'.$quoted.'["\'][^>]*>/iu',
            '/<meta[^>]+name=["\']'.$quoted.'["\'][^>]+content=["\'](?<content>[^"\']+)["\'][^>]*>/iu',
            '/<meta[^>]+content=["\'](?<content>[^"\']+)["\'][^>]+name=["\']'.$quoted.'["\'][^>]*>/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match) === 1) {
                return $this->cleanText((string) ($match['content'] ?? ''));
            }
        }

        return null;
    }

    private function cleanText(?string $value): ?string
    {
        $value = trim(html_entity_decode((string) ($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }
}
