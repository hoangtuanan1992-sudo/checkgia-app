<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ProductScannerApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.product_scanner.base_url', 'http://127.0.0.1:3110'), '/');
    }

    public function jobs(): array
    {
        return $this->get('/api/jobs');
    }

    public function job(string $id): array
    {
        return $this->get('/api/jobs/'.rawurlencode($id));
    }

    private function get(string $path): array
    {
        $timeout = max(1, (int) config('services.product_scanner.timeout', 12));
        $url = $this->baseUrl().'/'.ltrim($path, '/');

        try {
            $response = Http::acceptJson()
                ->connectTimeout($timeout)
                ->timeout($timeout)
                ->get($url);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'error' => 'Scanner API tra ve HTTP '.$response->status().'.',
                    'data' => [],
                ];
            }

            $data = $response->json();
            if (! is_array($data)) {
                return [
                    'ok' => false,
                    'error' => 'Scanner API khong tra ve JSON hop le.',
                    'data' => [],
                ];
            }

            return [
                'ok' => true,
                'error' => null,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Khong ket noi duoc Scanner API: '.mb_substr((string) $e->getMessage(), 0, 300),
                'data' => [],
            ];
        }
    }
}
