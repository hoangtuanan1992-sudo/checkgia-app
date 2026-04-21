<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\Product;
use App\Models\ShopeeCompetitor;
use App\Models\ShopeeProduct;
use App\Models\UserNotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class AlertNotifier
{
    public function notifyOnCompetitorPriceChange(Product $product, Competitor $competitor, int $newPrice, ?int $previousPrice): void
    {
        $setting = UserNotificationSetting::query()->where('user_id', $product->user_id)->first();
        if (! $setting) {
            return;
        }

        $own = (int) $product->price;
        $siteName = $competitor->competitorSite?->name ?? $competitor->name;
        $url = (string) ($competitor->url ?? '');
        $time = now()->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i');

        $diffAmount = $newPrice - $own;
        $diffPercent = $own > 0 ? (($newPrice - $own) / $own) * 100 : null;
        $dropAmount = ! is_null($previousPrice) ? ($previousPrice - $newPrice) : null;

        $base = [
            'product_name' => (string) $product->name,
            'own_price' => (string) $own,
            'own_price_fmt' => $this->formatVnd($own),
            'competitor_name' => (string) $siteName,
            'competitor_price' => (string) $newPrice,
            'competitor_price_fmt' => $this->formatVnd($newPrice),
            'previous_competitor_price' => (string) ($previousPrice ?? ''),
            'previous_competitor_price_fmt' => is_null($previousPrice) ? '' : $this->formatVnd((int) $previousPrice),
            'competitor_url' => (string) $url,
            'diff_amount' => (string) $diffAmount,
            'diff_amount_fmt' => $this->formatVnd($diffAmount),
            'diff_percent' => is_null($diffPercent) ? '' : (string) round($diffPercent, 2),
            'diff_percent_fmt' => is_null($diffPercent) ? '' : $this->formatPercent($diffPercent),
            'drop_amount' => is_null($dropAmount) ? '' : (string) $dropAmount,
            'drop_amount_fmt' => is_null($dropAmount) ? '' : $this->formatVnd((int) $dropAmount),
            'time' => (string) $time,
        ];

        if ($setting->notify_all_price_changes && ! is_null($previousPrice)) {
            $title = $setting->notify_all_price_changes_title ?: 'Biến động giá';
            $body = $setting->notify_all_price_changes_body ?: implode("\n", [
                'Sản phẩm: {product_name}',
                'Giá của bạn: {own_price_fmt}',
                'Đối thủ: {competitor_name}',
                'Giá đối thủ: {competitor_price_fmt}',
                'Giá trước đó: {previous_competitor_price_fmt}',
                'Chênh lệch: {diff_amount_fmt} ({diff_percent_fmt})',
                'Link: {competitor_url}',
                'Thời gian: {time}',
            ]);
            $this->send($setting, $this->render($title, $base), $this->render($body, $base), $product->name);
        }

        $cheaperPercent = $setting->alert_competitor_cheaper_percent;
        if ($cheaperPercent && $own > 0 && $newPrice < $own) {
            $betterPercent = (($own - $newPrice) / $own) * 100;
            if ($betterPercent >= $cheaperPercent) {
                $title = $setting->alert_cheaper_title ?: 'Cảnh báo giá';
                $body = $setting->alert_cheaper_body ?: implode("\n", [
                    'Rule: Đối thủ rẻ hơn bạn ≥ '.$cheaperPercent.'%',
                    'Sản phẩm: {product_name}',
                    'Giá của bạn: {own_price_fmt}',
                    'Đối thủ: {competitor_name}',
                    'Giá đối thủ: {competitor_price_fmt}',
                    'Chênh lệch: {diff_amount_fmt} ({diff_percent_fmt})',
                    'Link: {competitor_url}',
                    'Thời gian: {time}',
                ]);
                $payload = array_merge($base, [
                    'diff_percent' => is_null($diffPercent) ? '' : (string) round($diffPercent, 2),
                    'diff_percent_fmt' => is_null($diffPercent) ? '' : $this->formatPercent($diffPercent),
                    'cheaper_percent' => (string) round($betterPercent, 2),
                    'cheaper_percent_fmt' => $this->formatPercent($betterPercent),
                    'diff_amount' => (string) ($newPrice - $own),
                    'diff_amount_fmt' => $this->formatVnd($newPrice - $own),
                ]);
                $this->send($setting, $this->render($title, $payload), $this->render($body, $payload), $product->name);
            }
        }

        $ruleDropAmount = $setting->alert_competitor_drop_amount;
        if ($ruleDropAmount && ! is_null($previousPrice) && $previousPrice > $newPrice) {
            $drop = $previousPrice - $newPrice;
            if ($drop >= $ruleDropAmount) {
                $title = $setting->alert_drop_title ?: 'Cảnh báo giá';
                $body = $setting->alert_drop_body ?: implode("\n", [
                    'Rule: Đối thủ giảm ≥ {rule_drop_amount_fmt}',
                    'Sản phẩm: {product_name}',
                    'Đối thủ: {competitor_name}',
                    'Giá trước đó: {previous_competitor_price_fmt}',
                    'Giá đối thủ: {competitor_price_fmt}',
                    'Giảm: {drop_amount_fmt}',
                    'Link: {competitor_url}',
                    'Thời gian: {time}',
                ]);
                $payload = array_merge($base, [
                    'rule_drop_amount' => (string) $ruleDropAmount,
                    'rule_drop_amount_fmt' => $this->formatVnd((int) $ruleDropAmount),
                    'drop_amount' => (string) $drop,
                    'drop_amount_fmt' => $this->formatVnd((int) $drop),
                ]);
                $this->send($setting, $this->render($title, $payload), $this->render($body, $payload), $product->name);
            }
        }
    }

    public function notifyOnShopeeCompetitorPriceChange(ShopeeProduct $product, ShopeeCompetitor $competitor, int $newPrice, ?int $previousPrice): void
    {
        $setting = UserNotificationSetting::query()->where('user_id', $product->user_id)->first();
        if (! $setting) {
            return;
        }

        $own = is_null($product->last_price) ? null : (int) $product->last_price;
        if (is_null($own) || $own <= 0) {
            return;
        }

        $adj = (int) ($competitor->price_adjustment ?? 0);
        $competitorPrice = (int) $newPrice + $adj;
        $previousAdjPrice = is_null($previousPrice) ? null : ((int) $previousPrice + $adj);

        $siteName = (string) ($competitor->shop?->name ?? 'Đối thủ');
        $url = (string) ($competitor->url ?? '');
        $time = now()->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i');

        $diffAmount = $competitorPrice - $own;
        $diffPercent = $own > 0 ? (($competitorPrice - $own) / $own) * 100 : null;
        $dropAmount = ! is_null($previousAdjPrice) ? ($previousAdjPrice - $competitorPrice) : null;

        $base = [
            'product_name' => (string) ($product->name ?: ('Shopee product #'.$product->id)),
            'own_price' => (string) $own,
            'own_price_fmt' => $this->formatVnd($own),
            'competitor_name' => (string) $siteName,
            'competitor_price' => (string) $competitorPrice,
            'competitor_price_fmt' => $this->formatVnd($competitorPrice),
            'previous_competitor_price' => (string) ($previousAdjPrice ?? ''),
            'previous_competitor_price_fmt' => is_null($previousAdjPrice) ? '' : $this->formatVnd((int) $previousAdjPrice),
            'competitor_url' => (string) $url,
            'diff_amount' => (string) $diffAmount,
            'diff_amount_fmt' => $this->formatVnd($diffAmount),
            'diff_percent' => is_null($diffPercent) ? '' : (string) round($diffPercent, 2),
            'diff_percent_fmt' => is_null($diffPercent) ? '' : $this->formatPercent($diffPercent),
            'drop_amount' => is_null($dropAmount) ? '' : (string) $dropAmount,
            'drop_amount_fmt' => is_null($dropAmount) ? '' : $this->formatVnd((int) $dropAmount),
            'time' => (string) $time,
        ];

        if ($setting->notify_all_price_changes && ! is_null($previousAdjPrice)) {
            $title = $setting->notify_all_price_changes_title ?: 'Biến động giá';
            $body = $setting->notify_all_price_changes_body ?: implode("\n", [
                'Sản phẩm: {product_name}',
                'Giá của bạn: {own_price_fmt}',
                'Đối thủ: {competitor_name}',
                'Giá đối thủ: {competitor_price_fmt}',
                'Giá trước đó: {previous_competitor_price_fmt}',
                'Chênh lệch: {diff_amount_fmt} ({diff_percent_fmt})',
                'Link: {competitor_url}',
                'Thời gian: {time}',
            ]);
            $this->send($setting, $this->render($title, $base), $this->render($body, $base), (string) ($product->name ?: ('Shopee #'.$product->id)));
        }

        $cheaperPercent = $setting->alert_competitor_cheaper_percent;
        if ($cheaperPercent && $own > 0 && $competitorPrice < $own) {
            $betterPercent = (($own - $competitorPrice) / $own) * 100;
            if ($betterPercent >= $cheaperPercent) {
                $title = $setting->alert_cheaper_title ?: 'Cảnh báo giá';
                $body = $setting->alert_cheaper_body ?: implode("\n", [
                    'Rule: Đối thủ rẻ hơn bạn ≥ '.$cheaperPercent.'%',
                    'Sản phẩm: {product_name}',
                    'Giá của bạn: {own_price_fmt}',
                    'Đối thủ: {competitor_name}',
                    'Giá đối thủ: {competitor_price_fmt}',
                    'Chênh lệch: {diff_amount_fmt} ({diff_percent_fmt})',
                    'Link: {competitor_url}',
                    'Thời gian: {time}',
                ]);
                $payload = array_merge($base, [
                    'diff_percent' => is_null($diffPercent) ? '' : (string) round($diffPercent, 2),
                    'diff_percent_fmt' => is_null($diffPercent) ? '' : $this->formatPercent($diffPercent),
                    'cheaper_percent' => (string) round($betterPercent, 2),
                    'cheaper_percent_fmt' => $this->formatPercent($betterPercent),
                    'diff_amount' => (string) ($competitorPrice - $own),
                    'diff_amount_fmt' => $this->formatVnd($competitorPrice - $own),
                ]);
                $this->send($setting, $this->render($title, $payload), $this->render($body, $payload), (string) ($product->name ?: ('Shopee #'.$product->id)));
            }
        }

        $ruleDropAmount = $setting->alert_competitor_drop_amount;
        if ($ruleDropAmount && ! is_null($previousAdjPrice) && $previousAdjPrice > $competitorPrice) {
            $drop = $previousAdjPrice - $competitorPrice;
            if ($drop >= $ruleDropAmount) {
                $title = $setting->alert_drop_title ?: 'Cảnh báo giá';
                $body = $setting->alert_drop_body ?: implode("\n", [
                    'Rule: Đối thủ giảm ≥ {rule_drop_amount_fmt}',
                    'Sản phẩm: {product_name}',
                    'Đối thủ: {competitor_name}',
                    'Giá trước đó: {previous_competitor_price_fmt}',
                    'Giá đối thủ: {competitor_price_fmt}',
                    'Giảm: {drop_amount_fmt}',
                    'Link: {competitor_url}',
                    'Thời gian: {time}',
                ]);
                $payload = array_merge($base, [
                    'rule_drop_amount' => (string) $ruleDropAmount,
                    'rule_drop_amount_fmt' => $this->formatVnd((int) $ruleDropAmount),
                    'drop_amount' => (string) $drop,
                    'drop_amount_fmt' => $this->formatVnd((int) $drop),
                ]);
                $this->send($setting, $this->render($title, $payload), $this->render($body, $payload), (string) ($product->name ?: ('Shopee #'.$product->id)));
            }
        }
    }

    private function formatVnd(int $value): string
    {
        return number_format($value, 0, ',', '.').'đ';
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.').'%';
    }

    private function render(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $k => $v) {
            $out = str_replace('{'.$k.'}', (string) $v, $out);
        }

        return $out;
    }

    private function send(UserNotificationSetting $setting, string $title, string $body, string $productName): void
    {
        if ($setting->email_enabled && $setting->email_to) {
            try {
                Mail::raw($body, function ($m) use ($setting, $title, $productName) {
                    $m->to($setting->email_to)->subject($title.': '.$productName);
                });
            } catch (\Throwable $e) {
            }
        }

        if ($setting->telegram_enabled && $setting->telegram_bot_token && $setting->telegram_chat_id) {
            try {
                Http::asForm()->post('https://api.telegram.org/bot'.$setting->telegram_bot_token.'/sendMessage', [
                    'chat_id' => $setting->telegram_chat_id,
                    'text' => $title."\n\n".$body,
                ]);
            } catch (\Throwable $e) {
            }
        }
    }
}
