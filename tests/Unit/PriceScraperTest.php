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

    public function test_extracts_thegioididong_visible_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><body>
                <div class="product-name">
                    <h1>iPhone 16 256GB</h1>
                </div>
                <div class="box_saving v2 olgr twoprice">
                    <div class="bs_title">
                        <div class="bs_price" data-priceOrg="24990000.0" data-discountorigin="1600000.0">
                            <b>Online Gia Re Qua</b>
                            <strong>23.390.000&#x20AB;</strong>
                            <em>24.990.000&#x20AB;</em>
                        </div>
                    </div>
                </div>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeTheGioiDiDongPriceAndName('https://www.thegioididong.com/dtdd/iphone-16-256gb', $html);

        $this->assertSame([
            'name' => 'iPhone 16 256GB',
            'price' => 23390000,
        ], $result);
    }

    public function test_extracts_thegioididong_json_ld_price_when_visible_price_is_missing(): void
    {
        $html = <<<'HTML'
            <html><body>
                <script type="application/ld+json" id="productld">
                    {
                        "@context": "https://schema.org",
                        "@type": "Product",
                        "name": "Samsung Galaxy A56 5G 12GB/256GB",
                        "offers": {
                            "@type": "Offer",
                            "priceCurrency": "VND",
                            "price": 10690000.0
                        }
                    }
                </script>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeTheGioiDiDongPriceAndName('https://www.thegioididong.com/dtdd/samsung-galaxy-a56-5g', $html);

        $this->assertSame([
            'name' => 'Samsung Galaxy A56 5G 12GB/256GB',
            'price' => 10690000,
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

    public function test_extracts_minhtuanmobile_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <meta property="og:title" content="iPhone 17 256GB chinh hang VN A | Co tra gop 3 khong" />
                <script type="application/ld+json">
                {
                    "@context": "https://schema.org/",
                    "@type": "Product",
                    "name": "iPhone 17 256GB - Chinh hang VN - MG6L4ZP A",
                    "sku": "MG6L4ZP/A",
                    "offers": {
                        "@type": "Offer",
                        "priceCurrency": "VND",
                        "price": 24190000,
                        "priceSpecification": {
                            "@type": "UnitPriceSpecification",
                            "price": 24990000
                        }
                    }
                }
                </script>
            </head><body>
                <h1>iPhone 17 256GB - Chinh hang VN - MG6L4ZP/A</h1>
                <p class="prodetail__price prodetail__price--buynow mb-1">
                    <b class="price">24,190,000&#x0111;</b><s>24,990,000&#x0111;</s>
                </p>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeMinhTuanMobilePriceAndName(
            'https://minhtuanmobile.com/iphone-17-25091002335142/',
            $html
        );

        $this->assertSame([
            'name' => 'iPhone 17 256GB - Chinh hang VN - MG6L4ZP/A',
            'price' => 24190000,
        ], $result);
    }

    public function test_extracts_minhtuanmobile_short_slug_product_url_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <script type="application/ld+json">
                {
                    "@context": "https://schema.org/",
                    "@type": "Product",
                    "name": "iPhone 15 128GB - Chinh hang VN A",
                    "sku": "MTP13VN/A",
                    "offers": {
                        "@type": "Offer",
                        "url": "https://minhtuanmobile.com/iphone-15-128gb/",
                        "priceCurrency": "VND",
                        "price": 17490000,
                        "priceSpecification": {
                            "@type": "UnitPriceSpecification",
                            "price": 19990000
                        }
                    }
                }
                </script>
            </head><body>
                <h1>iPhone 15 128GB - Chinh hang VN/A</h1>
                <div class="prodetail_pricebox_main">
                    <p class="prodetail__price prodetail__price--buynow mb-1">
                        <b class="price">17,490,000&#x0111;</b><s>19,990,000&#x0111;</s>
                    </p>
                </div>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeMinhTuanMobilePriceAndName(
            'https://minhtuanmobile.com/iphone-15-128gb/',
            $html
        );

        $this->assertSame([
            'name' => 'iPhone 15 128GB - Chinh hang VN/A',
            'price' => 17490000,
        ], $result);
    }

    public function test_extracts_lg_com_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <title data-id="pdp-title">Tu lanh LG Instaview UV nano 635L mau be GR-X257BG | LG Viet Nam</title>
                <meta property="og:title" content="Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG - GR-X257BG | LG Viet Nam" />
            </head><body>
                <div class="price-area hidden" data-sku="GR-X257BG.AEEPEVN.EAVH.VN.C" data-msrp="55990000"></div>
                <h2 class="pdp-title">Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG</h2>
                <script type="application/ld+json">
                    {
                        "@context": "https://schema.org",
                        "@type": "Product",
                        "name": "Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG",
                        "offers": {
                            "@type": "Offer",
                            "priceCurrency": "VND",
                            "price": "39990000"
                        }
                    }
                </script>
                <script>
                    var ga4_dataset = {
                        "product": {
                            "model_name": `Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG`,
                            "msrp": parseFloat(`55990000`),
                            "price": ""
                        }
                    };
                </script>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeLgComPriceAndName(
            'https://www.lg.com/vn/tu-lanh/tu-lanh-instaview/gr-x257bg/',
            $html
        );

        $this->assertSame([
            'name' => 'Tu lanh LG Instaview Door-in-door 635L mau be GR-X257BG',
            'price' => 39990000,
        ], $result);
    }

    public function test_extracts_samsung_com_price_and_name_without_xpath(): void
    {
        $html = <<<'HTML'
            <html><head>
                <title>85 Inch Neo QLED QN950F 8K Samsung Vision AI Smart TV (2025) QA85QN950FKXXV | Samsung VN</title>
                <meta name="twitter:title" content="2025 QN950F 85 inch 8K Neo QLED Mini LED Samsung Vision AI Tivi - Gia & Danh Gia | Samsung VN" />
                <script>
                    digitalData = {product: {}};
                    digitalData.product.model_code = "QA85QN950FKXXV";
                    digitalData.product.displayName = "85 Inch Neo QLED QN950F 8K Samsung Vision AI Smart TV (2025)";
                    digitalData.product.model_price = "195690000";
                    digitalData.product.list_price = "215018182";
                </script>
            </head><body>
                <script type="application/ld+json">
                {
                    "@context": "https://schema.org/",
                    "@type": "Product",
                    "url": "https://www.samsung.com/vn/tvs/qled-tv/qn950f-85-inch-neo-qled-8k-mini-led-smart-tv-qa85qn950fkxxv/",
                    "name": "85 Inch Neo QLED QN950F 8K Samsung Vision AI Smart TV (2025)",
                    "sku": "QA85QN950FKXXV",
                    "offers": {
                        "@type": "Offer",
                        "priceCurrency": "VND",
                        "price": "195690000"
                    }
                }
                </script>
                <script type="text/javascript">
                    var globalShopInfo = {"price":"215018182","priceDisplay":"195.690.000 VND","promotionPrice":"195690000"};
                </script>
            </body></html>
        HTML;

        $result = (new PriceScraper)->scrapeSamsungComPriceAndName(
            'https://www.samsung.com/vn/tvs/qled-tv/qn950f-85-inch-neo-qled-8k-mini-led-smart-tv-qa85qn950fkxxv/',
            $html
        );

        $this->assertSame([
            'name' => '85 Inch Neo QLED QN950F 8K Samsung Vision AI Smart TV (2025) QA85QN950FKXXV',
            'price' => 195690000,
        ], $result);
    }
}
