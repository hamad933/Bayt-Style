<?php

namespace App\Http\Controllers\Admin;

use App\Admin\OrderOperationsService;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $state = trim((string) $request->query('state', 'all'));
        $allowedStates = ['all', 'pending_payment', 'cancelled'];
        if (! in_array($state, $allowedStates, true)) {
            $state = 'all';
        }

        $orders = Order::query()
            ->withCount(['returnCases', 'refundRecords', 'storeCreditEntries'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('order_number', 'ilike', '%'.$search.'%')
                        ->orWhere('customer_full_name', 'ilike', '%'.$search.'%')
                        ->orWhere('customer_email', 'ilike', '%'.$search.'%')
                        ->orWhere('customer_phone', 'ilike', '%'.$search.'%');
                });
            })
            ->when($state !== 'all', fn ($query) => $query->where('order_state', $state))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'search', 'state'));
    }

    public function show(Order $order): View
    {
        $order->load([
            'lines.options',
            'events' => fn ($query) => $query->orderByDesc('occurred_at')->orderByDesc('id'),
            'returnCases' => fn ($query) => $query->with([
                'orderLine',
                'events' => fn ($events) => $events->orderByDesc('occurred_at')->orderByDesc('id'),
                'inspection',
                'inventoryDisposition',
                'refundRecords',
                'storeCreditEntries',
            ])->orderByDesc('requested_at'),
            'refundRecords' => fn ($query) => $query->orderByDesc('occurred_at')->orderByDesc('id'),
            'storeCreditEntries' => fn ($query) => $query->orderByDesc('occurred_at')->orderByDesc('id'),
        ]);

        $returnIds = $order->returnCases->pluck('id');
        $auditLogs = AdminAuditLog::query()
            ->where(function ($query) use ($order, $returnIds): void {
                $query->where(function ($orders) use ($order): void {
                    $orders->where('entity_type', 'order')->where('entity_id', $order->id);
                });
                if ($returnIds->isNotEmpty()) {
                    $query->orWhere(function ($returns) use ($returnIds): void {
                        $returns->where('entity_type', 'return_case')->whereIn('entity_id', $returnIds);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        return view('admin.orders.show', compact('order', 'auditLogs'));
    }

    public function cancel(Request $request, Order $order, OrderOperationsService $operations): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
            'correlation_id' => ['nullable', 'uuid'],
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isCatalogAdmin(), 403);

        try {
            $operations->cancelPendingOrder($actor, $order, $validated['reason'], $validated['correlation_id'] ?? null);
        } catch (DomainException $exception) {
            return back()->withErrors(['order_state' => $exception->getMessage()]);
        }

        return back()->with('status', 'تم إلغاء الطلب المعلّق دون تغيير حقيقة الدفع أو التنفيذ.');
    }
}
