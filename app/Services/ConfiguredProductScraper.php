<?php

namespace App\Services;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteTemplate;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use Illuminate\Support\Facades\Schema;

class ConfiguredProductScraper
{
    public function __construct(private ?PriceScraper $priceScraper = null)
    {
        $this->priceScraper ??= new PriceScraper;
    }

    public function priceScraper(): PriceScraper
    {
        return $this->priceScraper;
    }

    /**
     * @return array{name: ?string, price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, name_debug: array, price_debug: array}
     */
    public function scrapeOwnProduct(string $url, int $userId, bool $zeroWhenPriceMissing = false): array
    {
        $knownProduct = $this->priceScraper->scrapeKnownSitePriceAndName($url);
        if ($knownProduct) {
            return $this->result(
                name: $knownProduct['name'],
                price: (int) $knownProduct['price'],
                source: 'known-site'
            );
        }

        $html = $this->priceScraper->fetchHtml($url);
        $candidates = [];

        $template = $this->approvedTemplateForUrl($url);
        if ($template) {
            $candidates[] = $this->extractWithTemplate($template, $html, 'xpath-template');
        }

        $userCandidate = $this->extractWithUserSettings($userId, $html);
        if ($userCandidate) {
            $candidates[] = $userCandidate;
        }

        foreach ($candidates as $candidate) {
            if ($this->hasNameAndPrice($candidate)) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->canUseZeroPrice($candidate, $zeroWhenPriceMissing)) {
                $candidate['price'] = 0;

                return $candidate;
            }
        }

