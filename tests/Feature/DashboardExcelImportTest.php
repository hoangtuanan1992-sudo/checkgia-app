<?php

namespace Tests\Feature;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DashboardExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_template_and_import_excel_products(): void
    {
        $user = User::factory()->create();
        $site = CompetitorSite::query()->create([
            'user_id' => $user->id,
            'name' => 'fptshop.com.vn',
            'domain' => 'fptshop.com.vn',
            'position' => 1,
        ]);

        UserScrapeSetting::query()->create([
            'user_id' => $user->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
        ]);

        Http::fake([
            'https://shop.test/import-product' => Http::response('<html><body><h1>Imported Phone</h1><div id="price">12.340.000d</div></body></html>', 200),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.import.template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $file = $this->excelUpload([
            ['Sản phẩm của tôi', 'Nhóm sản phẩm', 'fptshop.com.vn'],
            ['https://shop.test/import-product', 'Điện thoại', 'https://fptshop.com.vn/import-product'],
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.import.excel'), [
                'excel_file' => $file,
            ])
            ->assertRedirect(route('dashboard'));

        $group = ProductGroup::query()->where('user_id', $user->id)->where('name', 'Điện thoại')->firstOrFail();

        $this->assertDatabaseHas('products', [
            'user_id' => $user->id,
            'product_group_id' => $group->id,
            'name' => 'Imported Phone',
            'price' => 12340000,
            'product_url' => 'https://shop.test/import-product',
        ]);

        $this->assertDatabaseHas('competitors', [
            'competitor_site_id' => $site->id,
            'name' => 'fptshop.com.vn',
            'url' => 'https://fptshop.com.vn/import-product',
        ]);
    }

    public function test_subuser_excel_import_only_uses_allowed_product_groups_and_competitor_sites(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $allowedGroup = ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Allowed Group',
        ]);
        ProductGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Blocked Group',
        ]);
        $allowedSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'allowed-shop.test',
            'domain' => 'allowed-shop.test',
            'position' => 1,
        ]);
        $blockedSite = CompetitorSite::query()->create([
            'user_id' => $owner->id,
            'name' => 'blocked-shop.test',
            'domain' => 'blocked-shop.test',
            'position' => 2,
        ]);
        $allowedCompetitorGroup = CompetitorSiteGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Allowed Sites',
        ]);
        $allowedCompetitorGroup->competitorSites()->sync([$allowedSite->id]);

        UserScrapeSetting::query()->create([
            'user_id' => $owner->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
        ]);

        Http::fake([
            'https://shop.test/allowed-import' => Http::response('<html><body><h1>Allowed Import</h1><div id="price">1.000.000d</div></body></html>', 200),
            'https://shop.test/blocked-import' => Http::response('<html><body><h1>Blocked Import</h1><div id="price">2.000.000d</div></body></html>', 200),
        ]);

        $subUser = User::factory()->create([
            'role' => 'viewer',
            'parent_user_id' => $owner->id,
            'visible_product_group_ids' => [$allowedGroup->id],
            'visible_competitor_site_group_ids' => [$allowedCompetitorGroup->id],
        ]);

        $file = $this->excelUpload([
            ['Sản phẩm của tôi', 'Nhóm sản phẩm', 'allowed-shop.test', 'blocked-shop.test'],
            ['https://shop.test/allowed-import', 'Allowed Group', 'https://allowed-shop.test/item', 'https://blocked-shop.test/item'],
            ['https://shop.test/blocked-import', 'Blocked Group', 'https://allowed-shop.test/blocked', ''],
        ]);

        $this->actingAs($subUser)
            ->post(route('dashboard.import.excel'), [
                'excel_file' => $file,
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('products', [
            'user_id' => $owner->id,
            'product_group_id' => $allowedGroup->id,
            'name' => 'Allowed Import',
            'product_url' => 'https://shop.test/allowed-import',
        ]);
        $this->assertDatabaseMissing('products', [
            'user_id' => $owner->id,
            'name' => 'Blocked Import',
        ]);
        $this->assertDatabaseHas('competitors', [
            'competitor_site_id' => $allowedSite->id,
            'url' => 'https://allowed-shop.test/item',
        ]);
        $this->assertDatabaseMissing('competitors', [
            'competitor_site_id' => $blockedSite->id,
            'url' => 'https://blocked-shop.test/item',
        ]);
    }

    private function excelUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
                $sheet->setCellValue($coordinate, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'checkgia_excel_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
