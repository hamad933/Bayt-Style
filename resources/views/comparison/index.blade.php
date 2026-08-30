@extends('layouts.customer')

@section('title', 'المقارنة | بيت وأسلوب — Bayt & Style')
@section('description', 'قارن بين الحقائق المتاحة للمنتجات المختارة في بيت وأسلوب.')

@section('content')
<section class="saved-surface shell">
    <nav class="breadcrumbs" aria-label="مسار التنقل">
        <a href="{{ route('home') }}">الرئيسية</a><span>‹</span>
        <span>المقارنة</span>
    </nav>

    <header class="saved-surface-head comparison-head">
        <div>
            <p class="eyebrow">مقارنة هادئة</p>
            <h1>قارن ما هو متاح فعلًا</h1>
            <p>نعرض فقط حقائق موجودة في بيانات المنتج الحالية، بحد أقصى {{ $comparisonLimit }} منتجات حتى تبقى المقارنة واضحة على الشاشات الصغيرة.</p>
        </div>
        @if($products->isNotEmpty())
            <form action="{{ route('comparison.clear') }}" method="post" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true" :aria-busy="submitting ? 'true' : 'false'">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-link comparison-clear" :disabled="submitting" :aria-busy="submitting ? 'true' : 'false'" x-text="submitting ? 'جارٍ مسح المقارنة…' : 'مسح المقارنة'">مسح المقارنة</button>
                <span x-cloak x-show="submitting" role="status" aria-live="polite" aria-atomic="true">جارٍ مسح المقارنة…</span>
            </form>
        @endif
    </header>

    @if(session('status'))
        <p class="surface-status" role="status">{{ session('status') }}</p>
    @endif
    @error('comparison')
        <p class="surface-error" role="alert">{{ $message }}</p>
    @enderror

    @if($products->isEmpty())
        <div class="saved-empty" data-testid="comparison-empty">
            <p class="eyebrow">المقارنة فارغة</p>
            <h2>اختر حتى {{ $comparisonLimit }} منتجات من الكتالوج أو صفحات التفاصيل.</h2>
            <p>يمكنك إضافة المنتجات وإزالتها من المقارنة من دون تسجيل دخول ضمن جلسة الزائر الحالية.</p>
            <a class="button button-primary" href="{{ route('catalog') }}">اذهب إلى الكتالوج</a>
        </div>
    @else
        <div class="comparison-grid" data-testid="comparison-grid" aria-label="المنتجات المقارنة">
            @foreach($products as $product)
                @php($variant = $product->defaultVariant)
                <article class="comparison-item">
                    <div class="comparison-item-media">
                        @if($product->primaryMedia)
                            <img src="{{ asset($product->primaryMedia->path) }}" alt="{{ $product->primaryMedia->alt_ar }}">
                        @endif
                    </div>
                    <div class="comparison-item-head">
                        <p class="product-meta">{{ $product->category->name_ar }}</p>
                        <h2><a href="{{ route('products.show', $product) }}">{{ $product->name_ar }}</a></h2>
                    </div>
                    <dl class="comparison-facts">
                        <div><dt>الفئة</dt><dd>{{ $product->category->name_ar }}</dd></div>
                        <div><dt>السعر الحالي</dt><dd>@if($variant)<bdi>{{ number_format((float) $variant->price, 0) }}</bdi> ر.س@else غير متاح @endif</dd></div>
                        <div><dt>الخامة</dt><dd>{{ $product->material_ar }}</dd></div>
                        <div><dt>الغرفة</dt><dd>{{ $product->room_ar }}</dd></div>
                    </dl>
                    <div class="comparison-item-actions">
                        <a class="text-link" href="{{ route('products.show', $product) }}">عرض المنتج</a>
                        <form action="{{ route('comparison.destroy', $product) }}" method="post" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); return; } submitting = true" :aria-busy="submitting ? 'true' : 'false'">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="remove-line" data-testid="comparison-remove" :disabled="submitting" :aria-busy="submitting ? 'true' : 'false'" x-text="submitting ? 'جارٍ الإزالة…' : 'إزالة من المقارنة'">إزالة من المقارنة</button>
                            <span x-cloak x-show="submitting" role="status" aria-live="polite" aria-atomic="true">جارٍ إزالة المنتج من المقارنة…</span>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection