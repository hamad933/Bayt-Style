<?php

namespace App\Commerce\Returns;

use App\Models\Order;
use App\Models\RefundRecord;
use App\Models\ReturnCase;
use App\Models\StoreCreditEntry;
use Illuminate\Support\Collection;

class ReturnExperiencePresenter
{
    private const RETURN_STATES = [
        'requested' => [
            'label' => 'تم استلام طلب المرتجع',
            'detail' => 'سُجّل طلب المرتجع وهو بانتظار المراجعة والتفويض.',
        ],
        'authorized' => [
            'label' => 'تم تفويض المرتجع',
            'detail' => 'المرتجع مفوّض الآن، ولم نسجّل استلام المنتج بعد.',
        ],
        'received' => [
            'label' => 'تم استلام المرتجع',
            'detail' => 'استلام المنتج لا يعني اكتمال الاسترداد أو عودته إلى المخزون المتاح.',
        ],
        'inspected' => [
            'label' => 'اكتمل فحص المرتجع',
            'detail' => 'تم تسجيل الفحص، وما زال قرار حالة المنتج منفصلًا عن أي استرداد مالي.',
        ],
        'disposition_decided' => [
            'label' => 'تم تحديد حالة المنتج بعد الفحص',
            'detail' => 'سُجّل قرار حالة المنتج بعد الفحص بصورة مستقلة عن الاسترداد المالي.',
        ],
        'closed' => [
            'label' => 'أُغلق سجل المرتجع',
            'detail' => 'اكتملت معالجة سجل المرتجع؛ حالة الاسترداد المالي تظهر بشكل مستقل.',
        ],
        'rejected' => [
            'label' => 'تعذر قبول المرتجع',
            'detail' => 'أُغلق طلب المرتجع دون افتراض أي استرداد مالي أو تغيير في المخزون.',
        ],
    ];

    private const REFUND_STATES = [
        'requested' => [
            'label' => 'تم تسجيل طلب استرداد',
            'detail' => 'هذا تسجيل لطلب الاسترداد فقط، ولا يعني انتقال أموال.',
        ],
        'pending' => [
            'label' => 'الاسترداد قيد المعالجة',
            'detail' => 'لا توجد تسوية مكتملة مسجّلة حتى الآن.',
        ],
        'approved' => [
            'label' => 'تمت الموافقة على الاسترداد',
            'detail' => 'الموافقة مسجّلة، لكن لا نعرض الاسترداد كمكتمل قبل وجود حدث مالي مكتمل.',
        ],
        'rejected' => [
            'label' => 'تعذر اعتماد الاسترداد',
            'detail' => 'لا توجد تسوية استرداد مكتملة لهذا السجل.',
        ],
        'completed' => [
            'label' => 'اكتمل الاسترداد',
            'detail' => 'يوجد سجل مالي صريح يؤكد اكتمال الاسترداد.',
        ],
    ];

    private const DISPOSITIONS = [
        'sellable' => 'صالح للبيع بعد الفحص',
        'damaged' => 'متضرر',
        'repair' => 'يحتاج إلى إصلاح',
        'return_to_supplier' => 'إرجاع إلى المورّد',
        'disposal' => 'استبعاد',
        'hold' => 'موقوف للمراجعة',
    ];

    private const EVENT_TYPES = [
        'return_requested' => 'تم تسجيل طلب المرتجع',
        'return_authorized' => 'تم تفويض المرتجع',
        'return_received' => 'تم استلام المرتجع',
        'return_inspected' => 'اكتمل فحص المرتجع',
        'disposition_decided' => 'تم تحديد حالة المنتج بعد الفحص',
    ];

    private const REASONS = [
        'changed_mind' => 'تغيّر الاختيار',
        'damaged_or_defective' => 'مشكلة أو تلف في المنتج',
        'different_from_order' => 'المنتج مختلف عن المطلوب',
        'other' => 'سبب آخر',
    ];

