<?php

namespace App\Http\Controllers;

use App\Commerce\OrderStatusPresenter;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function __invoke(Request $request, Order $order, OrderStatusPresenter $presenter): View
    {
        $completed = $request->session()->get('checkout.completed', []);
        $authorizedOrderIds = is_array($completed) ? array_map('intval', array_values($completed)) : [];

        abort_unless(in_array((int) $order->id, $authorizedOrderIds, true), 403);

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
