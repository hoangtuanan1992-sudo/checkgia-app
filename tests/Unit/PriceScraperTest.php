<?php

namespace Tests\Unit;

use App\Services\PriceScraper;
use PHPUnit\Framework\TestCase;

class PriceScraperTest extends TestCase
{
    public function test_extract_first_by_xpaths_uses_fallback(): void
    {
        $html = '<html><body><div id="a">x</div><div id="price">37.790.000đ</div></body></html>';
        $scraper = new PriceScraper;

        $value = $scraper->extractFirstByXPaths($html, [
            '//*[@id="missing"]',
            '//*[@id="price"]',
        ]);

        $this->assertSame(37790000, $scraper->parsePriceToInt($value));
    }
}
