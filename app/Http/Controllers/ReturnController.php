<?php

namespace App\Http\Controllers;

use App\Commerce\CustomerOrderAccess;
use App\Commerce\Returns\ReturnCaseService;
use App\Commerce\Returns\ReturnExperiencePresenter;
use App\Commerce\Returns\StoreCreditLedgerService;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(
        Request $request,
        Order $order,
        CustomerOrderAccess $access,
        ReturnExperiencePresenter $presenter,
        StoreCreditLedgerService $storeCreditLedger,
    ): View {
        $access->authorize($request, $order);

        $order->load([
            'lines.options',
            'returnEligibilities',
            'returnCases.orderLine',
            'returnCases.events',
            'returnCases.inventoryDisposition',
            'refundRecords',
            'storeCreditEntries',
        ]);

        $storeCreditLedger->assertProjectionIntegrity($order, $order->storeCreditEntries);

        return view('returns.index', [
            'order' => $order,
            'returns' => $presenter->present($order),
        ]);
    }

    public function store(
        Request $request,
        Order $order,
        CustomerOrderAccess $access,
        ReturnCaseService $service,
    ): RedirectResponse {
        $access->authorize($request, $order);

        $validated = $request->validate([
            'line_ref' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'reason' => [
                'required',
                'string',
                Rule::in(['changed_mind', 'damaged_or_defective', 'different_from_order', 'other']),
            ],
        ]);

        $line = $order->lines()
            ->where('variant_sku', $validated['line_ref'])
            ->firstOrFail();

        try {
            $service->request(
                $order,
                $line,
                (int) $validated['quantity'],
                $validated['reason'],
                $access->authorityFingerprint($request),
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'return' => 'تعذر بدء طلب المرتجع لأن الأهلية المسجّلة لم تعد تسمح بهذه الكمية.',
            ]);
        }

        return redirect()
            ->route('orders.returns.index', $order)
            ->with('return_notice', 'تم تسجيل طلب المرتجع. سنعرض أي تحديث فعلي هنا عند تسجيله.');
    }
}
