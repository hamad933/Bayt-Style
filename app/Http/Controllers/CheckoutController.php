<?php
namespace App\Http\Controllers;
use App\Commerce\CartService;
use App\Commerce\CheckoutSubmissionService;
use App\Commerce\CheckoutTotals;
use App\Commerce\Shipping\ShippingMethodProvider;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ShippingMethodProvider $shipping,
        private readonly CheckoutTotals $totals,
        private readonly CheckoutSubmissionService $submission,
    ) {}
    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->snapshot($request, true);
        if ($cart['count'] === 0) return redirect()->route('cart.index')->with('warning','السلة فارغة.');
        if ($cart['issues']) {
            return redirect()->route('cart.index')->with('warning','حدّثنا معلومات السلة. راجع التغييرات قبل متابعة إتمام الطلب.');
        }
        $country = config('commerce.checkout.country_code');
        $methods = $this->shipping->methods($country);
        $shippingCode = $methods[0]['code'] ?? null;
        abort_unless($shippingCode, 503);
        $totals = $this->totals->calculate($cart['subtotal_minor'], $shippingCode, $country, $cart['currency']);
        $token = $request->session()->get('checkout_token') ?: (string) Str::uuid();
        $request->session()->put('checkout_token', $token);
        return view('checkout.show', compact('cart','methods','shippingCode','totals','token'));
    }
    public function store(Request $request): RedirectResponse
    {
        $country = (string) config('commerce.checkout.country_code');
        $methodCodes = collect($this->shipping->methods($country))->pluck('code')->all();
        $validated = $request->validate([
            'checkout_token' => ['required','uuid'],
            'full_name' => ['required','string','min:2','max:160'],
            'email' => ['required','email:rfc','max:254'],
            'phone' => ['required','string','min:7','max:32','regex:/^\+?[0-9 ()-]+$/'],
            'country_code' => ['required', Rule::in([$country])],
            'region' => ['required','string','max:120'],
            'city' => ['required','string','max:120'],
            'district' => ['nullable','string','max:160'],
            'address_line' => ['required','string','max:255'],
            'building_unit' => ['nullable','string','max:120'],
            'postal_code' => ['nullable','string','max:24'],
            'delivery_notes' => ['nullable','string','max:1000'],
            'shipping_method' => ['required', Rule::in($methodCodes)],
            'payment_method' => ['required', Rule::in([config('commerce.checkout.payment_method_code')])],
            'terms' => ['accepted'],
        ], [
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'يجب أن يكون :attribute نصًا.',
            'min.string' => 'يجب ألا يقل :attribute عن :min أحرف.',
            'max.string' => 'يجب ألا يزيد :attribute عن :max حرفًا.',
            'email' => 'أدخل :attribute بصيغة صحيحة.',
            'regex' => 'صيغة :attribute غير صحيحة.',
            'uuid' => 'تعذّر التحقق من :attribute. أعد فتح صفحة إتمام الطلب وحاول مرة أخرى.',
            'in' => 'القيمة المختارة في :attribute غير متاحة.',
            'terms.accepted' => 'يجب الموافقة صراحةً على الشروط المعروضة قبل تأكيد الطلب.',
        ], [
            'checkout_token' => 'جلسة إتمام الطلب',
            'full_name' => 'الاسم الكامل',
            'email' => 'البريد الإلكتروني',
            'phone' => 'رقم الجوال',
            'country_code' => 'البلد',
            'region' => 'المنطقة / المحافظة',
            'city' => 'المدينة',
            'district' => 'الحي',
            'address_line' => 'العنوان',
            'building_unit' => 'المبنى / الوحدة',
            'postal_code' => 'الرمز البريدي',
            'delivery_notes' => 'ملاحظات التوصيل',
            'shipping_method' => 'طريقة التوصيل',
            'payment_method' => 'طريقة الدفع',
            'terms' => 'الموافقة على الشروط',
        ]);
        $sessionToken = (string) $request->session()->get('checkout_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, (string) $validated['checkout_token'])) {
            throw ValidationException::withMessages([
                'checkout_token' => 'انتهت جلسة إتمام الطلب أو تغيرت. أعد فتح صفحة إتمام الطلب وحاول مرة أخرى.',
            ]);
        }
        if ($existing = Order::query()->where('idempotency_key', $sessionToken)->first()) {
            $request->session()->put('checkout.completed.'.$sessionToken, $existing->id);
            return redirect()->route('checkout.confirmation', $existing);
        }
        $cart = $this->cart->snapshot($request, true);
        if ($cart['count'] === 0 || $cart['issues']) {
            return redirect()->route('cart.index')->with('warning','تغيّرت معلومات السلة أو التوفر. راجع السلة ثم أعد المتابعة.');
        }
        $totals = $this->totals->calculate(
            $cart['subtotal_minor'], $validated['shipping_method'], $country, $cart['currency']
        );
        $order = $this->submission->submit($validated, $cart, $totals);
        $this->cart->clear($request);
        $request->session()->put('checkout.completed.'.$sessionToken, $order->id);
        return redirect()->route('checkout.confirmation', $order);
    }
    public function confirmation(Request $request, Order $order): View
    {
        $completed = $request->session()->get('checkout.completed', []);
        abort_unless(in_array($order->id, array_map('intval', $completed), true), 403);
        $order->load('lines.options');
        return view('checkout.confirmation', compact('order'));
    }
}
