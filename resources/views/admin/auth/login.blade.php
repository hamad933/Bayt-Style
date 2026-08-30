<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>دخول الإدارة | بيت وأسلوب</title>
    @vite(['resources/css/app.css', 'resources/css/s09.css', 'resources/js/app.js'])
</head>
<body class="admin-login-body">
<main class="admin-login-shell">
    <section class="admin-login-card" aria-labelledby="admin-login-title">
        <a class="admin-login-brand" href="{{ route('home') }}">بيت وأسلوب</a>
        <p class="admin-eyebrow">دخول إداري مقيّد</p>
        <h1 id="admin-login-title">إدارة الكتالوج والمخزون</h1>
        <p class="admin-login-copy">هذا المسار مخصص لهوية إدارة الكتالوج المصرّح لها. لا توجد آلية تسجيل عامة أو هوية إنتاجية مفعّلة هنا.</p>

        @if ($errors->any())
            <div class="admin-alert admin-alert--error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form class="admin-form"
              method="post"
              action="{{ route('admin.login.store') }}"
              x-data="{ submitting: false }"
              @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
              :aria-busy="submitting.toString()">
            @csrf
            <label class="admin-field">
                <span>البريد الإلكتروني</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </label>
            <label class="admin-field">
                <span>كلمة المرور</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="admin-primary-button"
                    type="submit"
                    :disabled="submitting"
                    :aria-busy="submitting.toString()">
                <span x-text="submitting ? 'جارٍ الدخول…' : 'دخول آمن'">دخول آمن</span>
            </button>
            <p class="admin-help"
               x-show="submitting"
               x-cloak
               role="status"
               aria-live="polite"
               aria-atomic="true">جارٍ التحقق من بيانات الدخول…</p>
        </form>
    </section>
</main>
</body>
</html>
