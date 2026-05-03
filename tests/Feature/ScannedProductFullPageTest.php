<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScannedProductFullPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_full_products_page_shows_scanned_products_for_website(): void
    {
        $this->insertScannedProduct([
            'job' => 'job-dienmaydo',
            'start_url' => 'https://dienmaydo.vn/',
            'name' => 'Tu lanh Toshiba GR-RS780WI-PGV(22)-XK',
            'code' => 'GR-RS780WI-PGV(22)-XK',
            'price' => 14500000,
            'url' => 'https://dienmaydo.vn/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk',
        ]);

        $this->get('/san-pham-full?website_url=https://dienmaydo.vn/')
            ->assertOk()
            ->assertSee('Sản phẩm đã quét')
            ->assertSee('Tu lanh Toshiba GR-RS780WI-PGV(22)-XK')
            ->assertSee('https://dienmaydo.vn/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk')
            ->assertDontSee('14.500.000đ')
            ->assertDontSee('Cập nhật');
    }

    public function test_public_full_products_page_accepts_raw_website_query(): void
    {
        $this->insertScannedProduct([
            'job' => 'job-raw-query',
            'start_url' => 'https://phongvu.vn/',
            'name' => 'Man hinh LCD LG 27 inch',
            'code' => '27MS500',
            'price' => 2590000,
            'url' => 'https://phongvu.vn/man-hinh-lcd-lg-27-inch',
        ]);

        $this->get('/san-pham-full?https://phongvu.vn/')
            ->assertOk()
            ->assertSee('Man hinh LCD LG 27 inch')
            ->assertSee('https://phongvu.vn/man-hinh-lcd-lg-27-inch')
            ->assertDontSee('2.590.000đ');
    }

    public function test_public_full_products_page_can_return_full_json_without_pagination(): void
    {
        $this->insertScannedProducts([
            'job' => 'job-full-json',
            'start_url' => 'https://dienmaydo.vn/',
            'products' => [
                [
                    'name' => 'Tu lanh Toshiba GR-RS780WI-PGV(22)-XK',
                    'code' => 'GR-RS780WI-PGV(22)-XK',
                    'price' => 14500000,
                    'url' => 'https://dienmaydo.vn/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk',
                ],
                [
                    'name' => 'Tu lanh Toshiba GR-RS910WI-PMV(06)-MG',
                    'code' => 'GR-RS910WI-PMV(06)-MG',
                    'price' => 16700000,
                    'url' => 'https://dienmaydo.vn/tu-lanh-toshiba-gr-rs910wi-pmv-06-mg',
                ],
            ],
        ]);

        $response = $this->get('/san-pham-full?website_url=https://dienmaydo.vn/&format=json');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('website', 'https://dienmaydo.vn')
            ->assertJsonPath('websiteKey', 'dienmaydo.vn')
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.code', 'GR-RS780WI-PGV(22)-XK')
            ->assertJsonPath('products.0.name', 'Tu lanh Toshiba GR-RS780WI-PGV(22)-XK')
            ->assertJsonPath('products.0.url', 'https://dienmaydo.vn/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk');

        $this->assertSame(['code', 'name', 'url'], array_keys($response->json('products.0')));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertScannedProduct(array $data): void
    {
        $now = now();
        $jobId = DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => $data['job'],
            'app' => 'windows-product-scanner',
            'start_url' => $data['start_url'],
            'mode' => 'all',
            'product_count' => 1,
            'imported_product_count' => 1,
            'priced_product_count' => 1,
            'batch_index' => 1,
            'batch_total' => 1,
            'last_pushed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('scanner_import_products')->insert([
            'scanner_import_job_id' => $jobId,
            'external_id' => sha1($data['url']),
            'external_job_id' => $data['job'],
            'product_code' => $data['code'],
            'name' => $data['name'],
            'price_text' => number_format((int) $data['price'], 0, ',', '.').'d',
            'price_value' => $data['price'],
            'currency' => 'VND',
            'url' => $data['url'],
            'link' => $data['url'],
            'source_url' => $data['start_url'],
            'url_hash' => sha1($data['url']),
            'source_url_hash' => sha1($data['start_url']),
            'dedupe_hash' => sha1($data['start_url'].'|'.$data['url']),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array{job: string, start_url: string, products: array<int, array{name: string, code: string, price: int, url: string}>} $data
     */
    private function insertScannedProducts(array $data): void
    {
        $now = now();
        $count = count($data['products']);
        $jobId = DB::table('scanner_import_jobs')->insertGetId([
            'external_job_id' => $data['job'],
            'app' => 'windows-product-scanner',
            'start_url' => $data['start_url'],
            'mode' => 'all',
            'product_count' => $count,
            'imported_product_count' => $count,
            'priced_product_count' => $count,
            'batch_index' => 1,
            'batch_total' => 1,
            'last_pushed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($data['products'] as $product) {
            DB::table('scanner_import_products')->insert([
                'scanner_import_job_id' => $jobId,
                'external_id' => sha1($product['url']),
                'external_job_id' => $data['job'],
                'product_code' => $product['code'],
                'name' => $product['name'],
                'price_text' => number_format((int) $product['price'], 0, ',', '.').'d',
                'price_value' => $product['price'],
                'currency' => 'VND',
                'url' => $product['url'],
                'link' => $product['url'],
                'source_url' => $data['start_url'],
                'url_hash' => sha1($product['url']),
                'source_url_hash' => sha1($data['start_url']),
                'dedupe_hash' => sha1($data['start_url'].'|'.$product['url']),
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
