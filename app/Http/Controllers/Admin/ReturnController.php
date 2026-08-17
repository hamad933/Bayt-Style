<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ReturnOperationsService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnCase;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function authorizeCase(Request $request, Order $order, ReturnCase $returnCase, ReturnOperationsService $operations): RedirectResponse
    {
        return $this->transition($request, $order, $returnCase, fn (User $actor, array $data) =>
            $operations->authorize($actor, $returnCase, $data['reason'], $data['correlation_id'] ?? null), 'تم اعتماد طلب الإرجاع تشغيليًا.');
    }

    public function receive(Request $request, Order $order, ReturnCase $returnCase, ReturnOperationsService $operations): RedirectResponse
    {
        return $this->transition($request, $order, $returnCase, fn (User $actor, array $data) =>
            $operations->receive($actor, $returnCase, $data['reason'], $data['correlation_id'] ?? null), 'تم تسجيل استلام المرتجع دون إنشاء استرداد أو رصيد أو مخزون تلقائي.');
    }

    public function inspect(Request $request, Order $order, ReturnCase $returnCase, ReturnOperationsService $operations): RedirectResponse
    {
        return $this->transition($request, $order, $returnCase, fn (User $actor, array $data) =>
            $operations->inspect($actor, $returnCase, $data['inspection_outcome'], $data['reason'], $data['correlation_id'] ?? null), 'تم تسجيل الفحص كحقيقة مستقلة.');
    }

    public function decideDisposition(Request $request, Order $order, ReturnCase $returnCase, ReturnOperationsService $operations): RedirectResponse
    {
        return $this->transition($request, $order, $returnCase, fn (User $actor, array $data) =>
            $operations->decideDisposition($actor, $returnCase, $data['disposition'], $data['reason'], $data['correlation_id'] ?? null), 'تم تسجيل قرار التصرف بالمخزون دون تعديل الرصيد تلقائيًا.');
    }

    private function transition(Request $request, Order $order, ReturnCase $returnCase, callable $callback, string $status): RedirectResponse
    {
        abort_unless((int) $returnCase->order_id === (int) $order->id, 404);

        $rules = [
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
            'correlation_id' => ['nullable', 'uuid'],
        ];

        if ($request->routeIs('admin.returns.inspect')) {
            $rules['inspection_outcome'] = ['required', 'string', 'min:2', 'max:64'];
        }

        if ($request->routeIs('admin.returns.disposition')) {
            $rules['disposition'] = ['required', Rule::in(['sellable', 'damaged', 'repair', 'return_to_supplier', 'disposal', 'hold'])];
        }

        $validated = $request->validate($rules);
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->isCatalogAdmin(), 403);

        try {
            $callback($actor, $validated);
        } catch (DomainException $exception) {
            return back()->withErrors(['return_state' => $exception->getMessage()]);
        }

        return back()->with('status', $status);
    }
}
