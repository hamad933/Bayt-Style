<?php
namespace App\Http\Controllers;
use App\Commerce\CartService;
use App\Models\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}
    public function index(Request $request): View
    {
        return view('cart.index', ['cart' => $this->cart->snapshot($request, true)]);
    }
    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->cart->snapshot($request));
    }
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required','integer'],
            'quantity' => ['required','integer','min:1','max:10'],
        ]);
        $variant = Variant::query()->whereKey($validated['variant_id'])
            ->where('is_active',true)->where('inventory_quantity','>',0)->firstOrFail();
        $cart = $request->session()->get('cart', []);
        $next = (int) ($cart[$variant->id] ?? 0) + (int) $validated['quantity'];
        if ($next > 10) {
            throw ValidationException::withMessages(['quantity'=>'الحد الأقصى للكمية من القطعة الواحدة هو 10.']);
        }
        $cart[$variant->id] = $next;
        $request->session()->put('cart', $cart);
        $this->cart->rememberPrice($request, $variant);
        return response()->json($this->cart->snapshot($request), 201);
    }
    public function update(Request $request, Variant $variant): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(['quantity'=>['required','integer','min:1','max:10']]);
        $cart = $request->session()->get('cart', []);
        abort_unless(array_key_exists($variant->id, $cart), 404);
        $cart[$variant->id] = (int) $validated['quantity'];
        $request->session()->put('cart', $cart);
        if ($request->expectsJson()) return response()->json($this->cart->snapshot($request));
        return redirect()->route('cart.index')->with('status','تم تحديث الكمية.');
    }
    public function destroy(Request $request, Variant $variant): JsonResponse|RedirectResponse
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$variant->id]);
        $request->session()->put('cart', $cart);
        $this->cart->forget($request, $variant->id);
        if ($request->expectsJson()) return response()->json($this->cart->snapshot($request));
        return redirect()->route('cart.index')->with('status','تمت إزالة القطعة من السلة.');
    }
}
