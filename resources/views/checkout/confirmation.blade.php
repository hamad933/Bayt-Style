@extends('layouts.customer')
@section('title', 'تم استلام الطلب | بيت وأسلوب')
@section('content')
<section class="s06-shell confirmation-page" data-testid="confirmation-page">
    <div class="confirmation-hero">
        <p class="eyebrow">تم استلام الطلب</p>
        <h1>شكرًا لك، تم استلام طلبك</h1>
        <p>احتفظ بالمرجع التالي. الدفع لم يثبت بعد، والمخزون غير محجوز حتى الآن، ولم يتم حجز شحنة.</p>
        <div class="order-reference"><span>مرجع الطلب</span><strong dir="ltr" data-testid="order-reference">{{ $order->order_number }}</strong></div>
    </div>
    <div class="confirmation-grid">
        <section class="confirmation-status" aria-labelledby="status-title">
            <h2 id="status-title">الحالة الحالية</h2>
            <dl>
                <div><dt>الطلب</dt><dd><span data-testid="order-state">تم استلام الطلب وهو بانتظار استكمال الدفع</span></dd></div>
                <div><dt>الدفع</dt><dd><span data-testid="payment-state">الدفع لم يكتمل بعد</span></dd></div>
                <div><dt>حجز المخزون</dt><dd><span data-testid="reservation-state">المخزون غير محجوز حتى الآن</span></dd></div>
                <div><dt>التجهيز</dt><dd><span data-testid="fulfillment-state">تجهيز الطلب لم يبدأ بعد</span></dd></div>
            </dl>
        </section>
        <section class="confirmation-summary" aria-labelledby="summary-title">
            <h2 id="summary-title">ملخص الطلب</h2>
            @foreach($order->lines as $line)
                <article class="confirmation-line">
                    <div><strong>{{ $line->product_name }}</strong><span>{{ $line->variant_name }}</span><small dir="ltr">{{ $line->variant_sku }}</small><small>الكمية: {{ $line->quantity }}</small></div>
                    <bdi>{{ number_format((float)$line->line_total,0) }} ر.س</bdi>
                </article>
            @endforeach
            <dl class="checkout-totals">
                <div><dt>المجموع الفرعي</dt><dd><bdi>{{ number_format((float)$order->subtotal,0) }}</bdi> ر.س</dd></div>
                <div><dt>التوصيل</dt><dd><bdi>{{ number_format((float)$order->shipping_amount,0) }}</bdi> ر.س</dd></div>
                <div><dt>الضريبة</dt><dd><bdi>{{ number_format((float)$order->tax_amount,0) }}</bdi> ر.س</dd></div>
                <div class="grand-total"><dt>الإجمالي</dt><dd data-testid="confirmation-total"><bdi>{{ number_format((float)$order->total,0) }}</bdi> ر.س</dd></div>
            </dl>
        </section>
    </div>
    <div class="confirmation-next">
        <p class="eyebrow">الخطوة التالية</p>
        <h2>يبقى الطلب بانتظار استكمال الخطوات التالية</h2>
        <p>سيبقى الطلب بانتظار استكمال الدفع والتأكد من التوفر وفق السياسة المعتمدة. لا يوجد موعد توصيل مؤكد حتى الآن.</p>
        <div class="s07-actions">
            <a class="button button-primary" href="{{ route('orders.show', $order) }}">عرض حالة الطلب</a>
            <a class="s07-text-link" href="{{ route('catalog') }}">العودة إلى المنتجات</a>
        </div>
    </div>
</section>
@endsection
