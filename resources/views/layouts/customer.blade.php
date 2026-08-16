<!doctype html>
<html lang="ar" dir="rtl" data-cart-count="{{ collect(session('cart', []))->sum() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بيت وأسلوب | Bayt & Style')</title>
    <meta name="description" content="@yield('description', 'بيت وأسلوب — تجربة تجارة منزلية عربية معاصرة.')">
    @vite(['resources/css/app.css', 'resources/css/refinements.css', 'resources/js/app.js'])
</head>
<body x-data="appShell" @keydown.escape.window="closeTransientUi">
<a class="skip-link" href="#main-content">انتقل إلى المحتوى الرئيسي</a>
<x-header />
<main id="main-content" tabindex="-1">
    @yield('content')
</main>
<x-footer />
<div class="toast-region" aria-live="polite" aria-atomic="true">
    <p x-show="$store.notice.message" x-transition x-text="$store.notice.message" class="toast"></p>
</div>
</body>
</html>
