@extends('layouts.customer')
@section('title', 'حالة الطلب | بيت وأسلوب')
@section('description', 'متابعة حالة طلبك المحفوظة كما حدثت فعليًا.')
@section('content')
<section class="s07-shell order-status-page" data-testid="order-status-page">
    <nav class="s07-crumbs" aria-label="مسار الصفحة">
        <a href="{{ route('home') }}">الرئيسية</a>
        <span aria-hidden="true">/</span>
        <span>حالة الطلب</span>
    </nav>

    <header class="s07-hero">
        <div>
            <p class="eyebrow">حالة الطلب</p>
            <h1 data-testid="overall-order-state">{{ $status['order']['label'] }}</h1>
            <p>{{ $status['order']['detail'] }}</p>
        </div>
        <div class="s07-reference" aria-label="مرجع الطلب">
            <span>مرجع الطلب</span>
            <strong dir="ltr" data-testid="status-order-reference">{{ $order->order_number }}</strong>
        </div>
    </header>

    <section class="s07-status-grid" aria-label="الحالات الحالية">
        <article>
            <span>الدفع</span>
            <strong data-testid="status-payment">{{ $status['payment']['label'] }}</strong>
            <p>{{ $status['payment']['detail'] }}</p>
        </article>
        <article>
            <span>المخزون</span>
            <strong data-testid="status-reservation">{{ $status['reservation']['label'] }}</strong>
            <p>{{ $status['reservation']['detail'] }}</p>
        </article>
        <article>
            <span>التجهيز</span>
            <strong data-testid="status-fulfillment">{{ $status['fulfillment']['label'] }}</strong>
            <p>{{ $status['fulfillment']['detail'] }}</p>
        </article>
        <article class="s07-shipment-state" data-testid="shipment-empty">
            <span>الشحن والتتبع</span>
            <strong>لا توجد شحنة حتى الآن</strong>
            <p>لا يوجد ناقل أو رقم تتبع أو موعد تسليم مؤكد لهذا الطلب حتى الآن.</p>
        </article>
    </section>

    <div class="s07-content-grid">
        <div class="s07-primary">
            <section class="s07-section" aria-labelledby="timeline-title">
                <div class="s07-section-head">
                    <p class="eyebrow">ما حدث فعليًا</p>
                    <h2 id="timeline-title">سجل الطلب</h2>
                </div>
                <ol class="s07-timeline" data-testid="order-timeline">
                    @forelse($status['timeline'] as $event)
                        <li data-testid="order-event">
                            <div class="s07-timeline-marker" aria-hidden="true"></div>
                            <div>
                                <time data-testid="event-time" datetime="{{ $event['occurred_at']->toIso8601String() }}">
                                    {{ $event['occurred_at']->translatedFormat('j M Y، H:i') }}
                                </time>
                                <h3>{{ $event['title'] }}</h3>
                                <p>{{ $event['detail'] }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="s07-timeline-empty">لا توجد تحديثات مسجّلة بعد.</li>
                    @endforelse
                </ol>
            </section>

            <section class="s07-section" aria-labelledby="items-title">
                <div class="s07-section-head">
                    <p class="eyebrow">المحفوظ مع الطلب</p>
                    <h2 id="items-title">المنتجات والكميات</h2>
                </div>
                <div class="s07-items" data-testid="saved-order-items">
                    @foreach($order->lines as $line)
                        <article class="s07-item">
                            <div class="s07-item-copy">
                                <h3>{{ $line->product_name }}</h3>
                                <p>{{ $line->variant_name }}</p>
                                @if($line->options->isNotEmpty())
                                    <ul>
                                        @foreach($line->options as $option)
                                            <li>{{ $option->attribute_name }}: {{ $option->option_value }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="s07-item-meta">
                                    <span>الكمية: <strong>{{ $line->quantity }}</strong></span>
                                    <span>سعر الوحدة: <bdi>{{ number_format((float) $line->unit_price, 0) }}</bdi> ر.س</span>
                                </div>
                            </div>
                            <div class="s07-item-total">
                                <span>الإجمالي</span>
                                <strong><bdi>{{ number_format((float) $line->line_total, 0) }}</bdi> ر.س</strong>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="s07-side" aria-label="ملخص الطلب">
            <section class="s07-summary">
                <p class="eyebrow">المبالغ المحفوظة</p>
                <h2>ملخص الإجمالي</h2>
                <dl>
                    <div><dt>المجموع الفرعي</dt><dd><bdi>{{ number_format((float) $order->subtotal, 0) }}</bdi> ر.س</dd></div>
                    <div><dt>التوصيل</dt><dd><bdi>{{ number_format((float) $order->shipping_amount, 0) }}</bdi> ر.س</dd></div>
                    <div><dt>الضريبة</dt><dd><bdi>{{ number_format((float) $order->tax_amount, 0) }}</bdi> ر.س</dd></div>
                    <div class="s07-grand-total"><dt>الإجمالي</dt><dd data-testid="status-total"><bdi>{{ number_format((float) $order->total, 0) }}</bdi> ر.س</dd></div>
                </dl>
            </section>

            <section class="s07-destination" data-testid="delivery-summary">
                <p class="eyebrow">وجهة التوصيل</p>
                <h2>ملخص يحافظ على الخصوصية</h2>
                @if($status['destination']['recipient'])
                    <p><strong>إلى {{ $status['destination']['recipient'] }}</strong></p>
                @endif
                <p>{{ $status['destination']['location'] }}</p>
                @if($status['destination']['contact_hint'])
                    <p>{{ $status['destination']['contact_hint'] }}</p>
                @endif
                <small>لا نعرض سطر العنوان التفصيلي أو بيانات التواصل الكاملة في صفحة المتابعة.</small>
            </section>
        </aside>
    </div>

    <section class="s07-next" aria-labelledby="next-title">
        <p class="eyebrow">ما التالي؟</p>
        <h2 id="next-title">التوقع التالي بحسب الحقيقة الحالية</h2>
        <p data-testid="next-expectation">{{ $status['next_expectation'] }}</p>
        <div class="s07-actions">
            <a class="button button-primary" href="{{ route('catalog') }}">متابعة التسوق</a>
            <a class="s07-text-link" href="{{ route('home') }}">العودة إلى الرئيسية</a>
        </div>
    </section>

    <section class="s07-next" aria-labelledby="returns-title">
        <p class="eyebrow">المرتجعات والاسترداد</p>
        <h2 id="returns-title">تفاصيل ما بعد الطلب في مكان واحد</h2>
        <p>تحقق من أهلية منتجات الطلب للمرتجع، وحالة أي مرتجع أو استرداد مالي مسجّل، وأي رصيد متجر صادر — من دون افتراض خطوات لم تحدث.</p>
        <div class="s07-actions">
            <a class="button button-primary" href="{{ route('orders.returns.index', $order) }}" data-testid="open-returns">عرض المرتجعات والاسترداد</a>
        </div>
    </section>
</section>
@endsection
