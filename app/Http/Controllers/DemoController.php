<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $setting = AppSetting::current();
        $demoUserId = $setting?->demo_user_id ? (int) $setting->demo_user_id : null;
        abort_unless($demoUserId, 404);

        $demoUser = User::query()
            ->where('id', $demoUserId)
            ->where('role', 'owner')
            ->whereNull('parent_user_id')
            ->first();

        abort_unless($demoUser, 404);

        Auth::login($demoUser);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
