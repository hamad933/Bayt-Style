@extends('layouts.admin')

@section('title', $order->order_number.' — إدارة الطلب')
@section('page-title', 'تفاصيل الطلب')

@section('content')
<section class="s10-order-head">
    <div>
        <a class="s10-back" href="{{ route('admin.orders.index') }}">العودة إلى الطلبات</a>
        <p class="admin-eyebrow">مرجع الطلب</p>
        <h1 dir="ltr">{{ $order->order_number }}</h1>
        <p>{{ $order->customer_full_name }} · <span dir="ltr">{{ $order->customer_email }}</span> · <span dir="ltr">{{ $order->customer_phone }}</span></p>
    </div>
    <div class="s10-state-grid" aria-label="حقائق الحالة المستقلة">
        <div><span>الطلب</span><strong>{{ $order->order_state }}</strong></div>
        <div><span>الدفع</span><strong>{{ $order->payment_state }}</strong></div>
        <div><span>الحجز</span><strong>{{ $order->reservation_state }}</strong></div>
        <div><span>التنفيذ</span><strong>{{ $order->fulfillment_state }}</strong></div>
    </div>
</section>

<section class="s10-grid s10-grid--two">
    <article class="admin-panel">
        <div class="s10-section-head"><div><p class="admin-eyebrow">Snapshot تاريخي</p><h2>بنود الطلب والأسعار</h2></div></div>
        <div class="s10-table-scroll" role="region" aria-label="بنود الطلب" tabindex="0">
            <table class="s10-table s10-table--compact">
                <thead><tr><th>المنتج</th><th>الخيار / SKU</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
                <tbody>
                @foreach ($order->lines as $line)
                    <tr>
                        <td><strong>{{ $line->product_name }}</strong></td>
                        <td><strong>{{ $line->variant_name }}</strong><small dir="ltr">{{ $line->variant_sku }}</small>@foreach($line->options as $option)<small>{{ $option->attribute_name }}: {{ $option->option_value }}</small>@endforeach</td>
                        <td>{{ $line->quantity }}</td>
                        <td dir="ltr">{{ $line->unit_price }} {{ $line->currency }}</td>
                        <td dir="ltr">{{ $line->line_total }} {{ $line->currency }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <dl class="s10-totals">
            <div><dt>المجموع الفرعي</dt><dd dir="ltr">{{ $order->subtotal }} {{ $order->currency }}</dd></div>
            <div><dt>الشحن</dt><dd dir="ltr">{{ $order->shipping_amount }} {{ $order->currency }}</dd></div>
            <div><dt>الضريبة</dt><dd dir="ltr">{{ $order->tax_amount }} {{ $order->currency }}</dd></div>
            <div class="is-total"><dt>الإجمالي</dt><dd dir="ltr">{{ $order->total }} {{ $order->currency }}</dd></div>
        </dl>
    </article>

    <article class="admin-panel">
        <div class="s10-section-head"><div><p class="admin-eyebrow">حقيقة مالية مستقلة</p><h2>الدفع</h2></div></div>
        <dl class="s10-facts">
            <div><dt>طريقة الدفع المسجلة</dt><dd dir="ltr">{{ $order->payment_method_code }}</dd></div>
            <div><dt>حالة الدفع</dt><dd><span class="s10-state s10-state--financial">{{ $order->payment_state }}</span></dd></div>
            <div><dt>العملة</dt><dd dir="ltr">{{ $order->currency }}</dd></div>
        </dl>
        <div class="s10-boundary" role="note">
            <strong>لا توجد سلطة نجاح دفع في المتصفح.</strong>
            <p>هذه الشاشة لا تنفذ تسوية أو بوابة أو استردادًا ماليًا حقيقيًا. حالة الدفع لا تتغير عند إلغاء الطلب أو تحديث التنفيذ.</p>
        </div>

        @if ($order->order_state === 'pending_payment' && $order->reservation_state === 'not_reserved' && $order->fulfillment_state === 'not_started')
            <form class="s10-sensitive-form" method="post" action="{{ route('admin.orders.cancel', $order) }}">
                @csrf
                <h3>إلغاء الطلب المعلّق</h3>
                <p>يغيّر حالة الطلب فقط. لا يغيّر الدفع أو الحجز أو التنفيذ.</p>
                <label><span>سبب القرار</span><textarea name="reason" required minlength="8" maxlength="1000"></textarea></label>
                <button class="admin-button admin-button--danger" type="submit">إلغاء الطلب فقط</button>
            </form>
        @endif
    </article>
</section>

<section class="admin-panel" aria-labelledby="s10-returns-title">
    <div class="s10-section-head">
        <div><p class="admin-eyebrow">Physical return ≠ refund</p><h2 id="s10-returns-title">حالات الإرجاع</h2></div>
        <span class="s10-count">{{ $order->returnCases->count() }}</span>
    </div>

    <div class="s10-return-list">
        @forelse ($order->returnCases as $case)
            <article class="s10-return-card">
                <header>
                    <div><strong dir="ltr">{{ $case->return_number }}</strong><small>الكمية: {{ $case->requested_quantity }} · السبب: {{ $case->reason_code }}</small></div>
                    <span class="s10-state">{{ $case->return_state }}</span>
                </header>

                <div class="s10-return-truth">
                    <span>الفحص: <strong>{{ $case->inspection?->inspection_outcome ?? 'غير مسجل' }}</strong></span>
                    <span>قرار المخزون: <strong>{{ $case->inventoryDisposition?->disposition ?? 'غير مسجل' }}</strong></span>
                    <span>الاستردادات: <strong>{{ $case->refundRecords->count() }}</strong></span>
                    <span>قيود الرصيد: <strong>{{ $case->storeCreditEntries->count() }}</strong></span>
                </div>

                @if ($case->return_state === 'requested')
                    <form class="s10-inline-action" method="post" action="{{ route('admin.returns.authorize', [$order, $case]) }}">@csrf<label><span>سبب الاعتماد</span><input name="reason" required minlength="8"></label><button class="admin-button admin-button--primary">اعتماد الإرجاع</button></form>
                @elseif ($case->return_state === 'authorized')
                    <form class="s10-inline-action" method="post" action="{{ route('admin.returns.receive', [$order, $case]) }}">@csrf<label><span>سبب تسجيل الاستلام</span><input name="reason" required minlength="8"></label><button class="admin-button admin-button--primary">تسجيل الاستلام</button></form>
                @elseif ($case->return_state === 'received')
                    <form class="s10-inline-action" method="post" action="{{ route('admin.returns.inspect', [$order, $case]) }}">@csrf<label><span>نتيجة الفحص</span><input name="inspection_outcome" required maxlength="64" placeholder="وصف نتيجة الفحص"></label><label><span>سبب التسجيل</span><input name="reason" required minlength="8"></label><button class="admin-button admin-button--primary">تسجيل الفحص</button></form>
                @elseif ($case->return_state === 'inspected')
                    <form class="s10-inline-action" method="post" action="{{ route('admin.returns.disposition', [$order, $case]) }}">@csrf<label><span>قرار التصرف</span><select name="disposition" required><option value="hold">حجز</option><option value="sellable">قابل للبيع</option><option value="damaged">تالف</option><option value="repair">إصلاح</option><option value="return_to_supplier">إرجاع للمورّد</option><option value="disposal">إتلاف</option></select></label><label><span>سبب القرار</span><input name="reason" required minlength="8"></label><button class="admin-button admin-button--primary">تسجيل القرار فقط</button></form>
                @endif
            </article>
        @empty
            <p class="s10-empty">لا توجد حالات إرجاع مرتبطة بهذا الطلب.</p>
        @endforelse
    </div>
</section>

<section class="s10-grid s10-grid--two">
    <article class="admin-panel" aria-labelledby="s10-refunds-title">
        <div class="s10-section-head"><div><p class="admin-eyebrow">Read-only financial truth</p><h2 id="s10-refunds-title">سجلات الاسترداد</h2></div></div>
        @forelse ($order->refundRecords as $refund)
            <div class="s10-ledger-row"><div><strong dir="ltr">{{ $refund->refund_reference }}</strong><small>{{ $refund->actor_type }} · {{ $refund->occurred_at?->format('Y-m-d H:i') }}</small></div><div><span class="s10-state s10-state--financial">{{ $refund->refund_state }}</span><strong dir="ltr">{{ $refund->amount }} {{ $refund->currency }}</strong></div></div>
        @empty
            <p class="s10-empty">لا يوجد سجل استرداد مالي. استلام المرتجع لا ينشئ واحدًا تلقائيًا.</p>
        @endforelse
    </article>

    <article class="admin-panel" aria-labelledby="s10-credit-title">
        <div class="s10-section-head"><div><p class="admin-eyebrow">Canonical ledger</p><h2 id="s10-credit-title">قيود رصيد المتجر</h2></div></div>
        @forelse ($order->storeCreditEntries as $entry)
            <div class="s10-ledger-row"><div><strong>{{ $entry->entry_type }}</strong><small>{{ $entry->source_type }} · <span dir="ltr">{{ $entry->source_reference }}</span></small></div><div><span dir="ltr">{{ $entry->amount }} {{ $entry->currency }}</span><small>{{ $entry->occurred_at?->format('Y-m-d H:i') }}</small></div></div>
        @empty
            <p class="s10-empty">لا توجد قيود رصيد. لا يوجد إصدار تلقائي من هذه الشاشة.</p>
        @endforelse
    </article>
</section>

<section class="s10-grid s10-grid--two">
    <article class="admin-panel" aria-labelledby="s10-events-title">
        <div class="s10-section-head"><div><p class="admin-eyebrow">Append-only chronology</p><h2 id="s10-events-title">تسلسل أحداث الطلب</h2></div></div>
        <div class="s10-timeline">
            @foreach ($order->events as $event)
                <div><span>{{ $event->occurred_at?->format('Y-m-d H:i:s') }}</span><strong>{{ $event->event_type }}</strong><small>طلب: {{ $event->resulting_order_state }} · دفع: {{ $event->resulting_payment_state }} · تنفيذ: {{ $event->resulting_fulfillment_state }}</small><code dir="ltr">{{ $event->correlation_id }}</code></div>
            @endforeach
        </div>
    </article>

    <article class="admin-panel" aria-labelledby="s10-audit-title">
        <div class="s10-section-head"><div><p class="admin-eyebrow">Privileged evidence</p><h2 id="s10-audit-title">سجل التدقيق الإداري</h2></div></div>
        <div class="s10-timeline">
            @forelse ($auditLogs as $audit)
                <div><span>{{ $audit->created_at?->format('Y-m-d H:i:s') }}</span><strong>{{ $audit->action }}</strong><small>{{ $audit->actor_identifier }} · {{ $audit->reason }}</small><code dir="ltr">{{ $audit->correlation_id }}</code></div>
            @empty
                <p class="s10-empty">لا توجد تغييرات إدارية حساسة على هذا الطلب بعد.</p>
            @endforelse
        </div>
    </article>
</section>
@endsection
