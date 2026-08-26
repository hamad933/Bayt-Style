@extends('layouts.admin')

@section('title', 'الطلبات والمدفوعات والمرتجعات — بيت وأسلوب')
@section('page-title', 'الطلبات والمدفوعات والمرتجعات')

@section('content')
<section class="s10-hero" aria-labelledby="s10-orders-title">
    <div>
        <p class="admin-eyebrow">RP01-S10 · حقيقة تشغيلية</p>
        <h1 id="s10-orders-title">الطلبات والمدفوعات والمرتجعات</h1>
        <p>عرض موحّد للحالة الحالية دون دمج حقيقة الطلب مع الدفع أو التنفيذ أو الإرجاع. لا توجد بوابة دفع أو استرداد مفعّلة في هذه البيئة.</p>
    </div>
    <div class="s10-truth-note" role="note">
        <strong>حد مالي صريح</strong>
        <span>رجوع المتصفح أو ادعاء العميل لا يثبت نجاح الدفع، وطلب الاسترداد لا يساوي نجاحه.</span>
    </div>
</section>

<section class="admin-panel" aria-label="مرشحات الطلبات">
    <form class="s10-filters" method="get" action="{{ route('admin.orders.index') }}">
        <label>
            <span>بحث</span>
            <input name="q" value="{{ $search }}" placeholder="رقم الطلب، العميل، البريد، الهاتف">
        </label>
        <label>
            <span>حالة الطلب</span>
            <select name="state">
                <option value="all" @selected($state === 'all')>كل الحالات</option>
                <option value="pending_payment" @selected($state === 'pending_payment')>بانتظار الدفع</option>
                <option value="cancelled" @selected($state === 'cancelled')>ملغي</option>
            </select>
        </label>
        <button class="admin-button admin-button--primary" type="submit">تطبيق</button>
    </form>
</section>

<section class="admin-panel" aria-labelledby="s10-orders-table-title">
    <div class="s10-section-head">
        <div>
            <p class="admin-eyebrow">السجل الدائم</p>
            <h2 id="s10-orders-table-title">الطلبات الحالية</h2>
        </div>
        <span class="s10-count">{{ $orders->total() }} طلب</span>
    </div>

    <div class="s10-table-scroll" role="region" aria-label="جدول الطلبات" tabindex="0" style="width: 100%; min-width: 0; contain: inline-size; direction: ltr;">
        <table class="s10-table" style="direction: rtl;">
            <thead>
            <tr>
                <th>الطلب</th>
                <th>العميل</th>
                <th>القيمة</th>
                <th>حالة الطلب</th>
                <th>الدفع</th>
                <th>الحجز</th>
                <th>التنفيذ</th>
                <th>مرتجعات / استردادات / رصيد</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><strong dir="ltr">{{ $order->order_number }}</strong><small>{{ $order->created_at?->format('Y-m-d H:i') }}</small></td>
                    <td><strong>{{ $order->customer_full_name }}</strong><small dir="ltr">{{ $order->customer_email }}</small></td>
                    <td><strong dir="ltr">{{ number_format((float) $order->total, 2) }} {{ $order->currency }}</strong></td>
                    <td><span class="s10-state">{{ $order->order_state }}</span></td>
                    <td><span class="s10-state s10-state--financial">{{ $order->payment_state }}</span></td>
                    <td><span class="s10-state">{{ $order->reservation_state }}</span></td>
                    <td><span class="s10-state">{{ $order->fulfillment_state }}</span></td>
                    <td><span class="s10-relations">{{ $order->return_cases_count }} / {{ $order->refund_records_count }} / {{ $order->store_credit_entries_count }}</span></td>
                    <td><a class="admin-button admin-button--secondary" href="{{ route('admin.orders.show', $order) }}">فتح</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="s10-empty">لا توجد طلبات مطابقة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="s10-pagination">{{ $orders->links() }}</div>
</section>
@endsection
