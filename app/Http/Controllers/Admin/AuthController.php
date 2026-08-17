<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        $user = Auth::user();
        if ($user instanceof User && $user->isCatalogAdmin()) {
            return redirect()->route('admin.catalog.index');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $key = 'admin-login:'.$request->ip().':'.$email;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'محاولات كثيرة. أعد المحاولة بعد قليل.',
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $validated['password']], false)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $user->isCatalogAdmin()) {
            Auth::logout();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.catalog.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
