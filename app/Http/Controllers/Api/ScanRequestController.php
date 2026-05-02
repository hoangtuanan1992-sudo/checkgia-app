<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScanRequestController extends Controller
{
    public function next(Request $request): JsonResponse
    {
        $auth = $this->authorizeImportApi($request);
        if ($auth) {
            return $auth;
        }

        if (! Schema::hasTable('scanner_scan_requests')) {
            return response()->json([
                'ok' => false,
                'error' => 'Scan request table is not migrated.',
            ], 503);
        }

        $scanRequest = DB::table('scanner_scan_requests')
            ->where('status', 'pending')
            ->orderBy('requested_at')
            ->orderBy('id')
            ->first();

        if (! $scanRequest) {
            return response()->json([
                'ok' => true,
                'request' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'request' => [
                'id' => (int) $scanRequest->id,
                'url' => (string) $scanRequest->requested_url,
                'startUrl' => (string) $scanRequest->requested_url,
                'status' => (string) $scanRequest->status,
                'requestedAt' => (string) ($scanRequest->requested_at ?? ''),
                'maxPages' => 15000,
            ],
        ]);
    }

    public function accepted(Request $request, int $scanRequest): JsonResponse
    {
        $auth = $this->authorizeImportApi($request);
        if ($auth) {
            return $auth;
        }

        if (! Schema::hasTable('scanner_scan_requests')) {
            return response()->json([
                'ok' => false,
                'error' => 'Scan request table is not migrated.',
            ], 503);
        }

        $externalJobId = trim((string) $request->input('externalJobId', ''));
        if ($externalJobId === '') {
            return response()->json([
                'ok' => false,
                'error' => 'externalJobId is required.',
            ], 422);
        }

        $updated = DB::table('scanner_scan_requests')
            ->where('id', $scanRequest)
            ->update([
                'status' => 'running',
                'external_job_id' => mb_substr($externalJobId, 0, 255),
                'claimed_at' => now(),
                'error' => null,
                'updated_at' => now(),
            ]);

        if (! $updated) {
            return response()->json([
                'ok' => false,
                'error' => 'Scan request not found.',
            ], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function failed(Request $request, int $scanRequest): JsonResponse
    {
        $auth = $this->authorizeImportApi($request);
        if ($auth) {
            return $auth;
        }

        $error = trim((string) $request->input('error', ''));
        DB::table('scanner_scan_requests')
            ->where('id', $scanRequest)
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error' => $error !== '' ? mb_substr($error, 0, 2000) : null,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    private function authorizeImportApi(Request $request): ?JsonResponse
    {
        $expectedKey = trim((string) config('services.checkgia_import.api_key', ''));
        if ($expectedKey === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Import API key is not configured.',
            ], 503);
        }

        $providedKey = $this->readApiKey($request);
        if ($providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid API key',
            ], 401);
        }

        return null;
    }

    private function readApiKey(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer !== '') {
            return $bearer;
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return trim((string) $request->header('x-api-key', ''));
    }
}
