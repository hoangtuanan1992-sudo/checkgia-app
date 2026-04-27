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

    public function test_extract_first_by_xpath_supports_normalize_space_and_utf8(): void
    {
        $html = '<html><head><meta charset="utf-8"></head><body><div class="box-product-name"><h1>Giá Samsung Galaxy Z Flip 7 tốt, Ưu đãi đến 12 triệu</h1></div></body></html>';
        $scraper = new PriceScraper;

        $value = $scraper->extractFirstByXPath($html, "normalize-space(//div[contains(@class, 'box-product-name')]/h1)");

        $this->assertSame('Giá Samsung Galaxy Z Flip 7 tốt, Ưu đãi đến 12 triệu', $value);
    }
}
