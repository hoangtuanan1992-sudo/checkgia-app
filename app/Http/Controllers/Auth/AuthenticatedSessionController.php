<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('Bạn đăng nhập quá nhiều lần. Vui lòng thử lại sau :seconds giây.', [
                    'seconds' => $seconds,
                ]),
            ]);
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user && ! $user->isAdmin()) {
            $ownerId = $user->serviceOwnerId();
            $owner = $ownerId === (int) $user->id ? $user : User::query()->find($ownerId);

            if ($owner && $owner->service_start_date && $owner->service_end_date) {
                $tz = 'Asia/Ho_Chi_Minh';
                $today = now($tz)->toDateString();
                $todayDate = Carbon::createFromFormat('Y-m-d', $today, $tz);
                $start = Carbon::createFromFormat('Y-m-d', $owner->service_start_date->format('Y-m-d'), $tz);
                $end = Carbon::createFromFormat('Y-m-d', $owner->service_end_date->format('Y-m-d'), $tz);

                if ($todayDate->lt($start)) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    throw ValidationException::withMessages([
                        'email' => 'Tài khoản chưa đến ngày sử dụng.',
                    ]);
                }

                if ($todayDate->gt($end)) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    throw ValidationException::withMessages([
                        'email' => 'Tài khoản đã hết hạn.',
                    ]);
                }
            }
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function throttleKey(Request $request): string
    {
        return $request->ip().'|'.mb_strtolower((string) $request->input('email'));
    }
}
