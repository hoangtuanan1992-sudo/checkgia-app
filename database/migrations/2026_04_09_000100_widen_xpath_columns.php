<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `user_scrape_settings` MODIFY `own_name_xpath` TEXT NULL');
        DB::statement('ALTER TABLE `user_scrape_settings` MODIFY `own_price_xpath` TEXT NULL');
        DB::statement('ALTER TABLE `user_scrape_settings` MODIFY `price_regex` TEXT NULL');

        DB::statement('ALTER TABLE `user_scrape_xpaths` MODIFY `xpath` TEXT NOT NULL');

        DB::statement('ALTER TABLE `competitor_sites` MODIFY `name_xpath` TEXT NULL');
        DB::statement('ALTER TABLE `competitor_sites` MODIFY `price_xpath` TEXT NULL');
        DB::statement('ALTER TABLE `competitor_sites` MODIFY `price_regex` TEXT NULL');

        DB::statement('ALTER TABLE `competitor_site_scrape_xpaths` MODIFY `xpath` TEXT NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `user_scrape_settings` MODIFY `own_name_xpath` VARCHAR(2048) NULL');
        DB::statement('ALTER TABLE `user_scrape_settings` MODIFY `own_price_xpath` VARCHAR(2048) NULL');
        DB::statement('ALTER TABLE `user_scrape_settings` MODIFY `price_regex` VARCHAR(2048) NULL');

        DB::statement('ALTER TABLE `user_scrape_xpaths` MODIFY `xpath` VARCHAR(2048) NOT NULL');

        DB::statement('ALTER TABLE `competitor_sites` MODIFY `name_xpath` VARCHAR(2048) NULL');
        DB::statement('ALTER TABLE `competitor_sites` MODIFY `price_xpath` VARCHAR(2048) NULL');
        DB::statement('ALTER TABLE `competitor_sites` MODIFY `price_regex` VARCHAR(2048) NULL');

        DB::statement('ALTER TABLE `competitor_site_scrape_xpaths` MODIFY `xpath` VARCHAR(2048) NOT NULL');
    }
};
