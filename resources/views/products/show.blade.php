@extends('layouts.customer')

@section('title', $product->name_ar.' | بيت وأسلوب — Bayt & Style')
@section('description', $product->short_description_ar)

@section('content')
@php
    $saved = in_array($product->id, array_map('intval', session('wishlist', [])), true);
    $compared = in_array($product->id, array_map('intval', session('comparison', [])), true);
@endphp
<section class="product-detail shell" x-data="productDetail(@js($variantConfig), {{ $saved ? 'true' : 'false' }})">
    <nav class="breadcrumbs product-breadcrumbs" aria-label="مسار التنقل">
        <a href="{{ route('home') }}">الرئيسية</a><span>‹</span>
        <a href="{{ route('catalog') }}">المنتجات</a><span>‹</span>
        <a href="{{ route('catalog', ['category' => $product->category->slug]) }}">{{ $product->category->name_ar }}</a><span>‹</span>
        <span>{{ $product->name_ar }}</span>
    </nav>

    <div class="product-detail-grid">
        <div class="gallery" x-data="gallery({{ $product->media->count() }})" @keydown.arrow-left.prevent="next(); $nextTick(() => document.getElementById('product-media-tab-' + active)?.focus())" @keydown.arrow-right.prevent="previous(); $nextTick(() => document.getElementById('product-media-tab-' + active)?.focus())">
            <div class="gallery-main">
                @foreach($product->media as $index => $media)
                    <img id="product-media-{{ $index }}" role="tabpanel" aria-labelledby="product-media-tab-{{ $index }}" x-show="active === {{ $index }}" x-transition.opacity src="{{ asset($media->path) }}" alt="{{ $media->alt_ar }}" @if($index > 0) loading="lazy" @endif>
                @endforeach
            </div>
            <div class="gallery-thumbs" role="tablist" aria-label="صور المنتج">
                @foreach($product->media as $index => $media)
                    <button id="product-media-tab-{{ $index }}" type="button" role="tab" aria-controls="product-media-{{ $index }}" :tabindex="active === {{ $index }} ? 0 : -1" :aria-selected="(active === {{ $index }}).toString()" @click="active = {{ $index }}" :class="{ 'is-active': active === {{ $index }} }" aria-label="عرض الصورة {{ $index + 1 }}">
                        <img src="{{ asset($media->path) }}" alt="">
                    </button>
                @endforeach
            </div>
        </div>

        <div class="product-summary">
            <p class="product-kicker">{{ $product->room_ar }} · <span x-text="selectedVariant?.name || @js($product->defaultVariant->name_ar)">{{ $product->defaultVariant->name_ar }}</span></p>
            <h1 data-testid="product-title">{{ $product->name_ar }}</h1>
            <p class="product-lead">{{ $product->short_description_ar }}</p>
            <div class="detail-price" data-testid="variant-price"><bdi x-text="selectedVariant?.priceFormatted || @js(number_format((float) $product->defaultVariant->price, 0))">{{ number_format((float) $product->defaultVariant->price, 0) }}</bdi> <span>ر.س</span></div>

            @if(count($variantConfig['dimensions']))
                <div class="variant-config" aria-labelledby="variant-config-title">
                    <div class="variant-config-heading">
                        <div>
                            <p class="eyebrow">تهيئة القطعة</p>
                            <h2 id="variant-config-title">اختر وحدة البيع</h2>
                        </div>
                        <span class="variant-selection-count">{{ count($variantConfig['dimensions']) }} خيارات</span>
                    </div>

                    @foreach($variantConfig['dimensions'] as $dimension)
                        <fieldset class="variant-option-group">
                            <legend>{{ $dimension['label'] }}</legend>
                            <div class="variant-options">
                                @foreach($dimension['values'] as $value)
                                    <button
                                        type="button"
                                        @click="choose(@js($dimension['key']), @js($value))"
                                        :aria-pressed="isSelected(@js($dimension['key']), @js($value)).toString()"
                                        :disabled="isOptionDisabled(@js($dimension['key']), @js($value))"
                                        :class="{ 'is-selected': isSelected(@js($dimension['key']), @js($value)) }"
                                        :title="isOptionDisabled(@js($dimension['key']), @js($value)) ? 'غير متاح مع الاختيارات الحالية' : ''"
                                        data-option-key="{{ $dimension['key'] }}"
                                        data-option-value="{{ $value }}"
                                    >{{ $value }}</button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
            @endif

            <div class="variant-summary" :class="{ 'is-unavailable': !canAdd }" aria-live="polite">
                <div>
                    <p class="eyebrow">الخيار الحالي</p>
                    <h2 x-text="selectedVariant?.name || 'تركيبة غير متاحة'">{{ $product->defaultVariant->name_ar }}</h2>
                </div>
                <dl class="variant-facts">
                    <div><dt>SKU</dt><dd dir="ltr" data-testid="variant-sku" x-text="selectedVariant?.sku || '—'">{{ $product->defaultVariant->sku }}</dd></div>
                    <div><dt>التوفر</dt><dd data-testid="variant-availability" x-text="availabilityLabel">متاح</dd></div>
                </dl>
                <p x-show="!canAdd" data-testid="variant-unavailable">هذه التركيبة غير متاحة للشراء. اختر تركيبة أخرى من الخيارات المتاحة.</p>
            </div>

            <div class="purchase-row">
                <div class="quantity-control" aria-label="الكمية">
                    <button type="button" @click="decrease" :disabled="quantity <= 1" aria-label="تقليل الكمية">−</button>
                    <output x-text="quantity" data-testid="quantity-value">1</output>
                    <button type="button" @click="increase" :disabled="quantity >= 10" aria-label="زيادة الكمية">+</button>
                </div>
                <button class="button button-primary add-cart" type="button" @click="addToCart" :disabled="adding || !canAdd" data-testid="add-to-cart">
                    <span x-show="!adding">أضف إلى السلة</span><span x-show="adding">جارٍ الإضافة…</span>
                </button>
            </div>

            <div class="detail-secondary-actions" x-data="comparisonToggle({{ $product->id }}, {{ $compared ? 'true' : 'false' }})">
                <button type="button" class="detail-wishlist" @click="toggleWishlist" :aria-pressed="saved.toString()" :aria-busy="wishlistBusy.toString()" :disabled="wishlistBusy" data-testid="detail-wishlist">
                    <span x-text="saved ? '♥' : '♡'">{{ $saved ? '♥' : '♡' }}</span>
                    <span x-show="!wishlistBusy" x-text="saved ? 'محفوظ في المفضلة' : 'حفظ في المفضلة'">{{ $saved ? 'محفوظ في المفضلة' : 'حفظ في المفضلة' }}</span>
                    <span role="status" aria-live="polite" aria-atomic="true" x-show="wishlistBusy" x-cloak>جارٍ التحديث…</span>
                </button>
                <button
                    type="button"
                    class="detail-compare"
                    @click="toggle"
                    :aria-pressed="compared.toString()"
                    :aria-busy="busy.toString()"
                    :disabled="busy"
                    data-testid="detail-comparison"
                >
                    <span x-show="!busy" x-text="compared ? 'إزالة من المقارنة' : 'أضف للمقارنة'">{{ $compared ? 'إزالة من المقارنة' : 'أضف للمقارنة' }}</span>
                    <span role="status" aria-live="polite" x-show="busy" x-cloak>جارٍ تحديث المقارنة…</span>
                </button>
            </div>
            <p class="inventory-boundary">إضافة القطعة إلى السلة لا تعني حجز المخزون.</p>

            <div class="service-notes">
                <article><span aria-hidden="true">▱</span><div><h2>التوصيل</h2><p>تُعرض خيارات وموعد التوصيل وفق الوجهة وشروط الطلب في المراحل اللاحقة من الشراء.</p></div></article>
                <article><span aria-hidden="true">↺</span><div><h2>الإرجاع</h2><p>تُراجع تفاصيل الإرجاع المطبقة على الطلب قبل إتمام الشراء، دون افتراض مدة ثابتة داخل هذا النطاق.</p></div></article>
                <article><span aria-hidden="true">◌</span><div><h2>المساعدة</h2><p>يمكن لخدمة العملاء توضيح معلومات العناية أو البيانات المتاحة عن القطعة قبل الشراء.</p></div></article>
            </div>
        </div>
    </div>
