<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'إدارة بيت وأسلوب')</title>
    @vite(['resources/css/app.css', 'resources/css/s09.css', 'resources/css/s10.css', 'resources/js/app.js', 'resources/js/s10-order-detail.js'])
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">انتقل إلى المحتوى الرئيسي</a>
<div class="admin-shell">
    <aside class="admin-sidebar" aria-label="التنقل الإداري">
        <a class="admin-brand" href="{{ route('admin.catalog.index') }}">
            <span class="admin-brand__mark" aria-hidden="true">ب</span>
            <span>
                <strong>بيت وأسلوب</strong>
                <small>إدارة المتجر</small>
            </span>
        </a>
        <nav class="admin-nav">
            <a class="admin-nav__link {{ request()->routeIs('admin.catalog.*', 'admin.variants.*', 'admin.inventory.*') ? 'is-active' : '' }}"
               href="{{ route('admin.catalog.index') }}">الكتالوج والمخزون</a>
            <a class="admin-nav__link {{ request()->routeIs('admin.orders.*', 'admin.returns.*') ? 'is-active' : '' }}"
               href="{{ route('admin.orders.index') }}">الطلبات والمدفوعات والمرتجعات</a>
        </nav>
        <div class="admin-sidebar__foot">
            <p class="admin-identity"><span>المستخدم</span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></p>
            <form method="post"
                  action="{{ route('admin.logout') }}"
                  x-data="{ submitting: false }"
                  @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
                  :aria-busy="submitting ? 'true' : 'false'">
                @csrf
                <button class="admin-link-button"
                        type="submit"
                        :disabled="submitting"
                        :aria-busy="submitting ? 'true' : 'false'"
                        x-text="submitting ? 'جارٍ تسجيل الخروج…' : 'تسجيل الخروج'">تسجيل الخروج</button>
                <small :role="submitting ? 'status' : null"
                       :aria-live="submitting ? 'polite' : null"
                       :aria-atomic="submitting ? 'true' : null"
                       x-text="submitting ? 'جارٍ إنهاء الجلسة بأمان…' : ''"></small>
            </form>
        </div>
    </aside>

    <div class="admin-workspace">
        <header class="admin-topbar">
            <div>
                <p class="admin-eyebrow">سطح تشغيلي محمي</p>
                <p class="admin-topbar__title">@yield('page-title', 'إدارة المتجر')</p>
            </div>
            <a class="admin-store-link" href="{{ route('home') }}">فتح المتجر</a>
        </header>

        <main id="admin-main" class="admin-main" tabindex="-1">
            @if (session('status'))
                <div class="admin-alert admin-alert--success" role="status">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="admin-alert admin-alert--error" role="alert">
                    <strong>تعذر تنفيذ العملية.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