    public function present(Order $order): array
    {
        $eligibilities = $order->returnEligibilities
            ->where('state', 'active')
            ->keyBy('order_line_id');

        $casesByLine = $order->returnCases->groupBy('order_line_id');

        $lines = $order->lines->map(function ($line) use ($eligibilities, $casesByLine): array {
            $eligibility = $eligibilities->get($line->id);
            $requested = (int) $casesByLine->get($line->id, collect())->sum('requested_quantity');
            $remaining = $eligibility
                ? max(0, min((int) $eligibility->eligible_quantity, (int) $line->quantity) - $requested)
                : 0;

            return [
                'sku' => $line->variant_sku,
                'product_name' => $line->product_name,
                'variant_name' => $line->variant_name,
                'ordered_quantity' => (int) $line->quantity,
                'eligible' => $eligibility !== null && $remaining > 0,
                'eligible_quantity' => $remaining,
                'eligibility_detail' => $eligibility !== null && $remaining > 0
                    ? 'توجد أهلية مرتجع مسجّلة لهذا المنتج ويمكن بدء الطلب للكمية الموضحة.'
                    : 'لا توجد أهلية مرتجع متاحة لهذا المنتج حاليًا.',
            ];
        })->all();

        $hasEligibleLine = collect($lines)->contains(fn (array $line): bool => $line['eligible']);

        return [
            'eligibility' => [
                'available' => $hasEligibleLine,
                'label' => $hasEligibleLine ? 'يمكن بدء مرتجع لبعض منتجات الطلب' : 'طلب المرتجع غير متاح حاليًا',
                'detail' => $hasEligibleLine
                    ? 'نعرض الإجراء فقط للمنتجات التي لديها أهلية مرتجع مسجّلة بشكل صريح.'
                    : 'هذا الطلب لم يصل إلى حالة موثقة تسمح ببدء مرتجع. لن نفترض التسليم أو الأهلية قبل تسجيلهما فعليًا.',
            ],
            'lines' => $lines,
            'reasons' => self::REASONS,
            'cases' => $order->returnCases
                ->sortByDesc('requested_at')
                ->map(fn (ReturnCase $case): array => $this->returnCase($case))
                ->values()
                ->all(),
            'refunds' => $this->refunds($order->refundRecords),
            'store_credit' => $this->storeCredit($order),
        ];
    }

    private function returnCase(ReturnCase $case): array
    {
        $state = self::RETURN_STATES[$case->return_state] ?? [
            'label' => 'تحديث على المرتجع',
            'detail' => 'سُجّلت حالة للمرتجع وسيظهر وصف أوضح عند توفره.',
        ];

        $disposition = $case->inventoryDisposition
            ? (self::DISPOSITIONS[$case->inventoryDisposition->disposition] ?? 'تم تسجيل قرار بعد الفحص')
            : null;

        return [
            'reference' => $case->return_number,
            'product_name' => $case->orderLine->product_name,
            'variant_name' => $case->orderLine->variant_name,
            'quantity' => (int) $case->requested_quantity,
            'reason' => self::REASONS[$case->reason_code] ?? 'سبب مسجّل',
            'state' => $state,
            'disposition' => $disposition,
            'timeline' => $case->events
                ->sortBy([['occurred_at', 'asc'], ['id', 'asc']])
                ->map(fn ($event): array => [
                    'label' => self::EVENT_TYPES[$event->event_type] ?? 'تحديث على المرتجع',
                    'occurred_at' => $event->occurred_at,
                ])
                ->values()
                ->all(),
        ];
    }

    private function refunds(Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        return $records
            ->groupBy('refund_reference')
            ->map(function (Collection $states): array {
                /** @var RefundRecord $latest */
                $latest = $states->sortByDesc(fn (RefundRecord $record): string => $record->occurred_at->format('U.u').'-'.$record->id)->first();
                $copy = self::REFUND_STATES[$latest->refund_state] ?? [
                    'label' => 'تحديث على الاسترداد',
                    'detail' => 'توجد حالة استرداد مسجّلة دون ادعاء اكتمال مالي.',
                ];

                return [
                    'state' => $copy,
                    'amount' => (float) $latest->amount,
                    'currency' => $latest->currency,
                    'occurred_at' => $latest->occurred_at,
                ];
            })
            ->values()
            ->all();
    }

    private function storeCredit(Order $order): array
    {
        $entries = $order->storeCreditEntries
            ->sortBy([['occurred_at', 'asc'], ['id', 'asc']])
            ->values();

        if ($entries->isEmpty()) {
            return [
                'balances' => [[
                    'currency' => $order->currency,
                    'amount' => 0.0,
                ]],
                'entries' => [],
            ];
        }

        $deltas = [];
        $balances = [];

        foreach ($entries as $entry) {
            $delta = $this->creditDelta($entry, $deltas);
            $deltas[$entry->id] = $delta;
            $balances[$entry->currency] = ($balances[$entry->currency] ?? 0.0) + $delta;
        }

        return [
            'balances' => collect($balances)->map(
                fn (float $amount, string $currency): array => ['currency' => $currency, 'amount' => $amount]
            )->values()->all(),
            'entries' => $entries->map(function (StoreCreditEntry $entry) use ($deltas): array {
                return [
                    'label' => match ($entry->entry_type) {
                        'credit' => 'إضافة إلى رصيد المتجر',
                        'debit' => 'استخدام من رصيد المتجر',
                        'reversal' => 'عكس قيد سابق',
                        default => 'قيد رصيد متجر',
                    },
                    'delta' => $deltas[$entry->id] ?? 0.0,
                    'currency' => $entry->currency,
                    'occurred_at' => $entry->occurred_at,
                ];
            })->sortByDesc('occurred_at')->values()->all(),
        ];
    }

    private function creditDelta(StoreCreditEntry $entry, array $knownDeltas): float
    {
        $amount = (float) $entry->amount;

        return match ($entry->entry_type) {
            'credit' => $amount,
            'debit' => -$amount,
            'reversal' => $entry->reversal_of_entry_id !== null
                ? -($knownDeltas[$entry->reversal_of_entry_id] ?? 0.0)
                : 0.0,
            default => 0.0,
        };
    }
}
