@extends('layouts.customer')
@section('title', 'تم استلام الطلب | بيت وأسلوب')
@section('content')
<section class="s06-shell confirmation-page" data-testid="confirmation-page">
    <div class="confirmation-hero">
        <p class="eyebrow">تم استلام Checkout</p>
        <h1>تم إنشاء سجل الطلب بنجاح</h1>
        <p>احتفظ بالمرجع التالي. لم يتم إثبات الدفع، ولم يُحجز المخزون، ولم يتم حجز شحنة.</p>
        <div class="order-reference"><span>مرجع الطلب</span><strong dir="ltr" data-testid="order-reference">{{ $order->order_number }}</strong></div>
    </div>
    <div class="confirmation-grid">
        <section class="confirmation-status" aria-labelledby="status-title">
            <h2 id="status-title">الحالة الحالية</h2>
            <dl>
                <div><dt>الطلب</dt><dd><span>بانتظار الدفع</span><bdi dir="ltr">{{ $order->order_state }}</bdi></dd></div>
                <div><dt>الدفع</dt><dd><span>معلّق / غير مسدد</span><bdi dir="ltr" data-testid="payment-state">{{ $order->payment_state }}</bdi></dd></div>
                <div><dt>حجز المخزون</dt><dd><span>غير محجوز — السياسة غير مفعّلة</span><bdi dir="ltr" data-testid="reservation-state">{{ $order->reservation_state }}</bdi></dd></div>
                <div><dt>التنفيذ</dt><dd><span>لم يبدأ</span><bdi dir="ltr">{{ $order->fulfillment_state }}</bdi></dd></div>
            </dl>
        </section>
        <section class="confirmation-summary" aria-labelledby="summary-title">
            <h2 id="summary-title">ملخص محفوظ</h2>
            @foreach($order->lines as $line)
                <article class="confirmation-line">
                    <div><strong>{{ $line->product_name }}</strong><span>{{ $line->variant_name }}</span><small dir="ltr">{{ $line->variant_sku }}</small><small>الكمية: {{ $line->quantity }}</small></div>
                    <bdi>{{ number_format((float)$line->line_total,0) }} ر.س</bdi>
                </article>
            @endforeach
            <dl class="checkout-totals">
                <div><dt>المجموع الفرعي</dt><dd><bdi>{{ number_format((float)$order->subtotal,0) }}</bdi> ر.س</dd></div>
                <div><dt>الشحن</dt><dd><bdi>{{ number_format((float)$order->shipping_amount,0) }}</bdi> ر.س</dd></div>
                <div><dt>الضريبة</dt><dd><bdi>{{ number_format((float)$order->tax_amount,0) }}</bdi> ر.س</dd></div>
                <div class="grand-total"><dt>الإجمالي</dt><dd data-testid="confirmation-total"><bdi>{{ number_format((float)$order->total,0) }}</bdi> ر.س</dd></div>
            </dl>
        </section>
    </div>
    <div class="confirmation-next">
        <p class="eyebrow">الخطوة التالية</p>
        <h2>يبقى الطلب في حالة انتظار</h2>
        <p>المسار الحالي يثبت إنشاء الطلب ولقطاته فقط. تفعيل سياسة الدفع أو الحجز أو التنفيذ وإتاحة تتبع S07 ليست جزءًا من هذه الموجة.</p>
        <a class="button button-primary" href="{{ route('catalog') }}">العودة إلى المنتجات</a>
    </div>
</section>
@endsection
