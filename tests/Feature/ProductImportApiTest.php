<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_invalid_api_key(): void
    {
        config(['services.checkgia_import.api_key' => 'secret-key']);

        $response = $this->postJson('/api/products/import', $this->payload(), [
            'Authorization' => 'Bearer wrong-key',
        ]);

        $response->assertStatus(401)->assertJson([
            'ok' => false,
            'error' => 'Invalid API key',
        ]);
    }

    public function test_it_imports_and_updates_scanner_products(): void
    {
        config(['services.checkgia_import.api_key' => 'secret-key']);

        $response = $this->postJson('/api/products/import', $this->payload(), [
            'Authorization' => 'Bearer secret-key',
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'received' => 2,
            'inserted' => 2,
            'updated' => 0,
            'skipped' => 0,
            'stored' => 2,
        ]);

        $this->assertDatabaseHas('scanner_import_jobs', [
            'external_job_id' => 'job-123',
            'app' => 'windows-product-scanner',
            'imported_product_count' => 2,
            'priced_product_count' => 2,
        ]);
        $this->assertDatabaseHas('scanner_import_products', [
            'external_job_id' => 'job-123',
            'product_code' => 'ABC123',
            'name' => 'Ten san pham',
            'price_value' => 7400000,
            'url' => 'https://web-goc/link-san-pham',
        ]);

        $payload = $this->payload();
        $payload['products'] = [[
            'externalId' => 'external-1',
            'jobId' => 'job-123',
            'productCode' => 'ABC123',
            'name' => 'Ten san pham moi',
            'price' => '7.900.000 d',
            'priceText' => '7.900.000 d',
            'priceValue' => 7900000,
            'currency' => 'VND',
            'url' => 'https://web-goc/link-san-pham',
            'link' => 'https://web-goc/link-san-pham',
            'sourceUrl' => 'https://web-goc/',
        ]];

        $updateResponse = $this->withHeader('x-api-key', 'secret-key')
            ->postJson('/api/products/import', $payload);

        $updateResponse->assertOk()->assertJson([
            'ok' => true,
            'received' => 1,
            'inserted' => 0,
            'updated' => 1,
            'stored' => 2,
        ]);

        $this->assertDatabaseHas('scanner_import_products', [
            'product_code' => 'ABC123',
            'name' => 'Ten san pham moi',
            'price_value' => 7900000,
        ]);
    }

    public function test_it_expands_truncated_product_code_from_product_name(): void
    {
        config(['services.checkgia_import.api_key' => 'secret-key']);

        $payload = $this->payload();
        $payload['source']['jobId'] = 'job-model-code';
        $payload['source']['productCount'] = 1;
        $payload['source']['batch']['size'] = 1;
        $payload['products'] = [[
            'externalId' => 'toshiba-1',
            'jobId' => 'job-model-code',
            'productCode' => 'GR-RS780WI',
            'name' => 'Tu lanh Toshiba Inverter 596 lit GR-RS780WI-PGV(22)-XK',
            'price' => '14.500.000 d',
            'priceText' => '14.500.000 d',
            'priceValue' => 14500000,
            'currency' => 'VND',
            'url' => 'https://dienmaydo.vn/tu-lanh-toshiba-inverter-596-lit-gr-rs780wi-pgv-22-xk',
            'link' => 'https://dienmaydo.vn/tu-lanh-toshiba-inverter-596-lit-gr-rs780wi-pgv-22-xk',
            'sourceUrl' => 'https://dienmaydo.vn/',
        ]];

        $response = $this->postJson('/api/products/import', $payload, [
            'Authorization' => 'Bearer secret-key',
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'received' => 1,
            'inserted' => 1,
        ]);

        $this->assertDatabaseHas('scanner_import_products', [
            'external_job_id' => 'job-model-code',
            'product_code' => 'GR-RS780WI-PGV(22)-XK',
            'name' => 'Tu lanh Toshiba Inverter 596 lit GR-RS780WI-PGV(22)-XK',
        ]);
    }

    private function payload(): array
    {
        return [
            'source' => [
                'app' => 'windows-product-scanner',
                'jobId' => 'job-123',
                'startUrl' => 'https://dienmayxanh.com/',
                'mode' => 'all',
                'productCount' => 2,
                'batch' => [
                    'index' => 1,
                    'total' => 1,
                    'size' => 2,
                ],
                'pushedAt' => '2026-05-02T10:00:00+07:00',
            ],
            'products' => [
                [
                    'externalId' => 'external-1',
                    'jobId' => 'job-123',
                    'productCode' => 'ABC123',
                    'name' => 'Ten san pham',
                    'price' => '7.400.000 d',
                    'priceText' => '7.400.000 d',
                    'priceValue' => 7400000,
                    'currency' => 'VND',
                    'url' => 'https://web-goc/link-san-pham',
                    'link' => 'https://web-goc/link-san-pham',
                    'sourceUrl' => 'https://web-goc/',
                ],
                [
                    'externalId' => 'external-2',
                    'jobId' => 'job-123',
                    'productCode' => 'XYZ789',
                    'name' => 'San pham thu hai',
                    'price' => '1.200.000 d',
                    'priceText' => '1.200.000 d',
                    'priceValue' => 1200000,
                    'currency' => 'VND',
                    'url' => 'https://web-goc/link-san-pham-2',
                    'link' => 'https://web-goc/link-san-pham-2',
                    'sourceUrl' => 'https://web-goc/',
                ],
            ],
        ];
    }
}
