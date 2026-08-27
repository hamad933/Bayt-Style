@extends('layouts.customer')

@section('title', 'المنتجات | بيت وأسلوب — Bayt & Style')
@section('description', 'استكشف منتجات بيت وأسلوب وابحث بالعربية حسب الفئة والغرفة والخامة والميزانية.')

@section('content')
<section class="catalog-intro shell">
    <div class="catalog-intro-image"><img src="{{ asset('images/editorial/living.jpg') }}" alt="غرفة معيشة مع أريكة زيتونية"></div>
    <div class="catalog-intro-copy">
        <nav class="breadcrumbs" aria-label="مسار التنقل"><a href="{{ route('home') }}">الرئيسية</a><span>‹</span><span>المنتجات</span></nav>
        <p class="eyebrow">تشكيلة بيت وأسلوب</p>
        <h1>قطع مختارة لمساحتك</h1>
        <p>استكشف تشكيلة هادئة من الأثاث والإضاءة والتفاصيل المنزلية، وابحث بما يناسب الغرفة والخامة والميزانية.</p>
    </div>
</section>

<section class="catalog-tools shell" >
    <form action="{{ route('catalog') }}" method="get" class="catalog-search" role="search">
        @foreach(request()->except(['q', 'page']) as $key => $value)
            @if(is_string($value) && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <label class="sr-only" for="catalog-search">البحث في المنتجات</label>
        <input id="catalog-search" name="q" type="search" value="{{ request('q') }}" placeholder="ابحث عن كرسي، مصباح، طاولة…" data-testid="catalog-search">
        <button type="submit">بحث</button>
    </form>
    <form action="{{ route('catalog') }}" method="get" class="sort-form">
        @foreach(request()->except(['sort', 'page']) as $key => $value)
            @if(is_string($value) && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <label for="sort">ترتيب النتائج</label>
        <select id="sort" name="sort" onchange="this.form.submit()" data-testid="sort-select">
            <option value="recommended" @selected(request('sort', 'recommended') === 'recommended')>الموصى به</option>
            <option value="newest" @selected(request('sort') === 'newest')>الأحدث</option>
            <option value="price-asc" @selected(request('sort') === 'price-asc')>السعر: من الأقل إلى الأعلى</option>
            <option value="price-desc" @selected(request('sort') === 'price-desc')>السعر: من الأعلى إلى الأقل</option>
        </select>
    </form>
    <button type="button" class="mobile-filter-button" @click="$store.filters.openDrawer()" :aria-expanded="$store.filters.open.toString()" aria-controls="mobile-filter-panel">تصفية المنتجات</button>
</section>

@php
    $active = collect([
        'q' => request('q') ? ['بحث: '.request('q'), request()->fullUrlWithQuery(['q' => null, 'page' => null])] : null,
        'category' => request('category') ? ['الفئة: '.optional($categories->firstWhere('slug', request('category')))->name_ar, request()->fullUrlWithQuery(['category' => null, 'page' => null])] : null,
        'room' => request('room') ? ['الغرفة: '.request('room'), request()->fullUrlWithQuery(['room' => null, 'page' => null])] : null,
        'material' => request('material') ? ['الخامة: '.request('material'), request()->fullUrlWithQuery(['material' => null, 'page' => null])] : null,
        'price' => request('price') ? ['السعر: '.match(request('price')) {'under-500' => 'أقل من 500 ر.س', '500-1000' => '500–1,000 ر.س', default => 'أكثر من 1,000 ر.س'}, request()->fullUrlWithQuery(['price' => null, 'page' => null])] : null,
    ])->filter();
@endphp

@if($active->isNotEmpty())
<div class="active-filters shell" aria-label="الفلاتر النشطة">
    <span>الفلاتر النشطة:</span>
    @foreach($active as [$label, $url])
        <a href="{{ $url }}" class="filter-chip">{{ $label }} <span aria-hidden="true">×</span></a>
    @endforeach
    <a href="{{ route('catalog') }}" class="clear-all">مسح الكل</a>
</div>
@endif

<section class="catalog-layout shell">
    <aside class="filters-panel desktop-filters" aria-label="تصفية المنتجات">
        <div class="filter-title"><div><p class="eyebrow">المنتجات</p><h2>تصفية</h2></div><span>{{ $products->total() }} نتائج</span></div>
        @include('partials.filters')
    </aside>

    <div class="catalog-results">
        <div class="results-head"><h2>المنتجات</h2><span>{{ $products->total() }} نتائج</span></div>
        @if($products->count())
            <div class="product-grid catalog-grid" data-testid="catalog-results">
                @foreach($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            @if($products->hasMorePages())
                <div class="load-more-wrap"><a class="button button-ghost" href="{{ $products->nextPageUrl() }}">عرض المزيد</a></div>
            @elseif($products->currentPage() > 1)
                <div class="load-more-wrap"><a class="text-link" href="{{ $products->previousPageUrl() }}">العودة إلى النتائج السابقة</a></div>
            @endif
        @else
            <div class="no-results" data-testid="no-results">
                <p class="eyebrow">لا توجد نتائج</p>
                <h2>لم نجد قطعًا مطابقة</h2>
                <p>جرّب تقليل عدد الفلاتر أو استخدام عبارة بحث أقصر. يمكنك أيضًا العودة إلى التشكيلة الكاملة.</p>
                <a class="button button-primary" href="{{ route('catalog') }}">عرض كل المنتجات</a>
            </div>
        @endif
    </div>
</section>

<div class="drawer-backdrop" x-show="$store.filters.open" x-transition.opacity x-cloak @click.self="$store.filters.closeDrawer()">
    <aside id="mobile-filter-panel" class="filter-drawer" role="dialog" aria-modal="true" aria-labelledby="filter-drawer-title" x-ref="mobileFilterPanel" x-effect="$store.filters.open && $nextTick(() => $refs.mobileFilterPanel?.querySelector('button')?.focus())" @keydown.tab="$store.filters.trapFocus($event, $el)">
        <div class="drawer-head"><h2 id="filter-drawer-title">تصفية المنتجات</h2><button type="button" @click="$store.filters.closeDrawer()" aria-label="إغلاق">×</button></div>
        @include('partials.filters', ['drawerContext' => true])
    </aside>
</div>
@endsection