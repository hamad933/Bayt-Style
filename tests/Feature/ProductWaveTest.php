<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_home_responds_successfully(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('دفء المنزل يبدأ')
            ->assertSee('بيت وأسلوب');
    }

    public function test_catalog_responds_successfully(): void
    {
        $this->get('/catalog')
            ->assertOk()
            ->assertSee('قطع مختارة لمساحتك')
            ->assertSee('كرسي استرخاء مخملي');
    }

    public function test_arabic_search_returns_matching_products(): void
    {
        $this->get('/catalog?q='.urlencode('كرسي'))
            ->assertOk()
            ->assertSee('كرسي استرخاء مخملي')
            ->assertSee('كرسي طعام خشبي')
            ->assertDontSee('مصباح طاولة سيراميك');
    }

    public function test_catalog_category_filtering_works(): void
    {
        $this->assertDatabaseHas('categories', ['slug' => 'lighting']);

        $this->get('/catalog?category=lighting')
            ->assertOk()
            ->assertSee('مصباح طاولة سيراميك')
            ->assertSee('وحدة إضاءة معلقة')
            ->assertDontSee('كرسي استرخاء مخملي');
    }

    public function test_catalog_sorting_by_price_ascending_works(): void
    {
        $this->get('/catalog?sort=price-asc')
            ->assertOk()
            ->assertSeeInOrder([
                'وسادة نسيج محبوك',
                'غطاء خفيف للكنبة',
                'مزهرية حجرية هادئة',
            ]);
    }

    public function test_product_slug_lookup_loads_product_detail(): void
    {
        $this->get('/products/olive-velvet-lounge-chair')
            ->assertOk()
            ->assertSee('كرسي استرخاء مخملي')
            ->assertSee('مخمل زيتوني')
            ->assertSee('1,950');
    }

    public function test_product_and_variant_are_distinct_related_models(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $variant = $product->defaultVariant;

        $this->assertInstanceOf(Variant::class, $variant);
        $this->assertNotSame($product->getTable(), $variant->getTable());
        $this->assertSame($product->id, $variant->product_id);
        $this->assertSame('BAS-CHAIR-OLV-01', $variant->sku);
    }

    public function test_add_to_cart_adds_sellable_variant_to_session(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-OLV-01')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('count', 2);

        $this->assertSame(2, session('cart')[$variant->id]);
    }

    public function test_repeated_add_to_cart_increments_existing_quantity(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-OLV-01')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('count', 3);

        $this->assertSame(3, session('cart')[$variant->id]);
    }

    public function test_cart_quantity_is_validated(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-OLV-01')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_cart_session_persists_between_authorized_routes(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-OLV-01')->firstOrFail();

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertCreated();
        $this->get('/catalog')
            ->assertOk()
            ->assertSessionHas('cart.'.$variant->id, 2);
        $this->get('/products/olive-velvet-lounge-chair')
            ->assertOk()
            ->assertSessionHas('cart.'.$variant->id, 2);
    }

    public function test_add_to_cart_does_not_reserve_or_mutate_inventory(): void
    {
        $variant = Variant::where('sku', 'BAS-CHAIR-OLV-01')->firstOrFail();
        $inventoryBefore = $variant->inventory_quantity;

        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 3])->assertCreated();

        $this->assertSame($inventoryBefore, $variant->fresh()->inventory_quantity);
    }

    public function test_guest_wishlist_toggle_persists_in_session(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();

        $this->postJson('/wishlist/'.$product->id.'/toggle')
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertSessionHas('wishlist', fn (array $ids) => in_array($product->id, array_map('intval', $ids), true));
    }

    public function test_seed_catalog_is_demo_data_and_contains_expected_relations(): void
    {
        $this->assertSame(5, Category::count());
        $this->assertSame(10, Product::count());
        $this->assertSame(13, Variant::count());
    }
}
