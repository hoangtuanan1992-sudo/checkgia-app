<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ProductLimit
{
    public static function default(): int
    {
        return 100;
    }

    public static function forUserId(int $userId): int
    {
        if ($userId <= 0 || ! Schema::hasColumn('users', 'product_limit')) {
            return self::default();
        }

        $limit = User::query()->whereKey($userId)->value('product_limit');

        return max(1, (int) ($limit ?: self::default()));
    }

    public static function currentCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int) Product::query()
            ->where('user_id', $userId)
            ->count();
    }

    public static function remainingForUserId(int $userId): int
    {
        return max(0, self::forUserId($userId) - self::currentCount($userId));
    }

    public static function wouldExceed(int $userId, int $newProductCount = 1): bool
    {
        if ($newProductCount <= 0) {
            return false;
        }

        return self::currentCount($userId) + $newProductCount > self::forUserId($userId);
    }

    public static function message(int $userId, int $newProductCount = 1): string
    {
        $limit = self::forUserId($userId);
        $current = self::currentCount($userId);
        $remaining = max(0, $limit - $current);

        if ($newProductCount > 1) {
            return 'Shop này đang có '.$current.'/'.$limit.' sản phẩm so sánh. Chỉ còn thêm được '.$remaining.' sản phẩm, không thể thêm '.$newProductCount.' sản phẩm mới.';
        }

        return 'Shop này đã đạt giới hạn '.$current.'/'.$limit.' sản phẩm so sánh. Vui lòng tăng giới hạn trong Admin trước khi thêm sản phẩm mới.';
    }
}
