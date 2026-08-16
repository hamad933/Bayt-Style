@extends('layouts.customer')
@section('title', 'إتمام الطلب | بيت وأسلوب')
@section('content')
<section class="s06-shell checkout-page" data-testid="checkout-page">
    <nav class="s06-crumbs" aria-label="مسار الصفحة"><a href="{{ route('cart.index') }}">السلة</a><span aria-hidden="true">/</span><span>إتمام الطلب</span></nav>
    <header class="s06-page-head checkout-head">
        <p class="eyebrow">إتمام الطلب كزائر</p>
        <h1>تفاصيل واضحة قبل التأكيد</h1>
        <p>هذه بيئة S06 غير مفعّلة تجاريًا: الشحن والضريبة وطريقة الدفع أدناه حدود تطويرية لإثبات الرحلة وليست سياسة إنتاجية.</p>
    </header>

    @if($errors->any())
        <div class="s06-error-summary" role="alert" tabindex="-1" data-testid="checkout-errors">
            <strong>راجع الحقول التالية:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('checkout.store') }}" class="checkout-form" data-testid="checkout-form">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $token }}">
        <div class="checkout-main">
            <section class="checkout-section" aria-labelledby="contact-title">
                <span class="checkout-step" aria-hidden="true">01</span>
                <div><p class="eyebrow">بيانات العميل</p><h2 id="contact-title">كيف نتواصل معك؟</h2></div>
                <div class="field-grid">
                    <label class="field field-wide">الاسم الكامل<input name="full_name" value="{{ old('full_name') }}" autocomplete="name" required></label>
                    <label class="field">البريد الإلكتروني<input name="email" type="email" value="{{ old('email') }}" autocomplete="email" dir="ltr" required></label>
                    <label class="field">رقم الجوال<input name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" dir="ltr" required></label>
                </div>
            </section>

            <section class="checkout-section" aria-labelledby="address-title">
                <span class="checkout-step" aria-hidden="true">02</span>
                <div><p class="eyebrow">عنوان التسليم</p><h2 id="address-title">أين يصل الطلب؟</h2></div>
                <div class="field-grid">
                    <label class="field">الدولة
                        <select name="country_code" required><option value="{{ config('commerce.checkout.country_code') }}" @selected(old('country_code', config('commerce.checkout.country_code')) === config('commerce.checkout.country_code'))>{{ config('commerce.checkout.country_name_ar') }}</option></select>
                    </label>
                    <label class="field">المنطقة / المحافظة<input name="region" value="{{ old('region') }}" autocomplete="address-level1" required></label>
                    <label class="field">المدينة<input name="city" value="{{ old('city') }}" autocomplete="address-level2" required></label>
                    <label class="field">الحي <span class="optional">اختياري</span><input name="district" value="{{ old('district') }}"></label>
                    <label class="field field-wide">الشارع / سطر العنوان<input name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" required></label>
                    <label class="field">المبنى / الوحدة <span class="optional">اختياري</span><input name="building_unit" value="{{ old('building_unit') }}"></label>
                    <label class="field">الرمز البريدي <span class="optional">عند انطباقه</span><input name="postal_code" value="{{ old('postal_code') }}" inputmode="numeric" dir="ltr"></label>
                    <label class="field field-wide">ملاحظات التسليم <span class="optional">اختياري</span><textarea name="delivery_notes" rows="3">{{ old('delivery_notes') }}</textarea></label>
                </div>
            </section>

            <section class="checkout-section" aria-labelledby="delivery-title">
                <span class="checkout-step" aria-hidden="true">03</span>
                <div><p class="eyebrow">طريقة التسليم</p><h2 id="delivery-title">اختر طريقة الشحن</h2></div>
                <div class="choice-list">
                    @foreach($methods as $method)
                        <label class="choice">
                            <input type="radio" name="shipping_method" value="{{ $method['code'] }}" @checked(old('shipping_method',$shippingCode) === $method['code']) required>
                            <span><strong>{{ $method['name_ar'] }}</strong><small>قيمة تطويرية حتمية لا تمثل تسعير ناقل إنتاجي.</small></span>
                            <bdi>{{ number_format($method['amount_minor']/100,0) }} ر.س</bdi>
                        </label>
                    @endforeach
                </div>
                <p class="s06-boundary-note">التوقيت والرسوم الفعلية يعتمدان على الوجهة وسياسة الطلب عند تفعيل مزود شحن معتمد؛ لا يوجد SLA لناقل مفعّل في S06.</p>
            </section>

            <section class="checkout-section" aria-labelledby="payment-title">
                <span class="checkout-step" aria-hidden="true">04</span>
                <div><p class="eyebrow">حد الدفع</p><h2 id="payment-title">طريقة الدفع</h2></div>
                <label class="choice">
                    <input type="radio" name="payment_method" value="{{ config('commerce.checkout.payment_method_code') }}" checked required>
                    <span><strong>مسار يدوي تجريبي — قيد الانتظار</strong><small>لا توجد بوابة دفع مفعّلة، ولا بيانات بنكية أو بطاقة مطلوبة.</small></span>
                </label>
                <p class="s06-boundary-note">تأكيد Checkout لن يعني أن الدفع نجح. ستبقى حالة الدفع <bdi dir="ltr">pending / unpaid</bdi> حتى توجد سلطة دفع مستقلة في موجة لاحقة.</p>
            </section>

            <section class="checkout-section" aria-labelledby="consent-title">
                <span class="checkout-step" aria-hidden="true">05</span>
                <div><p class="eyebrow">المراجعة والموافقة</p><h2 id="consent-title">راجع ثم أكّد</h2></div>
                <label class="consent-row">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                    <span>أوافق صراحةً على إرسال هذا الطلب ضمن سياسة الموافقة التطويرية ذات الإصدار <bdi dir="ltr">{{ config('commerce.checkout.consent_version') }}</bdi>. هذه الصياغة ليست وثيقة قانونية إنتاجية نهائية.</span>
                </label>
            </section>
        </div>

        <aside class="checkout-summary" aria-labelledby="checkout-summary-title">
            <p class="eyebrow">المراجعة</p><h2 id="checkout-summary-title">ملخص الطلب</h2>
            <div class="checkout-items">
                @foreach($cart['items'] as $item)
                    <div class="checkout-item">
                        <div><strong>{{ $item['product'] }}</strong><span>{{ $item['option_summary'] }}</span><small dir="ltr">{{ $item['sku'] }}</small><small>الكمية: {{ $item['quantity'] }}</small></div>
                        <bdi>{{ $item['line_total'] }} ر.س</bdi>
                    </div>
                @endforeach
            </div>
            <dl class="checkout-totals">
                <div><dt>المجموع الفرعي</dt><dd><bdi>{{ number_format($totals['subtotal_minor']/100,0) }}</bdi> ر.س</dd></div>
                <div><dt>الشحن التجريبي</dt><dd><bdi>{{ number_format($totals['shipping_minor']/100,0) }}</bdi> ر.س</dd></div>
                <div><dt>الضريبة</dt><dd><bdi>{{ number_format($totals['tax_minor']/100,0) }}</bdi> ر.س</dd></div>
                <div class="grand-total"><dt>الإجمالي</dt><dd data-testid="checkout-total"><bdi>{{ number_format($totals['total_minor']/100,0) }}</bdi> ر.س</dd></div>
            </dl>
            <p class="s06-policy-code">سياسة الضريبة التطويرية: <bdi dir="ltr">{{ $totals['tax_policy_code'] }}</bdi></p>
            <button class="button button-primary checkout-submit" type="submit" data-testid="confirm-checkout">تأكيد Checkout وإنشاء الطلب</button>
            <p class="s06-boundary-note">لن يحجز هذا الإجراء المخزون، ولن يثبت الدفع أو الشحن. تُحفظ الحقائق المستخدمة في الطلب كلقطات تاريخية.</p>
        </aside>
    </form>
</section>
@endsection
