<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function edit(): View
    {
        $setting = AppSetting::current() ?? new AppSetting;

        $demoUsers = User::query()
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('role', 'owner')->whereNull('parent_user_id');
                })->orWhere(function ($qq) {
                    $qq->where('role', 'viewer')->whereNotNull('parent_user_id');
                });
            })
            ->orderBy('parent_user_id')
            ->orderBy('role')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'parent_user_id', 'role']);

        $scrapeStatus = [
            'last_started_at' => Cache::get('checkgia:scrape-due:last_started_at'),
            'last_finished_at' => Cache::get('checkgia:scrape-due:last_finished_at'),
            'last_selected' => Cache::get('checkgia:scrape-due:last_selected'),
            'last_dispatched' => Cache::get('checkgia:scrape-due:last_dispatched'),
            'last_updated' => Cache::get('checkgia:scrape-due:last_updated'),
            'last_job_finished_at' => Cache::get('checkgia:scrape-due:last_job_finished_at'),
            'last_job_error' => Cache::get('checkgia:scrape-due:last_job_error'),
        ];

        return view('admin.settings.edit', compact('setting', 'demoUsers', 'scrapeStatus'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['nullable', 'in:smtp,log,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2048'],
            'mail_encryption' => ['nullable', 'in:,tls,ssl'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'demo_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('role', 'owner')->whereNull('parent_user_id');
                })->orWhere(function ($qq) {
                    $qq->where('role', 'viewer')->whereNotNull('parent_user_id');
                });
            })],
            'website_scrape_batch_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'website_scrape_concurrency' => ['nullable', 'integer', 'min:1', 'max:50'],
            'website_scrape_timeout_seconds' => ['nullable', 'integer', 'min:3', 'max:60'],
        ]);

        $setting = AppSetting::current() ?? new AppSetting;
        $passwordInput = array_key_exists('mail_password', $data) ? trim((string) $data['mail_password']) : null;
        if ($passwordInput === '') {
            unset($data['mail_password']);
        } else {
            $data['mail_password'] = $passwordInput;
        }

        if (array_key_exists('mail_encryption', $data) && $data['mail_encryption'] === '') {
            $data['mail_encryption'] = null;
        }

        if (array_key_exists('demo_user_id', $data) && (string) $data['demo_user_id'] === '') {
            $data['demo_user_id'] = null;
        }

        $table = (new AppSetting)->getTable();
        foreach (['website_scrape_batch_per_minute', 'website_scrape_concurrency', 'website_scrape_timeout_seconds'] as $col) {
            if (array_key_exists($col, $data) && ! Schema::hasColumn($table, $col)) {
                unset($data[$col]);
            }
        }

        if (array_key_exists('website_scrape_batch_per_minute', $data) && (string) $data['website_scrape_batch_per_minute'] === '') {
            $data['website_scrape_batch_per_minute'] = null;
        }

        if (array_key_exists('website_scrape_concurrency', $data) && (string) $data['website_scrape_concurrency'] === '') {
            $data['website_scrape_concurrency'] = null;
        }

        if (array_key_exists('website_scrape_timeout_seconds', $data) && (string) $data['website_scrape_timeout_seconds'] === '') {
            $data['website_scrape_timeout_seconds'] = null;
        }

        $setting->fill($data);
        $setting->save();

        return back()->with('status', 'Đã lưu cài đặt');
    }
}
