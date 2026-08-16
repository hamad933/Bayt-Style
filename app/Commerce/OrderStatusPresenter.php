<?php

namespace App\Commerce;

use App\Models\Order;
use App\Models\OrderEvent;

class OrderStatusPresenter
{
    private const ORDER_STATES = [
        'pending_payment' => [
            'label' => 'تم استلام طلبك',
            'detail' => 'طلبك محفوظ لدينا وهو بانتظار استكمال الدفع.',
        ],
    ];

    private const PAYMENT_STATES = [
        'pending' => [
            'label' => 'الدفع لم يكتمل بعد',
            'detail' => 'لا توجد تسوية دفع مسجّلة لهذا الطلب حتى الآن.',
        ],
    ];

    private const RESERVATION_STATES = [
        'not_reserved' => [
            'label' => 'المخزون غير محجوز حتى الآن',
            'detail' => 'لا يوجد حجز مخزون مسجّل لهذا الطلب حتى الآن.',
        ],
    ];

    private const FULFILLMENT_STATES = [
        'not_started' => [
            'label' => 'تجهيز الطلب لم يبدأ بعد',
            'detail' => 'لا توجد عملية تجهيز بدأت لهذا الطلب حتى الآن.',
        ],
    ];

    private const EVENT_TYPES = [
        'order_created' => [
            'title' => 'تم استلام الطلب',
            'detail' => 'حُفظت تفاصيل الطلب والأسعار والكميات كما كانت عند التأكيد.',
        ],
    ];

    public function present(Order $order): array
    {
        return [
            'order' => $this->state(self::ORDER_STATES, $order->order_state, 'حالة الطلب قيد المراجعة'),
            'payment' => $this->state(self::PAYMENT_STATES, $order->payment_state, 'حالة الدفع قيد المراجعة'),
            'reservation' => $this->state(self::RESERVATION_STATES, $order->reservation_state, 'حالة المخزون قيد المراجعة'),
            'fulfillment' => $this->state(self::FULFILLMENT_STATES, $order->fulfillment_state, 'حالة التجهيز قيد المراجعة'),
            'destination' => $this->destination($order),
            'timeline' => $order->events->map(fn (OrderEvent $event): array => $this->event($event))->all(),
            'next_expectation' => $this->nextExpectation($order),
        ];
    }

    private function state(array $states, string $value, string $fallback): array
    {
        return $states[$value] ?? [
            'label' => $fallback,
            'detail' => 'سنُظهر تفاصيل أوضح هنا عندما تصبح الحالة الحالية قابلة للعرض للعميل.',
        ];
    }

    private function event(OrderEvent $event): array
    {
        $copy = self::EVENT_TYPES[$event->event_type] ?? [
            'title' => 'تحديث على الطلب',
            'detail' => 'سُجّل تحديث جديد على الطلب.',
        ];

        return [
            ...$copy,
            'occurred_at' => $event->occurred_at,
        ];
    }

    private function destination(Order $order): array
    {
        $parts = array_values(array_unique(array_filter([
            trim((string) $order->delivery_city),
            trim((string) $order->delivery_district),
            trim((string) $order->delivery_region),
            (string) config('commerce.checkout.country_name_ar', 'السعودية'),
        ])));

        $firstName = trim((string) $order->customer_full_name);
        $firstName = preg_split('/\s+/u', $firstName)[0] ?? '';

        $digits = preg_replace('/\D+/', '', (string) $order->customer_phone);
        $lastFour = strlen($digits) >= 4 ? substr($digits, -4) : null;

        return [
            'recipient' => $firstName,
            'location' => implode('، ', $parts),
            'contact_hint' => $lastFour ? 'رقم التواصل المنتهي بـ '.$lastFour : null,
        ];
    }

    private function nextExpectation(Order $order): string
    {
        if (
            $order->payment_state === 'pending'
            && $order->reservation_state === 'not_reserved'
            && $order->fulfillment_state === 'not_started'
        ) {
            return 'طلبك محفوظ لدينا. لا توجد حتى الآن حقيقة جديدة عن إكمال الدفع أو حجز المخزون أو بدء التجهيز. ستتغير هذه الصفحة فقط عند تسجيل حدث فعلي.';
        }

        return 'ستعرض هذه الصفحة أي تغيير فعلي على حالة الطلب عند تسجيله، من دون افتراض خطوات لم تحدث بعد.';
    }
}
