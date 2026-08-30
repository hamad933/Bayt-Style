@extends('layouts.customer')
@section('title', 'إتمام الطلب | بيت وأسلوب')
@section('content')
<section class="s06-shell checkout-page" data-testid="checkout-page">
    <nav class="s06-crumbs" aria-label="مسار الصفحة"><a href="{{ route('cart.index') }}">السلة</a><span aria-hidden="true">/</span><span>إتمام الطلب</span></nav>
    <header class="s06-page-head checkout-head">
        <p class="eyebrow">إتمام الطلب كزائر</p>
        <h1>تفاصيل واضحة قبل التأكيد</h1>
        <p>هذه تجربة شراء غير مفعّلة تجاريًا بالكامل. رسوم التوصيل والضريبة وطريقة الدفع المعروضة حاليًا لأغراض تجربة الطلب وليست سياسة نهائية.</p>
    </header>

    @if($errors->any())
        <div class="s06-error-summary" id="checkout-errors" role="alert" tabindex="-1" data-testid="checkout-errors">
            <strong>راجع الحقول التالية:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('checkout.store') }}" class="checkout-form" data-testid="checkout-form"
        x-data="{ submitting: false }"
        @submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
        :aria-busy="submitting.toString()">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $token }}">
        <div class="checkout-main">
            <section class="checkout-section" aria-labelledby="contact-title">
                <span class="checkout-step" aria-hidden="true">01</span>
                <div><p class="eyebrow">بيانات العميل</p><h2 id="contact-title">كيف نتواصل معك؟</h2></div>
                <div class="field-grid">
                    <label class="field field-wide">الاسم الكامل
                        <input name="full_name" value="{{ old('full_name') }}" autocomplete="name" required @error('full_name') aria-invalid="true" aria-describedby="full-name-error" @enderror>
                        @error('full_name')<small class="field-error" id="full-name-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">البريد الإلكتروني
                        <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" dir="ltr" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')<small class="field-error" id="email-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">رقم الجوال
                        <input name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" dir="ltr" required @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                        @error('phone')<small class="field-error" id="phone-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            </section>

            <section class="checkout-section" aria-labelledby="address-title">
                <span class="checkout-step" aria-hidden="true">02</span>
                <div><p class="eyebrow">عنوان التسليم</p><h2 id="address-title">أين يصل الطلب؟</h2></div>
                <div class="field-grid">
                    <label class="field">الدولة
                        <select name="country_code" required @error('country_code') aria-invalid="true" aria-describedby="country-code-error" @enderror><option value="{{ config('commerce.checkout.country_code') }}" @selected(old('country_code', config('commerce.checkout.country_code')) === config('commerce.checkout.country_code'))>{{ config('commerce.checkout.country_name_ar') }}</option></select>
                        @error('country_code')<small class="field-error" id="country-code-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">المنطقة / المحافظة
                        <input name="region" value="{{ old('region') }}" autocomplete="address-level1" required @error('region') aria-invalid="true" aria-describedby="region-error" @enderror>
                        @error('region')<small class="field-error" id="region-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">المدينة
                        <input name="city" value="{{ old('city') }}" autocomplete="address-level2" required @error('city') aria-invalid="true" aria-describedby="city-error" @enderror>
                        @error('city')<small class="field-error" id="city-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">الحي <span class="optional">اختياري</span><input name="district" value="{{ old('district') }}"></label>
                    <label class="field field-wide">الشارع / سطر العنوان
                        <input name="address_line" value="{{ old('address_line') }}" autocomplete="street-address" required @error('address_line') aria-invalid="true" aria-describedby="address-line-error" @enderror>
                        @error('address_line')<small class="field-error" id="address-line-error">{{ $message }}</small>@enderror
                    </label>
                    <label class="field">المبنى / الوحدة <span class="optional">اختياري</span><input name="building_unit" value="{{ old('building_unit') }}"></label>
                    <label class="field">الرمز البريدي <span class="optional">عند انطباقه</span><input name="postal_code" value="{{ old('postal_code') }}" inputmode="numeric" dir="ltr"></label>
                    <label class="field field-wide">ملاحظات التسليم <span class="optional">اختياري</span><textarea name="delivery_notes" rows="3">{{ old('delivery_notes') }}</textarea></label>
                </div>
            </section>

            <section class="checkout-section" aria-labelledby="delivery-title">
                <span class="checkout-step" aria-hidden="true">03</span>
                <div><p class="eyebrow">طريقة التسليم</p><h2 id="delivery-title">اختر طريقة التوصيل</h2></div>
                <div class="choice-list">
                    @foreach($methods as $method)
                        <label class="choice">
                            <input type="radio" name="shipping_method" value="{{ $method['code'] }}" @checked(old('shipping_method',$shippingCode) === $method['code']) required @error('shipping_method') aria-invalid="true" aria-describedby="shipping-method-error" @enderror>
                            <span><strong>{{ $method['name_ar'] }}</strong><small>رسوم التوصيل المعروضة حاليًا تجريبية وليست تسعيرًا نهائيًا.</small></span>
                            <bdi>{{ number_format($method['amount_minor']/100,0) }} ر.س</bdi>
                        </label>
                    @endforeach
                </div>
                @error('shipping_method')<p class="field-error" id="shipping-method-error">{{ $message }}</p>@enderror
                <p class="s06-boundary-note">سيتم تحديد تفاصيل التوصيل النهائية وفق الوجهة والسياسة المعتمدة. لا يوجد موعد توصيل مؤكد حاليًا.</p>
            </section>

            <section class="checkout-section" aria-labelledby="payment-title">
                <span class="checkout-step" aria-hidden="true">04</span>
                <div><p class="eyebrow">الدفع</p><h2 id="payment-title">حالة الدفع الحالية</h2></div>
                <label class="choice">
                    <input type="radio" name="payment_method" value="{{ config('commerce.checkout.payment_method_code') }}" checked required @error('payment_method') aria-invalid="true" aria-describedby="payment-method-error" @enderror>
                    <span><strong>الدفع غير مكتمل بعد</strong><small>لا توجد وسيلة دفع إلكترونية مفعّلة حاليًا، ولن نطلب بيانات بطاقة أو بيانات بنكية في هذه الخطوة.</small></span>
                </label>
                @error('payment_method')<p class="field-error" id="payment-method-error">{{ $message }}</p>@enderror
                <p class="s06-boundary-note">تأكيد الطلب لا يعني نجاح الدفع. سيبقى الدفع غير مكتمل حتى يتم التحقق منه عبر وسيلة معتمدة.</p>
            </section>

            <section class="checkout-section" aria-labelledby="consent-title">
                <span class="checkout-step" aria-hidden="true">05</span>
                <div><p class="eyebrow">المراجعة والموافقة</p><h2 id="consent-title">راجع ثم أكّد</h2></div>
                <label class="consent-row">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required @error('terms') aria-invalid="true" aria-describedby="terms-error" @enderror>
                    <span>أوافق على إرسال بيانات هذا الطلب للمراجعة وفق الشروط المعروضة في هذه التجربة. هذه الصياغة مؤقتة وليست بديلًا عن الشروط القانونية النهائية عند الإطلاق.</span>
                </label>
                @error('terms')<p class="field-error" id="terms-error">{{ $message }}</p>@enderror
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
                <div><dt>رسوم التوصيل الحالية</dt><dd><bdi>{{ number_format($totals['shipping_minor']/100,0) }}</bdi> ر.س</dd></div>
                <div><dt>الضريبة الحالية</dt><dd><bdi>{{ number_format($totals['tax_minor']/100,0) }}</bdi> ر.س</dd></div>
                <div class="grand-total"><dt>الإجمالي</dt><dd data-testid="checkout-total"><bdi>{{ number_format($totals['total_minor']/100,0) }}</bdi> ر.س</dd></div>
            </dl>
            <p class="s06-policy-code">لا تُضاف ضريبة نهائية في التجربة الحالية؛ ستعتمد الضريبة النهائية على السياسة المعتمدة عند التفعيل.</p>
            <button class="button button-primary checkout-submit" type="submit" data-testid="confirm-checkout"
                :disabled="submitting" :aria-busy="submitting.toString()"
                x-text="submitting ? 'جارٍ تأكيد الطلب…' : 'تأكيد الطلب'">تأكيد الطلب</button>
            <p class="s06-boundary-note" role="status" aria-live="polite" aria-atomic="true" x-show="submitting" x-cloak data-testid="checkout-submitting-status">جارٍ إرسال الطلب. يرجى عدم إغلاق الصفحة أو إعادة الإرسال.</p>
            <p class="s06-boundary-note">لن يحجز هذا التأكيد المخزون، ولن يثبت الدفع أو حجز الشحنة. سنحتفظ بتفاصيل الطلب كما ظهرت لك عند التأكيد.</p>
        </aside>
    </form>
</section>
@if($errors->any())
<script>
    window.addEventListener('DOMContentLoaded', () => {
        document.getElementById('checkout-errors')?.focus();
    }, { once: true });
</script>
@endif
@endsection
