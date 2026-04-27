<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeProductPrices;
use App\Models\CompetitorPrice;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteTemplate;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Models\ShopeeProduct;
use App\Models\User;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use App\Services\PriceScraper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardProductController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_url' => ['required', 'url', 'max:2048'],
            'product_group_id' => ['nullable', 'integer'],
            'product_group_name' => ['nullable', 'string', 'max:255'],
            'competitor_urls' => ['array'],
            'competitor_urls.*' => ['nullable', 'url', 'max:2048'],
        ]);

        $userId = $request->user()->effectiveUserId();
        $groupId = $validated['product_group_id'] ?? null;
        if ($groupId) {
            $exists = ProductGroup::query()->where('user_id', $userId)->where('id', $groupId)->exists();
            if (! $exists) {
                $groupId = null;
            }
        }

        $groupName = trim((string) ($validated['product_group_name'] ?? ''));
        if (! $groupId && $groupName !== '') {
            $group = ProductGroup::firstOrCreate([
                'user_id' => $userId,
                'name' => $groupName,
            ]);
            $groupId = $group->id;
        }

        $product = Product::query()
            ->where('user_id', $userId)
            ->where('product_url', $validated['product_url'])
            ->first();

        if ($product) {
            if ($groupId && (int) $product->product_group_id !== (int) $groupId) {
                $product->product_group_id = $groupId;
                $product->save();
            }

            $sites = CompetitorSite::query()
                ->where('user_id', $userId)
                ->get(['id', 'name', 'domain', 'position', 'price_xpath', 'price_regex'])
                ->keyBy('id');
            $sitesByDomain = $sites
                ->filter(fn ($s) => is_string($s->domain) && trim((string) $s->domain) !== '')
                ->keyBy('domain');

            $urls = $validated['competitor_urls'] ?? [];
            foreach ($urls as $key => $url) {
                $url = is_string($url) ? trim($url) : null;

                if ($url === null || $url === '') {
                    continue;
                }

                $site = null;
                $keyInt = is_string($key) && preg_match('/^\d+$/', $key) ? (int) $key : (is_int($key) ? (int) $key : null);
                if (! is_null($keyInt) && $sites->has($keyInt)) {
                    $site = $sites->get($keyInt);
                } else {
                    $domain = CompetitorSite::normalizedDomainFromUrl($url);
                    if (! $domain) {
                        continue;
                    }

                    $site = $sitesByDomain->get($domain);
                    if (! $site) {
                        $site = $sites->first(fn ($s) => (string) $s->name === (string) $domain);
                        if (! $site) {
                            $site = CompetitorSite::query()
                                ->where('user_id', $userId)
                                ->where(function ($q) use ($domain) {
                                    $q->where('domain', $domain)->orWhere('name', $domain);
                                })
                                ->first();
                        }

                        if ($site) {
                            if (! $site->domain) {
                                $site->domain = $domain;
                                $site->save();
                            }
                        } else {
                            $nextPos = ((int) CompetitorSite::query()->where('user_id', $userId)->max('position')) + 1;
                            $site = CompetitorSite::create([
                                'user_id' => $userId,
                                'name' => $domain,
                                'domain' => $domain,
                                'position' => $nextPos,
                            ]);
                        }

                        $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
                        if ($template) {
                            $template->applyToCompetitorSite($site);
                        }

                        $site = $site->fresh();

                        $sites->put($site->id, $site);
                        $sitesByDomain->put($domain, $site);
                    }
                }

                $competitor = $product->competitors()->firstOrNew([
                    'competitor_site_id' => $site->id,
                ]);
                $competitor->name = $site->name;
                $competitor->url = $url;
                $competitor->save();
            }

            ScrapeProductPrices::dispatch($product->id);

            return redirect()->route('dashboard')->with('status', 'Đã cập nhật sản phẩm');
        }

        $limit = User::resolveProductLimitById($userId);
        $used = (int) Product::query()->where('user_id', $userId)->count()
            + (int) ShopeeProduct::query()->where('user_id', $userId)->count();
        if ($used >= $limit) {
            return back()
                ->withInput()
                ->with('status', 'Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản');
        }

        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        if (! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return redirect()
                ->route('dashboard.competitors')
                ->with('status', 'Vui lòng cài đặt XPath lấy tên và giá của bạn trước.');
        }

        $scraper = new PriceScraper;
        $nameDebug = ['tried' => []];
        $priceDebug = ['tried' => []];
        $tgdd = $scraper->scrapeTgddPriceAndName((string) $validated['product_url']);
        if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
            $price = $tgdd['price'];
            $name = isset($tgdd['name']) && is_string($tgdd['name']) && trim($tgdd['name']) !== '' ? trim($tgdd['name']) : null;
            if (! $name) {
                $html = $scraper->fetchHtml($validated['product_url']);
                $name = $scraper->extractTitle($html);
            }
        } else {
            $html = $scraper->fetchHtml($validated['product_url']);

            $nameXpaths = array_merge(
                [(string) $settings->own_name_xpath],
                UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'name')->orderBy('position')->pluck('xpath')->all()
            );
            $priceXpaths = array_merge(
                [(string) $settings->own_price_xpath],
                UserScrapeXpath::query()->where('user_id', $userId)->where('type', 'price')->orderBy('position')->pluck('xpath')->all()
            );

            $nameDebug = $scraper->extractFirstByXPathsWithDebug($html, $nameXpaths);
            $name = $nameDebug['value'] ?? null;
            if (! $name) {
                $name = $scraper->extractTitle($html);
            }
            $priceDebug = $scraper->extractFirstByXPathsWithDebug($html, $priceXpaths);
            $priceRaw = $priceDebug['value'] ?? null;
            $price = $scraper->parsePriceToInt($priceRaw, $settings->price_regex);

            if (! $name || is_null($price)) {
                $structured = $scraper->extractProductNameAndPriceFromStructuredData($html);
                if (! $name && is_string($structured['name'] ?? null) && trim((string) $structured['name']) !== '') {
                    $name = (string) $structured['name'];
                }
                if (is_null($price) && is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                    $price = $scraper->parsePriceToInt((string) $structured['price_raw'], $settings->price_regex);
                }
            }

            if (is_null($price) || (int) $price <= 0) {
                $tgdd = $scraper->scrapeTgddPriceAndName((string) $validated['product_url']);
                if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                    $price = $tgdd['price'];
                    if (! $name && isset($tgdd['name']) && is_string($tgdd['name']) && trim($tgdd['name']) !== '') {
                        $name = trim($tgdd['name']);
                    }
                }
            }
        }

        if (! $name || is_null($price)) {
            $parts = [];
            if (! $name) {
                $lines = ['Tên: không trích xuất được bằng XPath.'];
                $lines[] = '- own_name_xpath: '.(string) $settings->own_name_xpath;
                $fallbacks = array_slice($nameDebug['tried'] ?? [], 1);
                foreach ($fallbacks as $idx => $xp) {
                    $lines[] = '- tên dự phòng #'.($idx + 1).': '.$xp;
                }
                $parts[] = implode("\n", $lines);
            }
            if (is_null($price)) {
                $lines = ['Giá: không trích xuất được bằng XPath.'];
                $lines[] = '- own_price_xpath: '.(string) $settings->own_price_xpath;
                $fallbacks = array_slice($priceDebug['tried'] ?? [], 1);
                foreach ($fallbacks as $idx => $xp) {
                    $lines[] = '- giá dự phòng #'.($idx + 1).': '.$xp;
                }
                if ($settings->price_regex) {
                    $lines[] = '- Regex lọc giá: '.(string) $settings->price_regex;
                }
                $parts[] = implode("\n", $lines);
            }

            return back()
                ->withInput()
                ->withErrors(['product_url' => implode("\n\n", $parts)]);
        }

        $product = Product::create([
            'user_id' => $userId,
            'product_group_id' => $groupId,
            'name' => $name,
            'price' => $price,
            'product_url' => $validated['product_url'],
        ]);

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'price' => $price,
            'fetched_at' => now(),
        ]);

        $sites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }])
            ->get(['id', 'name', 'domain', 'position', 'price_xpath', 'price_regex'])
            ->keyBy('id');
        $sitesByDomain = $sites
            ->filter(fn ($s) => is_string($s->domain) && trim((string) $s->domain) !== '')
            ->keyBy('domain');

        $urls = $validated['competitor_urls'] ?? [];
        foreach ($urls as $key => $url) {
            $url = is_string($url) ? trim($url) : null;

            if ($url === null || $url === '') {
                continue;
            }

            $site = null;
            $keyInt = is_string($key) && preg_match('/^\d+$/', $key) ? (int) $key : (is_int($key) ? (int) $key : null);
            if (! is_null($keyInt) && $sites->has($keyInt)) {
                $site = $sites->get($keyInt);
            } else {
                $domain = CompetitorSite::normalizedDomainFromUrl($url);
                if (! $domain) {
                    continue;
                }

                $site = $sitesByDomain->get($domain);
                if (! $site) {
                    $site = $sites->first(fn ($s) => (string) $s->name === (string) $domain);
                    if (! $site) {
                        $site = CompetitorSite::query()
                            ->where('user_id', $userId)
                            ->where(function ($q) use ($domain) {
                                $q->where('domain', $domain)->orWhere('name', $domain);
                            })
                            ->first();
                    }

                    if ($site) {
                        if (! $site->domain) {
                            $site->domain = $domain;
                            $site->save();
                        }
                    } else {
                        $nextPos = ((int) CompetitorSite::query()->where('user_id', $userId)->max('position')) + 1;
                        $site = CompetitorSite::create([
                            'user_id' => $userId,
                            'name' => $domain,
                            'domain' => $domain,
                            'position' => $nextPos,
                        ]);
                    }

                    $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
                    if ($template) {
                        $template->applyToCompetitorSite($site);
                    }

                    $site = $site->fresh(['scrapeXpaths' => function ($q) {
                        $q->orderBy('type')->orderBy('position');
                    }]);

                    $sites->put($site->id, $site);
                    $sitesByDomain->put($domain, $site);
                }
            }

            $competitor = $product->competitors()->firstOrNew([
                'competitor_site_id' => $site->id,
            ]);
            $competitor->name = $site->name;
            $competitor->url = $url;
            $competitor->save();

            try {
                $tgdd = $scraper->scrapeTgddPriceAndName($url);
                if (is_array($tgdd) && isset($tgdd['price']) && is_int($tgdd['price'])) {
                    $cPrice = $tgdd['price'];
                    $latest = $competitor->prices()->latest('fetched_at')->first();
                    if (! $latest || (int) $latest->price !== (int) $cPrice) {
                        CompetitorPrice::create([
                            'competitor_id' => $competitor->id,
                            'price' => $cPrice,
                            'fetched_at' => now(),
                        ]);
                    }

                    continue;
                }
            } catch (\Throwable $e) {
            }

            if ($site->price_xpath) {
                try {
                    $cHtml = $scraper->fetchHtml($url);
                    $fallbacks = $site->scrapeXpaths->where('type', 'price')->sortBy('position')->pluck('xpath')->all();
                    $cPriceRaw = $scraper->extractFirstByXPaths($cHtml, array_merge([(string) $site->price_xpath], $fallbacks));
                    $cPrice = $scraper->parsePriceToInt($cPriceRaw, $site->price_regex);
                    if (is_null($cPrice)) {
                        $structured = $scraper->extractProductNameAndPriceFromStructuredData($cHtml);
                        if (is_string($structured['price_raw'] ?? null) && trim((string) $structured['price_raw']) !== '') {
                            $cPrice = $scraper->parsePriceToInt((string) $structured['price_raw'], $site->price_regex);
                        }
                    }
                    if (! is_null($cPrice)) {
                        $latest = $competitor->prices()->latest('fetched_at')->first();
                        if (! $latest || (int) $latest->price !== (int) $cPrice) {
                            CompetitorPrice::create([
                                'competitor_id' => $competitor->id,
                                'price' => $cPrice,
                                'fetched_at' => now(),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return redirect()->route('dashboard')->with('status', 'Đã thêm sản phẩm');
    }

    public function downloadImportTemplate(Request $request): StreamedResponse
    {
        if (! class_exists(Spreadsheet::class) || ! class_exists(IOFactory::class)) {
            $rows = [
                ['Link sản phẩm của bạn', 'Link đối thủ 1', 'Link đối thủ 2', 'Link đối thủ 3'],
                ['https://example.com/san-pham-cua-ban', 'https://doithu1.com/san-pham', 'https://doithu2.com/san-pham', 'https://doithu3.com/san-pham'],
            ];

            return response()->streamDownload(function () use ($rows) {
                echo "\xEF\xBB\xBF";
                foreach ($rows as $row) {
                    $escaped = array_map(function ($v) {
                        $v = (string) $v;
                        $v = str_replace('"', '""', $v);

                        return '"'.$v.'"';
                    }, $row);
                    echo implode(',', $escaped)."\r\n";
                }
            }, 'mau-import-san-pham.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $sheet = new Spreadsheet;
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Import');

        $ws->setCellValue('A1', 'Link sản phẩm của bạn');
        $ws->setCellValue('B1', 'Link đối thủ 1');
        $ws->setCellValue('C1', 'Link đối thủ 2');
        $ws->setCellValue('D1', 'Link đối thủ 3');

        $ws->setCellValue('A2', 'https://example.com/san-pham-cua-ban');
        $ws->setCellValue('B2', 'https://doithu1.com/san-pham');
        $ws->setCellValue('C2', 'https://doithu2.com/san-pham');
        $ws->setCellValue('D2', 'https://doithu3.com/san-pham');

        return response()->streamDownload(function () use ($sheet) {
            $writer = IOFactory::createWriter($sheet, 'Xlsx');
            $writer->save('php://output');
        }, 'mau-import-san-pham.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user || $user->isViewer(), 403);

        $userId = $user->effectiveUserId();
        $settings = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        if (! $settings->own_name_xpath || ! $settings->own_price_xpath) {
            return redirect()
                ->route('dashboard.competitors')
                ->with('status', 'Vui lòng cài đặt XPath lấy tên và giá của bạn trước.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
        ]);

        $limit = User::resolveProductLimitById($userId);
        $used = (int) Product::query()->where('user_id', $userId)->count()
            + (int) ShopeeProduct::query()->where('user_id', $userId)->count();
        $remaining = max(0, $limit - $used);
        if ($remaining <= 0) {
            return back()->with('status', 'Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản');
        }

        $file = $data['file'];
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => 'Không đọc được file.']);
        }

        $getCellValue = null;
        $highestRow = 1;
        $highestColIndex = 1;

        if (class_exists(IOFactory::class) && class_exists(Coordinate::class)) {
            $sheet = IOFactory::load($path);
            $ws = $sheet->getActiveSheet();
            $highestRow = max(1, (int) $ws->getHighestDataRow());
            $highestCol = (string) $ws->getHighestDataColumn();
            $highestColIndex = max(1, (int) Coordinate::columnIndexFromString($highestCol));
            $getCellValue = fn (int $col, int $row) => $ws->getCell([$col, $row])->getValue();
        } else {
            $ext = mb_strtolower((string) $file->getClientOriginalExtension());
            if ($ext !== 'csv') {
                return back()->withErrors(['file' => 'Hệ thống chưa cài thư viện đọc Excel. Vui lòng chạy composer install/update trên server hoặc upload file .csv.']);
            }

            $rows = [];
            $fp = fopen($path, 'r');
            if (! $fp) {
                return back()->withErrors(['file' => 'Không đọc được file.']);
            }

            while (($row = fgetcsv($fp)) !== false) {
                $rows[] = $row;
                if (count($rows) >= 2000) {
                    break;
                }
            }
            fclose($fp);

            $highestRow = max(1, count($rows));
            $highestColIndex = max(1, (int) collect($rows)->map(fn ($r) => is_array($r) ? count($r) : 0)->max());
            $getCellValue = fn (int $col, int $row) => $rows[$row - 1][$col - 1] ?? null;
        }

        $first = trim($this->cellToString($getCellValue(1, 1)));
        $startRow = filter_var($first, FILTER_VALIDATE_URL) ? 1 : 2;

        $sitesByDomain = [];
        $productIdsToScrape = [];
        $createdProducts = 0;
        $updatedProducts = 0;
        $addedCompetitors = 0;
        $skippedRows = 0;
        $reachedLimit = false;

        for ($row = $startRow; $row <= $highestRow; $row++) {
            if ($createdProducts >= 1000) {
                break;
            }

            $ownUrl = trim($this->cellToString($getCellValue(1, $row)));
            if ($ownUrl === '') {
                continue;
            }
            if (! filter_var($ownUrl, FILTER_VALIDATE_URL)) {
                $skippedRows++;

                continue;
            }

            $product = Product::query()
                ->where('user_id', $userId)
                ->where('product_url', $ownUrl)
                ->first();

            $productTouched = false;
            if (! $product) {
                if ($remaining <= 0) {
                    $reachedLimit = true;
                    break;
                }

                $product = Product::create([
                    'user_id' => $userId,
                    'name' => $this->guessProductNameFromUrl($ownUrl),
                    'price' => 0,
                    'product_url' => $ownUrl,
                ]);

                $createdProducts++;
                $remaining--;
                $productTouched = true;
            }

            $competitorUrls = [];
            for ($col = 2; $col <= $highestColIndex; $col++) {
                $val = trim($this->cellToString($getCellValue($col, $row)));
                if ($val === '') {
                    continue;
                }
                if (! filter_var($val, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $competitorUrls[$val] = true;
            }

            foreach (array_keys($competitorUrls) as $competitorUrl) {
                $domain = CompetitorSite::normalizedDomainFromUrl($competitorUrl);
                if (! $domain) {
                    continue;
                }

                $site = $sitesByDomain[$domain] ?? null;
                if (! $site) {
                    $site = $this->resolveCompetitorSite($userId, $domain);
                    $sitesByDomain[$domain] = $site;
                }

                $competitor = $product->competitors()->firstOrNew([
                    'competitor_site_id' => $site->id,
                ]);
                $beforeUrl = (string) ($competitor->url ?? '');

                $competitor->name = $site->name ?: $domain;
                $competitor->url = $competitorUrl;
                $competitor->save();

                if (! $competitor->wasRecentlyCreated && $beforeUrl === $competitorUrl) {
                    continue;
                }

                $addedCompetitors++;
                $productTouched = true;
            }

            if ($productTouched) {
                $productIdsToScrape[] = (int) $product->id;
                if ($createdProducts === 0) {
                    $updatedProducts++;
                }
            }
        }

        $productIdsToScrape = array_values(array_unique($productIdsToScrape));
        foreach ($productIdsToScrape as $id) {
            ScrapeProductPrices::dispatch($id);
        }

        $msg = 'Đã nhập '.$createdProducts.' sản phẩm';
        if ($addedCompetitors > 0) {
            $msg .= ', '.$addedCompetitors.' link đối thủ';
        }
        if ($skippedRows > 0) {
            $msg .= '. Bỏ qua '.$skippedRows.' dòng không hợp lệ';
        }
        if ($reachedLimit) {
            $msg .= '. Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản';
        }

        return redirect()->route('dashboard')->with('status', $msg);
    }

    private function cellToString(mixed $v): string
    {
        if ($v instanceof RichText) {
            return $v->getPlainText();
        }

        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        if (is_scalar($v)) {
            return (string) $v;
        }

        return '';
    }

    private function guessProductNameFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? trim($host) : '';
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $host = $host !== '' ? $host : 'Sản phẩm import';

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        $tail = $path !== '' ? trim(basename($path), " \t\n\r\0\x0B/") : '';
        if ($tail !== '') {
            $tail = mb_substr($tail, 0, 60);
            $host .= ' - '.$tail;
        }

        return mb_substr($host, 0, 255);
    }

    private function resolveCompetitorSite(int $userId, string $domain): CompetitorSite
    {
        $site = CompetitorSite::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($domain) {
                $q->where('domain', $domain)->orWhere('name', $domain);
            })
            ->first();

        if ($site) {
            if (! $site->domain) {
                $site->domain = $domain;
                $site->save();
            }
        } else {
            $nextPos = ((int) CompetitorSite::query()->where('user_id', $userId)->max('position')) + 1;
            $site = CompetitorSite::create([
                'user_id' => $userId,
                'name' => $domain,
                'domain' => $domain,
                'position' => $nextPos,
            ]);
        }

        $template = CompetitorSiteTemplate::query()->where('domain', $domain)->where('is_approved', true)->first();
        if ($template) {
            $template->applyToCompetitorSite($site);
        }

        return $site;
    }
}
