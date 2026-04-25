<?php

namespace App\Services;

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

    public function extractFirstByXPath(string $html, string $xpath): ?string
    {
        $xpath = trim($xpath);
        if ($xpath === '') {
            return null;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xp = new \DOMXPath($dom);
        $nodes = $xp->query($xpath);
        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        $value = trim((string) $nodes->item(0)?->textContent);

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
            return $result;
        }

        foreach ($m[1] as $json) {
            $json = trim((string) $json);
            if ($json === '') {
                continue;
            }

            $data = json_decode($json, true);
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

        return $result;
    }

    private function flattenJsonLdNodes(mixed $data): array
    {
        $out = [];

        if (! is_array($data)) {
            return $out;
        }

        if (array_key_exists('@graph', $data) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $node) {
                if (is_array($node)) {
                    $out[] = $node;
                }
            }
        }

        if (array_key_exists('@type', $data)) {
            $out[] = $data;
        }

        $isList = array_keys($data) === range(0, count($data) - 1);
        if ($isList) {
            foreach ($data as $item) {
                foreach ($this->flattenJsonLdNodes($item) as $node) {
                    $out[] = $node;
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
            foreach (['sale_price', 'price', 'lowPrice', 'highPrice'] as $k) {
                if (! array_key_exists($k, $offer)) {
                    continue;
                }
                $v = $offer[$k];
                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }
                if (is_string($v)) {
                    $s = trim($v);
                    if ($s !== '') {
                        return $s;
                    }
                }
            }
        }

        return null;
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
