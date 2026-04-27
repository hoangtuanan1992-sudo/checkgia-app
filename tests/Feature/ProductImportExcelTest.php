<?php

namespace Tests\Feature;

use App\Jobs\ScrapeProductPrices;
use App\Models\Product;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ProductImportExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_import_products_and_competitors_from_xlsx(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        UserScrapeSetting::create([
            'user_id' => $user->id,
            'own_name_xpath' => '//h1',
            'own_price_xpath' => '//*[@id="price"]',
            'price_regex' => null,
        ]);

        $spreadsheet = new Spreadsheet;
        $ws = $spreadsheet->getActiveSheet();
        $ws->setCellValue('A1', 'Link sản phẩm của bạn');
        $ws->setCellValue('B1', 'Link đối thủ 1');
        $ws->setCellValue('A2', 'https://example.com/p1');
        $ws->setCellValue('B2', 'https://doithu.com/p1');

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);

        $file = UploadedFile::fake()->createWithContent('import.xlsx', $content ?: '');

        $this->post(route('dashboard.products.import'), [
            'file' => $file,
        ])->assertRedirect(route('dashboard'));

        $product = Product::query()->where('user_id', $user->id)->where('product_url', 'https://example.com/p1')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('competitors', [
            'product_id' => (int) $product->id,
            'url' => 'https://doithu.com/p1',
        ]);

        Queue::assertPushed(ScrapeProductPrices::class);
    }
}
