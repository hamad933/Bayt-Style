<?php

namespace App\Commerce;

use App\Models\Order;
use Illuminate\Http\Request;

class CustomerOrderAccess
{
    public function authorize(Request $request, Order $order): void
    {
        $completed = $request->session()->get('checkout.completed', []);
        $authorizedOrderIds = is_array($completed) ? array_map('intval', array_values($completed)) : [];

        abort_unless(in_array((int) $order->id, $authorizedOrderIds, true), 403);
    }

    public function authorityFingerprint(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }
}
