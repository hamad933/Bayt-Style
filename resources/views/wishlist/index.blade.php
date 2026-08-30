@extends('layouts.customer')

@section('title', 'المفضلة | بيت وأسلوب — Bayt & Style')
@section('description', 'القطع التي حفظتها أثناء استكشاف بيت وأسلوب.')

@section('content')
<section class="saved-surface shell">
    <nav class="breadcrumbs" aria-label="مسار التنقل">
        <a href="{{ route('home') }}">الرئيسية</a><span>‹</span>
        <span>المفضلة</span>
    </nav>

    <header class="saved-surface-head">
        <div>
            <p class="eyebrow">مختاراتك</p>
            <h1>المفضلة</h1>
            <p>مكان واحد للقطع التي تريد العودة إليها أثناء الاستكشاف. تُحفظ هذه الحالة في جلسة الزائر الحالية فقط.</p>
        </div>
        <a class="text-link" href="{{ route('catalog') }}">متابعة الاستكشاف</a>
    </header>

    @if(session('status'))
        <p class="surface-status" role="status">{{ session('status') }}</p>
    @endif

    @if($products->isEmpty())
        <div class="saved-empty" data-testid="wishlist-empty">
            <p class="eyebrow">لا توجد قطع محفوظة</p>
            <h2>ابدأ من التشكيلة التي تناسب مساحتك.</h2>
            <p>استخدم رمز القلب في صفحات المنتجات أو بطاقات الكتالوج، وستظهر القطع هنا في الجلسة نفسها.</p>
            <a class="button button-primary" href="{{ route('catalog') }}">استكشف المنتجات</a>
        </div>
    @else
        <div class="wishlist-list" data-testid="wishlist-list">
            @foreach($products as $product)
                @php
                    $variant = $product->defaultVariant;
                    $media = $product->primaryMedia;
                    $compared = in_array($product->id, array_map('intval', session('comparison', [])), true);
                @endphp
                <article class="wishlist-row">
                    <a class="wishlist-row-image" href="{{ route('products.show', $product) }}" aria-label="عرض {{ $product->name_ar }}">
                        @if($media)<img src="{{ asset($media->path) }}" alt="{{ $media->alt_ar }}">@endif
                    </a>
                    <div class="wishlist-row-copy">
                        <p class="product-meta">{{ $product->category->name_ar }} · {{ $product->material_ar }} · {{ $product->room_ar }}</p>
                        <h2><a href="{{ route('products.show', $product) }}">{{ $product->name_ar }}</a></h2>
                        <p>{{ $product->short_description_ar }}</p>
                        @if($variant)
                            <strong class="wishlist-price"><bdi>{{ number_format((float) $variant->price, 0) }}</bdi> ر.س</strong>
                        @endif
                    </div>
                    <div class="wishlist-row-actions">
                        <a class="button button-ghost" href="{{ route('products.show', $product) }}">عرض التفاصيل</a>
                        <button
                            type="button"
                            class="compare-text-action"
                            x-data="comparisonToggle({{ $product->id }}, {{ $compared ? 'true' : 'false' }})"
                            @click="toggle"
                            :aria-pressed="compared.toString()"
                            :disabled="busy"
                            data-testid="wishlist-comparison-toggle"
                        ><span x-text="compared ? 'إزالة من المقارنة' : 'أضف للمقارنة'">{{ $compared ? 'إزالة من المقارنة' : 'أضف للمقارنة' }}</span></button>
                        <form
                            action="{{ route('wishlist.destroy', $product) }}"
                            method="post"
                            x-data="{ submitting: false }"
                            @submit="if (submitting) { $event.preventDefault() } else { submitting = true }"
                            :aria-busy="submitting.toString()"
                            data-testid="wishlist-remove-form"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="remove-line"
                                :disabled="submitting"
                                :aria-busy="submitting.toString()"
                                data-testid="wishlist-remove-submit"
                            ><span x-text="submitting ? 'جارٍ الإزالة…' : 'إزالة من المفضلة'">إزالة من المفضلة</span></button>
                            <span
                                class="surface-status"
                                x-show="submitting"
                                x-cloak
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                                data-testid="wishlist-remove-status"
                            >جارٍ إزالة القطعة من المفضلة…</span>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
