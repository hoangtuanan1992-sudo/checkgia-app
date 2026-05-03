<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PriceScraper
{
    public function fetchHtml(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->timeout(25)->retry(2, 250)->get($url);

        $response->throw();

        return (string) $response->body();
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

        return null;
    }

    public function isTopzoneUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return $host === 'topzone.vn';
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

    private function attributeNumber(string $tag, string $attribute): ?int
    {
        if (preg_match('/\b'.preg_quote($attribute, '/').'\s*=\s*["\'](?<value>[^"\']+)["\']/iu', $tag, $match) !== 1) {
            return null;
        }

        return $this->normalizeNumericPrice((string) ($match['value'] ?? ''));
    }

    private function normalizeNumericPrice(string $value): ?int
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return null;
        }

        $value = $this->stripTrailingDecimalZero($value);

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
