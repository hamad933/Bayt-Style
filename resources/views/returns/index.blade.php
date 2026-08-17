@extends('layouts.customer')

@section('title', 'المرتجعات والاسترداد | بيت وأسلوب')
@section('description', 'متابعة أهلية المرتجعات وحالتها والاسترداد ورصيد المتجر كما سُجّلت فعليًا.')

@section('content')
<section class="s08-shell" data-testid="returns-page">
    <nav class="s08-crumbs" aria-label="مسار الصفحة">
        <a href="{{ route('home') }}">الرئيسية</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('orders.show', $order) }}">حالة الطلب</a>
        <span aria-hidden="true">/</span>
        <span>المرتجعات والاسترداد</span>
    </nav>

    <header class="s08-hero">
        <div>
            <p class="eyebrow">ما بعد الطلب</p>
            <h1>المرتجعات والاسترداد</h1>
            <p>هنا تظهر فقط الحالات المسجّلة فعليًا لطلبك: أهلية المرتجع، وما تم استلامه أو فحصه، وحالة أي استرداد مالي، وأي رصيد متجر صادر.</p>
        </div>
        <div class="s08-reference" aria-label="مرجع الطلب">
            <span>مرجع الطلب</span>
            <strong dir="ltr">{{ $order->order_number }}</strong>
        </div>
    </header>

    @if(session('return_notice'))
        <p class="s08-notice" role="status">{{ session('return_notice') }}</p>
    @endif

    @if($errors->has('return'))
        <p class="s08-error" role="alert">{{ $errors->first('return') }}</p>
    @endif

    <section class="s08-eligibility" aria-labelledby="eligibility-title" data-testid="return-eligibility-state">
        <div class="s08-section-intro">
            <p class="eyebrow">أهلية المرتجع</p>
            <h2 id="eligibility-title">{{ $returns['eligibility']['label'] }}</h2>
        </div>
        <p>{{ $returns['eligibility']['detail'] }}</p>
    </section>

    <div class="s08-main-grid">
        <div class="s08-primary">
            <section class="s08-section" aria-labelledby="return-items-title">
                <div class="s08-section-head">
                    <p class="eyebrow">منتجات الطلب</p>
                    <h2 id="return-items-title">ما الذي يمكن طلب إرجاعه الآن؟</h2>
                    <p>كل منتج يعرض أهلية مستقلة، ولا نفتح طلب مرتجع لمجرد وجوده في الطلب.</p>
                </div>

                <div class="s08-order-lines" data-testid="return-order-lines">
                    @foreach($returns['lines'] as $line)
                        <article class="s08-order-line">
                            <div class="s08-line-copy">
                                <h3>{{ $line['product_name'] }}</h3>
                                <p>{{ $line['variant_name'] }}</p>
                                <small>الكمية في الطلب: {{ $line['ordered_quantity'] }}</small>
                            </div>

                            <div class="s08-line-action">
                                @if($line['eligible'])
                                    <p class="s08-available">{{ $line['eligibility_detail'] }}</p>
                                    <form method="POST" action="{{ route('orders.returns.store', $order) }}" class="s08-return-form">
                                        @csrf
                                        <input type="hidden" name="line_ref" value="{{ $line['sku'] }}">
                                        <label>
                                            <span>الكمية</span>
                                            <select name="quantity" aria-label="كمية المرتجع">
                                                @for($quantity = 1; $quantity <= $line['eligible_quantity']; $quantity++)
                                                    <option value="{{ $quantity }}">{{ $quantity }}</option>
                                                @endfor
                                            </select>
                                        </label>
                                        <label>
                                            <span>السبب</span>
                                            <select name="reason" aria-label="سبب المرتجع">
                                                @foreach($returns['reasons'] as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <button class="button button-primary" type="submit" data-testid="start-return">بدء طلب المرتجع</button>
                                    </form>
                                @else
                                    <p class="s08-unavailable" data-testid="line-return-unavailable">{{ $line['eligibility_detail'] }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="s08-section" aria-labelledby="cases-title">
                <div class="s08-section-head">
                    <p class="eyebrow">طلبات المرتجع</p>
                    <h2 id="cases-title">ما حدث فعليًا للمرتجعات</h2>
                    <p>حالة المرتجع المادي مستقلة عن الاسترداد المالي وعن قرار المخزون بعد الفحص.</p>
                </div>

                <div class="s08-case-list" data-testid="return-case-list">
                    @forelse($returns['cases'] as $case)
                        <article class="s08-case" data-testid="return-case">
                            <div class="s08-case-top">
                                <div>
                                    <span>مرجع المرتجع</span>
                                    <strong dir="ltr">{{ $case['reference'] }}</strong>
                                </div>
                                <div>
                                    <span>الكمية</span>
                                    <strong>{{ $case['quantity'] }}</strong>
                                </div>
                            </div>
                            <h3>{{ $case['product_name'] }}</h3>
                            <p class="s08-muted">{{ $case['variant_name'] }} · {{ $case['reason'] }}</p>
                            <div class="s08-state-copy">
                                <strong>{{ $case['state']['label'] }}</strong>
                                <p>{{ $case['state']['detail'] }}</p>
                            </div>

                            @if($case['disposition'])
                                <p class="s08-disposition">
                                    <span>حالة المنتج بعد الفحص:</span>
                                    <strong>{{ $case['disposition'] }}</strong>
                                    <small>هذا قرار تصنيف بعد الفحص، وليس تأكيدًا على زيادة المخزون المتاح للبيع.</small>
                                </p>
                            @endif

                            <ol class="s08-timeline">
                                @foreach($case['timeline'] as $event)
                                    <li>
                                        <time datetime="{{ $event['occurred_at']->toIso8601String() }}">
                                            {{ $event['occurred_at']->translatedFormat('j M Y، H:i') }}
                                        </time>
                                        <span>{{ $event['label'] }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </article>
                    @empty
                        <p class="s08-empty" data-testid="return-cases-empty">لا توجد طلبات مرتجع مسجّلة لهذا الطلب حتى الآن.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="s08-side" aria-label="الاسترداد ورصيد المتجر">
            <section class="s08-financial-section" data-testid="refund-truth">
                <p class="eyebrow">الاسترداد المالي</p>
                <h2>حالة مستقلة عن المرتجع</h2>
                @forelse($returns['refunds'] as $refund)
                    <article class="s08-refund-row">
                        <strong>{{ $refund['state']['label'] }}</strong>
                        <p>{{ $refund['state']['detail'] }}</p>
                        <div>
                            <span><bdi>{{ number_format($refund['amount'], 2) }}</bdi> {{ $refund['currency'] }}</span>
                            <time datetime="{{ $refund['occurred_at']->toIso8601String() }}">
                                {{ $refund['occurred_at']->translatedFormat('j M Y') }}
                            </time>
                        </div>
                    </article>
                @empty
                    <p class="s08-empty" data-testid="refund-empty">لا توجد عملية استرداد مسجّلة لهذا الطلب حتى الآن.</p>
                @endforelse
            </section>

            <section class="s08-financial-section" data-testid="store-credit-truth">
                <p class="eyebrow">رصيد المتجر</p>
                <h2>الرصيد من سجل القيود</h2>

                <div class="s08-credit-balances">
                    @foreach($returns['store_credit']['balances'] as $balance)
                        <div>
                            <span>الرصيد الحالي</span>
                            <strong data-testid="store-credit-balance">
                                <bdi>{{ number_format($balance['amount'], 2) }}</bdi> {{ $balance['currency'] }}
                            </strong>
                        </div>
                    @endforeach
                </div>

                @forelse($returns['store_credit']['entries'] as $entry)
                    <div class="s08-ledger-entry" data-testid="store-credit-entry">
                        <div>
                            <strong>{{ $entry['label'] }}</strong>
                            <time datetime="{{ $entry['occurred_at']->toIso8601String() }}">
                                {{ $entry['occurred_at']->translatedFormat('j M Y') }}
                            </time>
                        </div>
                        <span dir="ltr">
                            {{ $entry['delta'] >= 0 ? '+' : '−' }}{{ number_format(abs($entry['delta']), 2) }} {{ $entry['currency'] }}
                        </span>
                    </div>
                @empty
                    <p class="s08-empty" data-testid="store-credit-empty">لم يصدر رصيد متجر لهذا الطلب حتى الآن.</p>
                @endforelse
            </section>
        </aside>
    </div>

    <section class="s08-truth-note" aria-labelledby="truth-note-title">
        <p class="eyebrow">كيف نعرض الحالة؟</p>
        <h2 id="truth-note-title">كل حقيقة تبقى مستقلة</h2>
        <p>استلام المرتجع لا يعني تلقائيًا اكتمال الاسترداد، وفحص المنتج لا يعني تلقائيًا إعادته إلى المخزون المتاح، ولا يُنشأ رصيد متجر إلا إذا وُجد قيد صريح مسجّل.</p>
        <a class="s08-text-link" href="{{ route('orders.show', $order) }}">العودة إلى حالة الطلب</a>
    </section>
</section>
@endsection
