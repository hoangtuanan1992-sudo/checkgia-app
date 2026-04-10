<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = trim((string) $data['email']);

        $user = User::query()
            ->where('email', $email)
            ->orWhere('email_canonical', User::canonicalEmail($email))
            ->first();

        if ($user) {
            $temp = Str::random(8).'aA1';
            $user->update(['password' => $temp]);

            $body = implode("\n", [
                'Mật khẩu tạm thời của bạn: '.$temp,
                'Vui lòng đăng nhập và đổi mật khẩu ngay tại mục Tài khoản.',
                'Thời gian: '.now()->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i'),
            ]);

            try {
                Mail::raw($body, function ($m) use ($user) {
                    $m->to($user->email)->subject('Khôi phục mật khẩu');
                });
            } catch (\Throwable $e) {
            }
        }

        return back()->with('status', 'Nếu email tồn tại, mật khẩu mới đã được gửi.');
    }
}
