@extends('layouts.customer')
@section('title', 'سلة التسوق | بيت وأسلوب')
@section('content')
<section class="s06-shell commerce-page" data-testid="cart-page">
    <nav class="s06-crumbs" aria-label="مسار الصفحة"><a href="{{ route('home') }}">الرئيسية</a><span aria-hidden="true">/</span><span>السلة</span></nav>
    <header class="s06-page-head">
        <p class="eyebrow">سلة التسوق</p>
        <h1>راجع مختاراتك بهدوء</h1>
        <p>السلة تعبّر عن نية الشراء فقط. لا يتم حجز المخزون عند الإضافة أو تعديل الكمية.</p>
    </header>

    @if(session('status'))<p class="s06-alert is-success" role="status">{{ session('status') }}</p>@endif
    @if(session('warning'))<p class="s06-alert is-warning" role="alert">{{ session('warning') }}</p>@endif
    @foreach($cart['issues'] as $issue)
        <p class="s06-alert is-warning" role="alert">{{ $issue['message'] }}</p>
    @endforeach

    @if($cart['count'] === 0)
        <div class="s06-empty" data-testid="cart-empty">
            <p class="eyebrow">السلة فارغة</p>
            <h2>ابدأ بقطعة تحبها</h2>
            <p>استكشف التشكيلة واختر المنتج والخيارات المناسبة لك، ثم عد إلى هنا لمراجعة التفاصيل.</p>
            <a class="button button-primary" href="{{ route('catalog') }}">استكشف المنتجات</a>
        </div>
    @else
        <div class="s06-cart-grid">
            <div class="s06-lines" data-testid="cart-lines">
                @foreach($cart['items'] as $item)
                    <article class="s06-line" data-testid="cart-line">
                        <div class="s06-line-media">
                            @if($item['image'])<img src="{{ $item['image'] }}" alt="{{ $item['product'] }}">@endif
                        </div>
                        <div class="s06-line-body">
                            <div>
                                <h2>{{ $item['product'] }}</h2>
                                <p>{{ $item['option_summary'] }}</p>
                                <p class="s06-sku" dir="ltr">{{ $item['sku'] }}</p>
                            </div>
                            @unless($item['available'])<p class="s06-inline-warning">غير متاح بالكمية المطلوبة حاليًا.</p>@endunless
                            @if($item['price_changed'])<p class="s06-inline-warning">تم تحديث السعر إلى السعر الحالي.</p>@endif
                            <dl class="s06-line-pricing">
                                <div><dt>سعر الوحدة</dt><dd><bdi>{{ $item['price'] }}</bdi> ر.س</dd></div>
                                <div><dt>إجمالي السطر</dt><dd data-testid="line-total"><bdi>{{ $item['line_total'] }}</bdi> ر.س</dd></div>
                            </dl>
                            <div class="s06-line-actions">
                                <form method="post" action="{{ route('cart.update', $item['variant_id']) }}" x-data="{ busy: false }" x-bind:aria-busy="busy.toString()" x-on:submit="if (busy) { $event.preventDefault(); return; } busy = true">
                                    @csrf @method('PATCH')
                                    <label for="quantity-{{ $item['variant_id'] }}">الكمية</label>
                                    <input id="quantity-{{ $item['variant_id'] }}" name="quantity" type="number" min="1" max="10" value="{{ $item['quantity'] }}" inputmode="numeric">
                                    <button class="text-action" type="submit" x-bind:disabled="busy" x-bind:aria-busy="busy.toString()" x-text="busy ? 'جارٍ التحديث…' : 'تحديث'">تحديث</button>
                                    <span role="status" aria-live="polite" x-show="busy" x-cloak>جارٍ تحديث الكمية…</span>
                                </form>
                                <form method="post" action="{{ route('cart.destroy', $item['variant_id']) }}" x-data="{ busy: false }" x-bind:aria-busy="busy.toString()" x-on:submit="if (busy) { $event.preventDefault(); return; } busy = true">
                                    @csrf @method('DELETE')
                                    <button class="s06-remove" type="submit" x-bind:disabled="busy" x-bind:aria-busy="busy.toString()" x-text="busy ? 'جارٍ الإزالة…' : 'إزالة'">إزالة</button>
                                    <span role="status" aria-live="polite" x-show="busy" x-cloak>جارٍ إزالة القطعة…</span>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <aside class="s06-summary" aria-labelledby="cart-summary-title">
                <p class="eyebrow">الإجمالي</p>
                <h2 id="cart-summary-title">ملخص السلة</h2>
                <dl>
                    <div><dt>المجموع الفرعي</dt><dd data-testid="cart-subtotal"><bdi>{{ $cart['subtotal'] }}</bdi> ر.س</dd></div>
                    <div><dt>التوصيل</dt><dd>تظهر الرسوم في خطوة إتمام الطلب</dd></div>
                    <div><dt>الضريبة</dt><dd>تظهر وفق السياسة المطبقة عند إتمام الطلب</dd></div>
                </dl>
                <div class="s06-total-preview">
                    <span>الإجمالي قبل التوصيل والضريبة</span>
                    <strong><bdi>{{ $cart['subtotal'] }}</bdi> ر.س</strong>
                </div>
                <p class="s06-boundary-note">سنتحقق من الخيارات والسعر والتوفر مرة أخرى قبل إتمام الطلب.</p>
                @if($cart['checkout_blocked'])
                    <button class="button button-primary" type="button" disabled>المتابعة غير متاحة قبل المراجعة</button>
                @else
                    <a class="button button-primary" href="{{ route('checkout.show') }}" data-testid="proceed-checkout">المتابعة لإتمام الطلب</a>
                @endif
                <a class="s06-secondary-link" href="{{ route('catalog') }}">متابعة التسوق</a>
            </aside>
        </div>
    @endif
</section>
@endsection
