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

    public function test_extract_first_by_xpath_supports_string_expressions(): void
    {
        $html = '<html><body><h1>  iPhone 17   Pro Max  </h1></body></html>';
        $scraper = new PriceScraper;

        $value = $scraper->extractFirstByXPath($html, 'normalize-space(//h1)');

        $this->assertSame('iPhone 17 Pro Max', $value);
    }

    public function test_parse_price_supports_scientific_notation(): void
    {
        $this->assertSame(24290000, (new PriceScraper)->parsePriceToInt('2.429E+07'));
    }

    public function test_extracts_topzone_normal_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><body>
                <h1>iPhone 17 256GB</h1>
                <div class="box_saving olgr v2">
                    <div class="bs_price" data-price="24990000.0" data-disprice="24890000.0">
                        <strong>24.890.000&#x20AB;</strong>
                    </div>
                </div>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeTopzonePriceAndName('https://www.topzone.vn/iphone/iphone-17', $html);

        $this->assertSame([
            'name' => 'iPhone 17 256GB',
            'price' => 24890000,
        ], $result);
    }

    public function test_extracts_topzone_json_ld_price_when_visible_price_is_missing(): void
    {
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="iPhone 17 Pro Max giá tốt" />
            </head><body>
                <h1>iPhone 17 Pro Max 256GB</h1>
                <script type="application/ld+json">
                {
                    "potentialAction": {
                        "priceSpecification": {
                            "priceCurrency": "VND",
                            "name": "Giá iPhone 17 Pro Max 256GB",
                            "price": "37990000.0"
                        }
                    }
                }
                </script>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeTopzonePriceAndName('https://www.topzone.vn/iphone/iphone-17-pro-max', $html);

        $this->assertSame([
            'name' => 'iPhone 17 Pro Max 256GB',
            'price' => 37990000,
        ], $result);
    }

    public function test_topzone_price_uses_main_product_price_before_accessory_prices(): void
    {
        $html = <<<'HTML'
            <html><body>
                <h1>iPhone 17 Pro Max 256GB</h1>
                <strong class="price" data-price="37990000.0" data-disprice="37990000.0">37.990.000&#x20AB;</strong>
                <div class="accessory-price" data-price="540000.0" data-disprice="500000.0">500.000&#x20AB;</div>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeTopzonePriceAndName('https://www.topzone.vn/iphone/iphone-17-pro-max', $html);

        $this->assertSame([
            'name' => 'iPhone 17 Pro Max 256GB',
            'price' => 37990000,
        ], $result);
    }

    public function test_topzone_price_does_not_keep_trailing_decimal_zero(): void
    {
        $html = <<<'HTML'
            <html><body>
                <h1>iPhone 17 Pro Max 256GB</h1>
                <strong class="price">37.990.000.0&#x20AB;</strong>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeTopzonePriceAndName('https://www.topzone.vn/iphone/iphone-17-pro-max', $html);

        $this->assertSame([
            'name' => 'iPhone 17 Pro Max 256GB',
            'price' => 37990000,
        ], $result);
    }

    public function test_extracts_viettelstore_json_ld_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="Samsung Galaxy S26 Plus 12GB | 256GB chinh hang - ViettelStore.vn" />
            </head><body>
                <script type="application/ld+json">
                {
                    "@context": "http://schema.org/",
                    "@type": "Product",
                    "name": "Samsung Galaxy S26 Plus 12GB 256GB",
                    "offers": {
                        "@type": "AggregateOffer",
                        "Price": "24290000",
                        "priceCurrency": "VND"
                    }
                }
                </script>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeViettelStorePriceAndName('https://viettelstore.vn/dien-thoai/samsung-galaxy-s26-plus-pid362293.html', $html);

        $this->assertSame([
            'name' => 'Samsung Galaxy S26 Plus 12GB 256GB',
            'price' => 24290000,
        ], $result);
    }

    public function test_extracts_viettelstore_visible_price_and_scientific_data_price(): void
    {
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="Samsung Galaxy S26 Plus 12GB | 256GB chinh hang - ViettelStore.vn" />
            </head><body>
                <a data-name="Samsung Galaxy S26 Plus 12GB 256GB" data-price="2.429E+07">
                    <div class="block-box-price">
                        <div class="price">24.290.000 &#x20AB;</div>
                        <div class="price-old">29.990.000 &#x20AB;</div>
                    </div>
                </a>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeViettelStorePriceAndName('https://viettelstore.vn/dien-thoai/samsung-galaxy-s26-plus-pid362293.html', $html);

        $this->assertSame([
            'name' => 'Samsung Galaxy S26 Plus 12GB 256GB',
            'price' => 24290000,
        ], $result);
    }

    public function test_extracts_mi_com_price_from_buy_api_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="Tat ca thong so va tinh nang cua POCO Pad X1 | Xiaomi Viet Nam" />
            </head><body>
                <script type="application/ld+json">
                {
                    "@context": "http://schema.org/",
                    "@type": "Product",
                    "name": "POCO Pad X1",
                    "brand": {"@type": "Brand", "name": "Xiaomi"}
                }
                </script>
                <div class="xm-price"><p class="xm-price--items"></p></div>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeMiComPriceAndName(
            'https://www.mi.com/vn/product/poco-pad-x1/',
            $html,
            [
                'errno' => 0,
                'data' => [
                    'item_min_price' => 10290000,
                    'rrp' => 11290000,
                ],
            ]
        );

        $this->assertSame([
            'name' => 'POCO Pad X1',
            'price' => 10290000,
        ], $result);
    }

    public function test_extracts_hoanghamobile_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB" />
                <script>
                    window.insider_object = {};
                    window.insider_object.product = {"id":"5520","name":"May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB","currency":"VND","unit_price":5490000.0,"unit_sale_price":3790000.0,"url":"https://hoanghamobile.com/may-tinh-bang/may-tinh-bang-redmi-pad-se-8-7-4g-6gb-128gb","stock":212,"is_available":true,"custom":{"sku":[{"sku":"PASE8R6XD","name":"Xanh Duong","price":3790000.0}]}};
                </script>
            </head><body>
                <div class="product-detail"><h1>May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB</h1></div>
                <div class="box-price"><strong class="price">3.790.000 &#x20AB;</strong></div>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeHoangHaMobilePriceAndName(
            'https://hoanghamobile.com/may-tinh-bang/may-tinh-bang-redmi-pad-se-8-7-4g-6gb-128gb',
            $html
        );

        $this->assertSame([
            'name' => 'May Tinh Bang Redmi Pad SE 8.7 4G 6GB/128GB',
            'price' => 3790000,
        ], $result);
    }
}
