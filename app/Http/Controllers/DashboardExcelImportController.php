<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductPriceHistory;
use App\Services\ConfiguredProductScraper;
use App\Services\PriceScraper;
use App\Support\ProductLimit;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DashboardExcelImportController extends Controller
{
    public function template(Request $request): StreamedResponse
    {
        $authUser = $request->user();
        $userId = $authUser->effectiveUserId();
        $competitorSites = $this->visibleCompetitorSites($authUser, $userId);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Nhap san pham');

        $headers = [
            'Sản phẩm của tôi',
            'Nhóm sản phẩm',
        ];

        if ($competitorSites->isNotEmpty()) {
            foreach ($competitorSites as $site) {
                $headers[] = (string) $site->name;
            }
        } else {
            $headers[] = 'Link đối thủ 1';
            $headers[] = 'Link đối thủ 2';
            $headers[] = 'Link đối thủ 3';
        }

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column.'1', $header);
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('EAF3FF');
        $sheet->freezePane('A2');
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $help = $spreadsheet->createSheet();
        $help->setTitle('Huong dan');
        $help->setCellValue('A1', 'Cột 1: nhập link sản phẩm của shop. Nếu chỉ nhập tên sản phẩm, hệ thống vẫn thêm sản phẩm với giá 0đ.');
        $help->setCellValue('A2', 'Cột 2: nhập tên nhóm sản phẩm. Tài khoản chính được tự tạo nhóm mới, tài khoản con chỉ được dùng nhóm đã cấp quyền.');
        $help->setCellValue('A3', 'Từ cột 3 trở đi: nhập link đối thủ. Nếu tiêu đề cột là tên đối thủ đã có thì link sẽ vào đúng cột đó; nếu không, hệ thống lấy domain từ link.');
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'checkgia_file_mau_nhap_excel.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'excel_file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $validated['excel_file'];
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return back()
                ->withInput()
                ->withErrors(['excel_file' => 'Vui lòng chọn file Excel dạng .xlsx, .xls hoặc .csv.']);
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (Throwable) {
            return back()
                ->withInput()
                ->withErrors(['excel_file' => 'Không đọc được file Excel. Vui lòng tải file mẫu và nhập lại dữ liệu.']);
        }

        try {
            $stats = $this->importSheet($request, $spreadsheet->getActiveSheet());
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $message = 'Đã nhập Excel: tạo mới '.$stats['created'].' sản phẩm, cập nhật '.$stats['updated'].' sản phẩm, thêm/cập nhật '.$stats['links'].' link đối thủ.';
        if ($stats['skippedRows'] > 0 || $stats['skippedLinks'] > 0) {
            $message .= ' Bỏ qua '.$stats['skippedRows'].' dòng và '.$stats['skippedLinks'].' link không hợp lệ.';
        }

        return redirect()->route('dashboard')->with('status', $message);
    }

    /**
     * @return array{created:int,updated:int,links:int,skippedRows:int,skippedLinks:int}
     */
    private function importSheet(Request $request, Worksheet $sheet): array
    {
        $authUser = $request->user();
        $userId = $authUser->effectiveUserId();
        $stats = [
            'created' => 0,
            'updated' => 0,
            'links' => 0,
            'skippedRows' => 0,
            'skippedLinks' => 0,
        ];

        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        if ($highestRow < 2) {
            return $stats;
        }

        $competitorSites = $this->visibleCompetitorSites($authUser, $userId);
        $competitorColumnMap = $this->mapCompetitorColumns($sheet, $highestColumn, $competitorSites);
        $nextPosition = ((int) CompetitorSite::query()->where('user_id', $userId)->max('position')) + 1;
        $productGroups = $this->visibleProductGroups($authUser, $userId);
        $scraper = new ConfiguredProductScraper(new PriceScraper);

        for ($row = 2; $row <= $highestRow; $row++) {
            $ownValue = $this->cellString($sheet, 1, $row);
            $groupValue = $this->cellString($sheet, 2, $row);

            if ($ownValue === '' && $this->rowHasNoCompetitorLinks($sheet, $row, $highestColumn)) {
                continue;
            }

            if ($ownValue === '') {
                $stats['skippedRows']++;
                continue;
            }

            [$groupId, $groupError] = $this->resolveProductGroupId($authUser, $userId, $groupValue, $productGroups);
            if ($groupError !== null) {
                $stats['skippedRows']++;
                continue;
            }

            $product = $this->importProduct($userId, $ownValue, $groupId, $scraper);
            if (! $product) {
                $stats['skippedRows']++;
                continue;
            }

            $stats[$product->wasRecentlyCreated ? 'created' : 'updated']++;

            for ($column = 3; $column <= $highestColumn; $column++) {
                $url = $this->cellString($sheet, $column, $row);
                if ($url === '') {
                    continue;
                }

                if (! $this->isUrl($url)) {
                    $stats['skippedLinks']++;
                    continue;
                }

                $mappedSite = $competitorColumnMap[$column] ?? null;
                $site = $this->resolveCompetitorSite($authUser, $userId, $url, $mappedSite, $competitorSites, $nextPosition);
                if (! $site) {
                    $stats['skippedLinks']++;
                    continue;
                }

                $this->upsertCompetitorLink($product, $site, $url);
                $stats['links']++;
            }
        }

        return $stats;
    }

    private function importProduct(int $userId, string $ownValue, ?int $groupId, ConfiguredProductScraper $scraper): ?Product
    {
        $ownUrl = $this->isUrl($ownValue) ? $ownValue : null;
        $name = $ownValue;
        $price = 0;

        if ($ownUrl !== null) {
            try {
                $scraped = $scraper->scrapeOwnProduct($ownUrl, $userId, true);
            } catch (Throwable) {
                $scraped = ['name' => null, 'price' => null];
            }

            $scrapedName = trim((string) ($scraped['name'] ?? ''));
            $name = $scrapedName !== '' ? $scrapedName : $ownUrl;
            $price = is_null($scraped['price'] ?? null) ? 0 : max(0, (int) $scraped['price']);
        }

        $product = $this->findExistingProduct($userId, $ownUrl, $name);
        if (! $product && ProductLimit::wouldExceed($userId)) {
            return null;
        }

        if (! $product) {
            $product = new Product;
            $product->user_id = $userId;
        } elseif (Product::hasSoftDeleteColumn() && $product->trashed()) {
            $product->restore();
        }

        if (Product::hasDeletedByColumn()) {
            $product->deleted_by_user_id = null;
        }

        $product->product_group_id = $groupId;
        $product->name = $name;
        $product->price = $price;
        $product->product_url = $ownUrl;
        $product->save();

        $this->storeOwnPriceHistory($product, $price);

        return $product;
    }

    private function findExistingProduct(int $userId, ?string $ownUrl, string $name): ?Product
    {
        $query = Product::query();
        if (Product::hasSoftDeleteColumn()) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        $query->where('user_id', $userId);
        if ($ownUrl !== null) {
            $query->where('product_url', $ownUrl);
        } else {
            $query->whereNull('product_url')->where('name', $name);
        }

        return $query->first();
    }

    private function storeOwnPriceHistory(Product $product, int $price): void
    {
        if ($price <= 0) {
            return;
        }

        $latest = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->latest('fetched_at')
            ->first();
        if (! $latest || (int) $latest->price !== $price) {
            ProductPriceHistory::create([
                'product_id' => $product->id,
                'price' => $price,
                'fetched_at' => now(),
            ]);
        }
    }

    private function upsertCompetitorLink(Product $product, CompetitorSite $site, string $url): void
    {
        $competitor = Competitor::query()->firstOrNew([
            'product_id' => $product->id,
            'competitor_site_id' => $site->id,
        ]);
        $wasExisting = $competitor->exists;
        $oldUrl = trim((string) ($competitor->url ?? ''));

        $competitor->name = $site->name;
        $competitor->url = $url;
        $competitor->save();

        if (! $wasExisting || $oldUrl !== $url) {
            $competitor->markPriceMissing();
        }
    }

    private function resolveProductGroupId($authUser, int $userId, string $groupValue, Collection $productGroups): array
    {
        $groupValue = trim($groupValue);

        if ($authUser->isViewer()) {
            if ($groupValue === '') {
                return [null, 'missing_group'];
            }

            $group = $this->findGroupInCollection($productGroups, $groupValue);

            return $group ? [(int) $group->id, null] : [null, 'invalid_group'];
        }

        if ($groupValue === '') {
            return [null, null];
        }

        $group = $this->findGroupInCollection($productGroups, $groupValue);
        if ($group) {
            return [(int) $group->id, null];
        }

        $group = ProductGroup::query()->firstOrCreate([
            'user_id' => $userId,
            'name' => $groupValue,
        ]);
        $productGroups->push($group);

        return [(int) $group->id, null];
    }

    private function findGroupInCollection(Collection $productGroups, string $groupValue): ?ProductGroup
    {
        if (ctype_digit($groupValue)) {
            $byId = $productGroups->firstWhere('id', (int) $groupValue);
            if ($byId) {
                return $byId;
            }
        }

        $needle = $this->normalizedText($groupValue);

        return $productGroups->first(fn ($group) => $this->normalizedText((string) $group->name) === $needle);
    }

    private function resolveCompetitorSite($authUser, int $userId, string $url, ?CompetitorSite $mappedSite, Collection $competitorSites, int &$nextPosition): ?CompetitorSite
    {
        if ($mappedSite) {
            return $competitorSites->contains('id', $mappedSite->id) ? $mappedSite : null;
        }

        $domain = CompetitorSite::normalizedDomainFromUrl($url);
        $name = CompetitorSite::normalizedNameFromUserInput($url);
        $site = $competitorSites->first(function ($site) use ($domain, $name) {
            $siteDomain = $site->domain ?: CompetitorSite::normalizedDomainFromUserInput((string) $site->name);

            return ($domain && $siteDomain === $domain)
                || $this->normalizedText((string) $site->name) === $this->normalizedText($name);
        });

        if ($site) {
            return $site;
        }

        if ($authUser->isViewer()) {
            return null;
        }

        $data = [
            'user_id' => $userId,
            'name' => $name !== '' ? $name : (string) $domain,
            'position' => $nextPosition++,
        ];
        if (Schema::hasColumn('competitor_sites', 'domain')) {
            $data['domain'] = $domain;
        }

        $site = CompetitorSite::query()->create($data);
        $competitorSites->push($site);

        return $site;
    }

    private function mapCompetitorColumns(Worksheet $sheet, int $highestColumn, Collection $competitorSites): array
    {
        $lookup = [];
        foreach ($competitorSites as $site) {
            foreach ([$site->name, $site->domain, CompetitorSite::normalizedDomainFromUserInput((string) $site->name)] as $candidate) {
                $key = $this->siteLookupKey($candidate);
                if ($key !== '') {
                    $lookup[$key] = $site;
                }
            }
        }

        $map = [];
        for ($column = 3; $column <= $highestColumn; $column++) {
            $header = $this->cellString($sheet, $column, 1);
            $key = $this->siteLookupKey($header);
            if ($key !== '' && isset($lookup[$key])) {
                $map[$column] = $lookup[$key];
            }
        }

        return $map;
    }

    private function visibleProductGroups($authUser, int $userId): Collection
    {
        $query = ProductGroup::query()->where('user_id', $userId)->orderBy('name');
        if ($authUser->isViewer()) {
            $ids = $authUser->visibleProductGroupIds();
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $ids);
            }
        }

        return $query->get(['id', 'user_id', 'name']);
    }

    private function visibleCompetitorSites($authUser, int $userId): Collection
    {
        $query = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name');

        if ($authUser->isViewer()) {
            $groupIds = $authUser->visibleCompetitorSiteGroupIds();
            if ($groupIds === [] || ! Schema::hasTable('competitor_site_groups') || ! Schema::hasTable('competitor_site_group_sites')) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('groups', fn ($q) => $q->whereIn('competitor_site_groups.id', $groupIds));
            }
        }

        $columns = ['id', 'user_id', 'name', 'position'];
        if (Schema::hasColumn('competitor_sites', 'domain')) {
            $columns[] = 'domain';
        }

        return $query->get($columns);
    }

    private function rowHasNoCompetitorLinks(Worksheet $sheet, int $row, int $highestColumn): bool
    {
        for ($column = 3; $column <= $highestColumn; $column++) {
            if ($this->cellString($sheet, $column, $row) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellString(Worksheet $sheet, int $column, int $row): string
    {
        $coordinate = Coordinate::stringFromColumnIndex($column).$row;
        $cell = $sheet->getCell($coordinate);
        $value = trim((string) $cell->getFormattedValue());
        $hyperlink = trim((string) $cell->getHyperlink()->getUrl());

        if ($hyperlink !== '' && ! $this->isUrl($value) && $this->isUrl($hyperlink)) {
            return $hyperlink;
        }

        return $value;
    }

    private function siteLookupKey(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        $domain = CompetitorSite::normalizedDomainFromUserInput($value);
        if ($domain) {
            return 'domain:'.$domain;
        }

        return 'name:'.$this->normalizedText($value);
    }

    private function normalizedText(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function isUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
