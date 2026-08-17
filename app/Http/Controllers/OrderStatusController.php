<?php

namespace App\Http\Controllers;

use App\Commerce\CustomerOrderAccess;
use App\Commerce\OrderStatusPresenter;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        OrderStatusPresenter $presenter,
        CustomerOrderAccess $access,
    ): View {
        $access->authorize($request, $order);

        $order->load([
            'lines.options',
            'events' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id'),
        ]);

        return view('orders.show', [
            'order' => $order,
            'status' => $presenter->present($order),
        ]);
    }
}
