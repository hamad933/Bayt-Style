@extends('layouts.customer')

@section('title', $product->name_ar.' | بيت وأسلوب — Bayt & Style')
@section('description', $product->short_description_ar)

@section('content')
<section class="product-detail shell" x-data="productDetail({{ $product->id }}, {{ $product->defaultVariant->id }}, {{ in_array($product->id, array_map('intval', session('wishlist', [])), true) ? 'true' : 'false' }})">
    <nav class="breadcrumbs product-breadcrumbs" aria-label="مسار التنقل">
        <a href="{{ route('home') }}">الرئيسية</a><span>‹</span>
        <a href="{{ route('catalog') }}">المنتجات</a><span>‹</span>
        <a href="{{ route('catalog', ['category' => $product->category->slug]) }}">{{ $product->category->name_ar }}</a><span>‹</span>
        <span>{{ $product->name_ar }}</span>
    </nav>

    <div class="product-detail-grid">
        <div class="gallery" x-data="gallery({{ $product->media->count() }})" @keydown.arrow-left.prevent="next()" @keydown.arrow-right.prevent="previous()">
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
            <p class="product-kicker">{{ $product->room_ar }} · {{ $product->defaultVariant->name_ar }}</p>
            <h1 data-testid="product-title">{{ $product->name_ar }}</h1>
            <p class="product-lead">{{ $product->short_description_ar }}</p>
            <div class="detail-price"><bdi>{{ number_format((float) $product->defaultVariant->price, 0) }}</bdi> <span>ر.س</span></div>

            <div class="variant-summary">
                <div><p class="eyebrow">الخيار الحالي</p><h2>وحدة البيع الحالية</h2></div>
                <div class="variant-name"><span class="variant-dot" aria-hidden="true"></span>{{ $product->defaultVariant->name_ar }}</div>
                <p>الخيار المعروض هو وحدة البيع الحالية لهذا المنتج. تظهر الخيارات الإضافية فقط عندما تكون متاحة للقطعة.</p>
            </div>

            <div class="purchase-row">
                <div class="quantity-control" aria-label="الكمية">
                    <button type="button" @click="decrease" :disabled="quantity <= 1" aria-label="تقليل الكمية">−</button>
                    <output x-text="quantity" data-testid="quantity-value">1</output>
                    <button type="button" @click="increase" :disabled="quantity >= 10" aria-label="زيادة الكمية">+</button>
                </div>
                <button class="button button-primary add-cart" type="button" @click="addToCart" :disabled="adding" data-testid="add-to-cart">
                    <span x-show="!adding">أضف إلى السلة</span><span x-show="adding">جارٍ الإضافة…</span>
                </button>
            </div>
            <button type="button" class="detail-wishlist" @click="toggleWishlist" :aria-pressed="saved.toString()" data-testid="detail-wishlist">
                <span x-text="saved ? '♥' : '♡'">♡</span>
                <span x-text="saved ? 'محفوظ في المفضلة' : 'حفظ في المفضلة'">حفظ في المفضلة</span>
            </button>
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
                    <div><dt>الخامة المرئية</dt><dd>{{ $product->material_ar }}</dd></div>
                    <div><dt>الحالة المعروضة</dt><dd>خيار بيع واحد</dd></div>
                </dl>
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
