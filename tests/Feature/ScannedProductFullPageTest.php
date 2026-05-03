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
            ->assertSee('14.500.000đ')
            ->assertSee('https://dienmaydo.vn/tu-lanh-toshiba-gr-rs780wi-pgv-22-xk');
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
            ->assertSee('2.590.000đ');
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
}