        return $candidates[0] ?? $this->result(source: 'none');
    }

    /**
     * @return array{price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, price_debug: array}
     */
    public function scrapeCompetitorPrice(string $url, ?CompetitorSite $site = null): array
    {
        $knownProduct = $this->priceScraper->scrapeKnownSitePriceAndName($url);
        if ($knownProduct) {
            return $this->priceResult((int) $knownProduct['price'], 'known-site');
        }

        $html = $this->priceScraper->fetchHtml($url);
        $siteResult = $site ? $this->extractCompetitorSitePrice($site, $html) : null;
        if ($siteResult && ! is_null($siteResult['price'])) {
            return $siteResult;
        }

        $template = $this->approvedTemplateForUrl($url);
        if ($template) {
            $templateResult = $this->extractTemplatePrice($template, $html, 'xpath-template');
            if (! is_null($templateResult['price'])) {
                return $templateResult;
            }

            return $siteResult ?? $templateResult;
        }

        return $siteResult ?? $this->priceResult(null, 'none');
    }

    public function applyApprovedTemplateToSite(CompetitorSite $site, ?string $source = null): void
    {
        $domain = $site->domain ?: CompetitorSite::normalizedDomainFromUserInput($source ?: $site->name);
        if (! $domain) {
            return;
        }

        if (Schema::hasColumn('competitor_sites', 'domain') && $site->domain !== $domain) {
            $site->domain = $domain;
            $site->save();
        }

        $template = $this->approvedTemplateForDomain($domain);
        if (! $template) {
            return;
        }

        $template->applyToCompetitorSite($site);
    }

    public function approvedTemplateForUrl(string $url): ?CompetitorSiteTemplate
    {
        return $this->approvedTemplateForDomain(CompetitorSite::normalizedDomainFromUrl($url));
    }

    public function approvedTemplateForDomain(?string $domain): ?CompetitorSiteTemplate
    {
        if (! $domain || ! Schema::hasTable('competitor_site_templates')) {
            return null;
        }

        $query = CompetitorSiteTemplate::query()
            ->where('domain', $domain)
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }]);

        if (Schema::hasColumn('competitor_site_templates', 'is_approved')) {
            $query->where('is_approved', true);
        }

        return $query->first();
    }

    /**
     * @return array{name: ?string, price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, name_debug: array, price_debug: array}
     */
    private function extractWithTemplate(CompetitorSiteTemplate $template, string $html, string $source): array
    {
        $nameXpaths = $this->templateXpaths($template, 'name', $template->name_xpath);
        $priceXpaths = $this->templateXpaths($template, 'price', $template->price_xpath);

        $nameDebug = $this->priceScraper->extractFirstByXPathsWithDebug($html, $nameXpaths);
        $name = $nameDebug['value'] ?? null;
        if (! $name) {
            $name = $this->priceScraper->extractTitle($html);
        }

        $priceDebug = $this->priceScraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
        $priceRaw = $priceDebug['value'] ?? null;

        return $this->result(
            name: $name,
            price: $this->priceScraper->parsePriceToInt($priceRaw, $template->price_regex),
            source: $source,
            priceRaw: $priceRaw,
            isContactPrice: $this->priceScraper->isContactPriceText($priceRaw),
            templateId: (int) $template->id,
            nameDebug: $nameDebug,
            priceDebug: $priceDebug
        );
    }

    /**
     * @return array{name: ?string, price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, name_debug: array, price_debug: array}|null
     */
    private function extractWithUserSettings(int $userId, string $html): ?array
    {
        $settings = UserScrapeSetting::query()->where('user_id', $userId)->first();
        if (! $settings || ! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return null;
        }

        $nameXpaths = array_merge(
            [(string) $settings->own_name_xpath],
            UserScrapeXpath::query()
                ->where('user_id', $userId)
                ->where('type', 'name')
                ->orderBy('position')
                ->pluck('xpath')
                ->all()
        );
        $priceXpaths = array_merge(
            [(string) $settings->own_price_xpath],
            UserScrapeXpath::query()
                ->where('user_id', $userId)
                ->where('type', 'price')
                ->orderBy('position')
                ->pluck('xpath')
                ->all()
        );

        $nameDebug = $this->priceScraper->extractFirstByXPathsWithDebug($html, $nameXpaths);
        $name = $nameDebug['value'] ?? null;
        if (! $name) {
            $name = $this->priceScraper->extractTitle($html);
        }

        $priceDebug = $this->priceScraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
        $priceRaw = $priceDebug['value'] ?? null;

        return $this->result(
            name: $name,
            price: $this->priceScraper->parsePriceToInt($priceRaw, $settings->price_regex),
            source: 'user-xpath',
            priceRaw: $priceRaw,
            isContactPrice: $this->priceScraper->isContactPriceText($priceRaw),
            nameDebug: $nameDebug,
            priceDebug: $priceDebug
        );
    }

    /**
     * @return array{price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, price_debug: array}|null
     */
    private function extractCompetitorSitePrice(CompetitorSite $site, string $html): ?array
    {
        if (! $site->price_xpath) {
            return null;
        }

        $fallbacks = $site->scrapeXpaths()
            ->where('type', 'price')
            ->orderBy('position')
            ->pluck('xpath')
            ->all();

        $priceXpaths = array_values(array_filter(array_merge([(string) $site->price_xpath], $fallbacks), fn ($xpath) => trim((string) $xpath) !== ''));
        $debug = $this->priceScraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
        $raw = $debug['value'] ?? null;

        return $this->priceResult(
            $this->priceScraper->parsePriceToInt($raw, $site->price_regex),
            'site-xpath',
            $raw,
            $this->priceScraper->isContactPriceText($raw),
            null,
            $debug
        );
    }

    /**
     * @return array{price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, price_debug: array}
     */
    private function extractTemplatePrice(CompetitorSiteTemplate $template, string $html, string $source): array
    {
        $priceXpaths = $this->templateXpaths($template, 'price', $template->price_xpath);
        $debug = $this->priceScraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
        $raw = $debug['value'] ?? null;

        return $this->priceResult(
            $this->priceScraper->parsePriceToInt($raw, $template->price_regex),
            $source,
            $raw,
            $this->priceScraper->isContactPriceText($raw),
            (int) $template->id,
            $debug
        );
    }

    private function hasNameAndPrice(array $candidate): bool
    {
        return trim((string) ($candidate['name'] ?? '')) !== '' && ! is_null($candidate['price'] ?? null);
    }

    private function canUseZeroPrice(array $candidate, bool $zeroWhenPriceMissing): bool
    {
        return trim((string) ($candidate['name'] ?? '')) !== ''
            && is_null($candidate['price'] ?? null)
            && ($zeroWhenPriceMissing || (bool) ($candidate['is_contact_price'] ?? false));
    }

    /**
     * @return list<string>
     */
    private function templateXpaths(CompetitorSiteTemplate $template, string $type, ?string $primary): array
    {
        $fallbacks = $template->scrapeXpaths
            ->where('type', $type)
            ->sortBy('position')
            ->pluck('xpath')
            ->all();

        return array_values(array_filter(array_merge([(string) $primary], $fallbacks), fn ($xpath) => trim((string) $xpath) !== ''));
    }

    /**
     * @return array{name: ?string, price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, name_debug: array, price_debug: array}
     */
    private function result(
        ?string $name = null,
        ?int $price = null,
        ?string $source = null,
        ?string $priceRaw = null,
        bool $isContactPrice = false,
        ?int $templateId = null,
        array $nameDebug = ['tried' => []],
        array $priceDebug = ['tried' => []]
    ): array {
        $name = $this->cleanText($name);

        return [
            'name' => $name,
            'price' => $price,
            'source' => $source,
            'price_raw' => $priceRaw,
            'is_contact_price' => $isContactPrice,
            'template_id' => $templateId,
            'name_debug' => $nameDebug,
            'price_debug' => $priceDebug,
        ];
    }

    /**
     * @return array{price: ?int, source: ?string, price_raw: ?string, is_contact_price: bool, template_id: ?int, price_debug: array}
     */
    private function priceResult(
        ?int $price,
        ?string $source,
        ?string $priceRaw = null,
        bool $isContactPrice = false,
        ?int $templateId = null,
        array $priceDebug = ['tried' => []]
    ): array {
        return [
            'price' => $price,
            'source' => $source,
            'price_raw' => $priceRaw,
            'is_contact_price' => $isContactPrice,
            'template_id' => $templateId,
            'price_debug' => $priceDebug,
        ];
    }

    private function cleanText(?string $value): ?string
    {
        $value = trim(html_entity_decode((string) ($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }
}