</section>

<section class="section shell detail-information">
    <div class="detail-side-copy">
        <p class="eyebrow">معلومات القطعة</p>
        <h2>تفاصيل بهدوء</h2>
        <p>معلومات عملية ومختصرة تساعدك على فهم القطعة دون تحويل الصفحة إلى بطاقة مواصفات مزدحمة.</p>
    </div>
    <div class="detail-accordions" x-data="accordions">
        <article class="detail-accordion">
            <button type="button" @click="toggle('description')" :aria-expanded="isOpen('description').toString()"><span>الوصف</span><span x-text="isOpen('description') ? '−' : '+'"></span></button>
            <div x-show="isOpen('description')"><p>{{ $product->description_ar }}</p></div>
        </article>
        <article class="detail-accordion">
            <button type="button" @click="toggle('materials')" :aria-expanded="isOpen('materials').toString()"><span>الخامات والتفاصيل</span><span x-text="isOpen('materials') ? '−' : '+'"></span></button>
            <div x-show="isOpen('materials')">
                <dl class="detail-list">
                    <div><dt>الفئة</dt><dd>{{ $product->category->name_ar }}</dd></div>
                    <div><dt>الخامة</dt><dd>{{ $product->material_ar }}</dd></div>
                    <div><dt>حالة البيع</dt><dd>{{ $product->variants->count() > 1 ? 'خيارات Variant فعلية متعددة' : 'وحدة بيع واحدة' }}</dd></div>
                </dl>
                <p>{{ $product->details_ar }}</p>
            </div>
        </article>
        <article class="detail-accordion">
            <button type="button" @click="toggle('care')" :aria-expanded="isOpen('care').toString()"><span>العناية</span><span x-text="isOpen('care') ? '−' : '+'"></span></button>
            <div x-show="isOpen('care')"><p>للعناية العامة، يُفضّل إزالة الغبار بلطف واختبار أي منظف مخصص للأقمشة على جزء غير ظاهر أولًا. تُراجع إرشادات المنتج الفعلية عند توفرها.</p></div>
        </article>
        <article class="detail-accordion">
            <button type="button" @click="toggle('dimensions')" :aria-expanded="isOpen('dimensions').toString()"><span>الأبعاد</span><span x-text="isOpen('dimensions') ? '−' : '+'"></span></button>
            <div x-show="isOpen('dimensions')"><p>الأبعاد التفصيلية غير متاحة لهذه القطعة حاليًا، ولن تُعرض حتى تتوفر بيانات دقيقة ومعتمدة.</p></div>
        </article>
    </div>
</section>

<section class="section shell editorial-detail">
    <div class="editorial-detail-image"><img src="{{ asset('images/products/chair-detail-seat.jpg') }}" alt="تفصيل خامة مخملية زيتونية"></div>
    <div class="editorial-detail-copy">
        <p class="eyebrow">بيت وأسلوب · تنسيق هادئ</p>
        <h2>اجعل الخامة هي نقطة الهدوء</h2>
        <p>يمكن للون الزيتوني والملمس المخملي أن يؤديا دورًا بصريًا واضحًا مع تفاصيل محايدة وخشب داكن، من دون الحاجة إلى إضافات كثيرة حول القطعة.</p>
    </div>
</section>
@endsection
