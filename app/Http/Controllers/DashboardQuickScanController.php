<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeProductPrices;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardQuickScanController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.quick-scan', [
            'baseUrl' => (string) old('base_url', (string) $request->query('base_url', '')),
            'sitemapUrl' => (string) old('sitemap_url', (string) $request->query('sitemap_url', '')),
            'limit' => (int) old('limit', (int) $request->query('limit', 2000)),
            'urls' => [],
            'scanMessage' => null,
        ]);
    }

    public function scan(Request $request): View
    {
        $validated = $request->validate([
            'base_url' => ['required', 'url', 'max:2048'],
            'sitemap_url' => ['nullable', 'url', 'max:2048'],
            'limit' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $baseUrl = trim((string) $validated['base_url']);
        $sitemapUrl = trim((string) ($validated['sitemap_url'] ?? ''));
        $limit = (int) $validated['limit'];

        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $baseHost = is_string($baseHost) ? strtolower($baseHost) : '';

        $candidates = [];
        if ($sitemapUrl !== '') {
            $candidates[] = $sitemapUrl;
        } else {
            $base = rtrim($baseUrl, '/');
            $candidates = [
                $base.'/sitemap.xml',
                $base.'/sitemap_index.xml',
                $base.'/sitemap.xml.gz',
            ];
        }

        $timeoutSeconds = 7;
        if (Schema::hasTable('app_settings') && Schema::hasColumn('app_settings', 'website_scrape_timeout_seconds')) {
            try {
                $timeoutSeconds = max(1, (int) (\App\Models\AppSetting::current()?->website_scrape_timeout_seconds ?? 7));
            } catch (\Throwable) {
            }
        }

        $req = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept' => 'application/xml,text/xml,application/xhtml+xml,text/html;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
        ])->connectTimeout($timeoutSeconds)->timeout($timeoutSeconds)->retry(1, 100);

        $scanMessage = null;
        $rawXml = null;
        $usedSitemap = null;
        foreach ($candidates as $u) {
            try {
                $res = $req->get($u);
                if (! $res->successful()) {
                    continue;
                }
                $body = (string) $res->body();
                if (trim($body) === '') {
                    continue;
                }
                $rawXml = $body;
                $usedSitemap = $u;
                break;
            } catch (\Throwable) {
            }
        }

        $urls = [];
        $visited = [];
        $pushUrl = function (string $url) use (&$urls, &$visited, $baseHost, $limit) {
            $url = trim($url);
            if ($url === '') return;
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) return;
            $host = parse_url($url, PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : '';
            if ($baseHost !== '' && $host !== '' && $host !== $baseHost) return;
            if (isset($visited[$url])) return;
            $visited[$url] = true;
            $urls[] = $url;
            if (count($urls) >= $limit) return;
        };

        $parseXml = function (string $xml) {
            libxml_use_internal_errors(true);
            $sx = simplexml_load_string($xml);
            libxml_clear_errors();
            return $sx ?: null;
        };

        $fetchAndCollect = function (string $url) use (&$req, &$visited, &$urls, $limit, $baseHost, $parseXml, $pushUrl, &$fetchAndCollect) {
            $url = trim($url);
            if ($url === '' || isset($visited['__sitemap:'.$url])) {
                return;
            }
            $visited['__sitemap:'.$url] = true;
            if (str_ends_with(strtolower($url), '.gz')) {
                return;
            }
            try {
                $res = $req->get($url);
                if (! $res->successful()) {
                    return;
                }
                $body = (string) $res->body();
                if (trim($body) === '') {
                    return;
                }
                $sx = $parseXml($body);
                if (! $sx) {
                    return;
                }
                $rootName = strtolower($sx->getName());

                if ($rootName === 'sitemapindex') {
                    $i = 0;
                    foreach ($sx->sitemap as $sm) {
                        $loc = trim((string) ($sm->loc ?? ''));
                        if ($loc === '') continue;
                        $host = parse_url($loc, PHP_URL_HOST);
                        $host = is_string($host) ? strtolower($host) : '';
                        if ($baseHost !== '' && $host !== '' && $host !== $baseHost) continue;
                        $fetchAndCollect($loc);
                        $i++;
                        if ($i >= 30 || count($urls) >= $limit) break;
                    }
                    return;
                }

                if ($rootName === 'urlset') {
                    foreach ($sx->url as $u) {
                        $loc = trim((string) ($u->loc ?? ''));
                        if ($loc === '') continue;
                        $pushUrl($loc);
                        if (count($urls) >= $limit) break;
                    }
                }
            } catch (\Throwable) {
            }
        };

        if (is_string($rawXml) && $rawXml !== '') {
            $sx = $parseXml($rawXml);
            if ($sx) {
                $rootName = strtolower($sx->getName());
                if ($rootName === 'sitemapindex') {
                    $visited['__sitemap:'.$usedSitemap] = true;
                    $i = 0;
                    foreach ($sx->sitemap as $sm) {
                        $loc = trim((string) ($sm->loc ?? ''));
                        if ($loc === '') continue;
                        $fetchAndCollect($loc);
                        $i++;
                        if ($i >= 30 || count($urls) >= $limit) break;
                    }
                    $scanMessage = $usedSitemap ? 'Đã quét sitemap: '.$usedSitemap : null;
                } elseif ($rootName === 'urlset') {
                    foreach ($sx->url as $u) {
                        $loc = trim((string) ($u->loc ?? ''));
                        if ($loc === '') continue;
                        $pushUrl($loc);
                        if (count($urls) >= $limit) break;
                    }
                    $scanMessage = $usedSitemap ? 'Đã quét sitemap: '.$usedSitemap : null;
                } else {
                    $scanMessage = 'Sitemap không đúng định dạng XML urlset/sitemapindex.';
                }
            } else {
                $scanMessage = 'Không đọc được sitemap (XML lỗi hoặc bị chặn).';
            }
        } else {
            $scanMessage = 'Không tìm thấy sitemap hoặc không truy cập được.';
        }

        return view('dashboard.quick-scan', [
            'baseUrl' => $baseUrl,
            'sitemapUrl' => $sitemapUrl,
            'limit' => $limit,
            'urls' => $urls,
            'scanMessage' => $scanMessage,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'base_url' => ['required', 'url', 'max:2048'],
            'urls' => ['array'],
            'urls.*' => ['nullable', 'url', 'max:2048'],
        ]);

        $userId = $request->user()->effectiveUserId();
        $limit = User::resolveProductLimitById($userId);
        $currentCount = Product::query()->where('user_id', $userId)->count();
        $remaining = max(0, $limit - $currentCount);

        $baseUrl = trim((string) $validated['base_url']);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        $baseHost = is_string($baseHost) ? strtolower($baseHost) : '';

        $urls = $validated['urls'] ?? [];
        $clean = [];
        foreach ($urls as $u) {
            $u = is_string($u) ? trim($u) : '';
            if ($u === '') continue;
            $host = parse_url($u, PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : '';
            if ($baseHost !== '' && $host !== '' && $host !== $baseHost) continue;
            $clean[$u] = true;
        }
        $cleanUrls = array_keys($clean);

        $created = 0;
        $skippedExists = 0;
        $skippedInvalid = count($urls) - count($cleanUrls);
        $reachedLimit = false;
        $productIdsToScrape = [];

        foreach ($cleanUrls as $url) {
            if ($remaining <= 0) {
                $reachedLimit = true;
                break;
            }

            $exists = Product::query()
                ->where('user_id', $userId)
                ->where('product_url', $url)
                ->exists();
            if ($exists) {
                $skippedExists++;
                continue;
            }

            $p = Product::create([
                'user_id' => $userId,
                'name' => $this->guessProductNameFromUrl($url),
                'price' => 0,
                'product_url' => $url,
            ]);
            $created++;
            $remaining--;
            $productIdsToScrape[] = (int) $p->id;
        }

        $productIdsToScrape = array_values(array_unique($productIdsToScrape));
        foreach ($productIdsToScrape as $id) {
            ScrapeProductPrices::dispatch($id);
        }

        $msg = 'Đã thêm '.$created.' sản phẩm vào danh sách';
        if ($skippedExists > 0) {
            $msg .= '. Bỏ qua '.$skippedExists.' link đã có';
        }
        if ($skippedInvalid > 0) {
            $msg .= '. Bỏ qua '.$skippedInvalid.' link không hợp lệ/khác domain';
        }
        if ($reachedLimit) {
            $msg .= '. Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản';
        }

        return redirect()->route('dashboard')->with('status', $msg);
    }

    private function guessProductNameFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? trim($host) : '';
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\\./', '', $host) ?? $host;
        $host = $host !== '' ? $host : 'Sản phẩm quét';

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        $tail = $path !== '' ? trim(basename($path), " \t\n\r\0\x0B/") : '';
        if ($tail !== '') {
            $tail = mb_substr($tail, 0, 60);
            $host .= ' - '.$tail;
        }

        return mb_substr($host, 0, 255);
    }
}

