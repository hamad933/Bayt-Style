<?php

namespace Tests\Feature;

use App\Http\Controllers\ComparisonController;
use App\Models\Product;
use App\Models\Variant;
use App\Models\VariantAttribute;
use App\Models\VariantAttributeOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class S04S05Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_variant_option_authority_is_normalized_relational_data(): void
    {
        $this->assertFalse(Schema::hasColumn('variants', 'options'));
        $this->assertTrue(Schema::hasTable('variant_attributes'));
        $this->assertTrue(Schema::hasTable('variant_attribute_options'));
        $this->assertTrue(Schema::hasTable('variant_attribute_option_variant'));

        $color = VariantAttribute::where('code', 'color')->firstOrFail();
        $sand = VariantAttributeOption::where('variant_attribute_id', $color->id)->where('value_ar', 'رملي')->firstOrFail();
        $variant = Variant::where('sku', 'BAS-CHAIR-SAND-01')->with('attributeOptions.attribute')->firstOrFail();

        $this->assertSame(['color' => 'رملي', 'finish' => 'جوزي داكن'], $variant->optionSelection());
        $this->assertTrue($variant->attributeOptions->contains($sand));
        $this->assertDatabaseHas('variant_attribute_option_variant', ['variant_id' => $variant->id, 'variant_attribute_option_id' => $sand->id]);
    }

    public function test_product_has_multiple_real_variants_and_exact_options_resolve(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $product->load('variants.attributeOptions.attribute');
        $this->assertCount(4, $product->variants);

        $variant = $product->resolveVariant(['color' => 'رملي', 'finish' => 'جوزي داكن']);
        $this->assertInstanceOf(Variant::class, $variant);
        $this->assertSame('BAS-CHAIR-SAND-01', $variant->sku);
        $this->assertSame('2050.00', $variant->price);
        $this->assertSame('مخمل رملي · جوزي داكن', $variant->name_ar);
        $this->assertTrue($variant->isSellable());
    }

    public function test_invalid_and_unavailable_combinations_are_not_silently_mapped(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $product->load('variants.attributeOptions.attribute');
        $this->assertNull($product->resolveVariant(['color' => 'أزرق', 'finish' => 'جوزي داكن']));

        $unavailable = $product->resolveVariant(['color' => 'رملي', 'finish' => 'بلوط طبيعي']);
        $this->assertNotNull($unavailable);
        $this->assertSame('BAS-CHAIR-SAND-OAK-01', $unavailable->sku);
        $this->assertFalse($unavailable->isSellable());
        $this->postJson('/cart/items', ['variant_id' => $unavailable->id, 'quantity' => 1])->assertNotFound();
        $this->assertEmpty(session('cart', []));
    }

    public function test_add_to_cart_uses_exact_selected_variant_and_does_not_reserve_inventory(): void
    {
        $selected = Variant::where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $default = Variant::where('sku', 'BAS-CHAIR-OLV-01')->firstOrFail();
        $inventoryBefore = $selected->inventory_quantity;

        $this->postJson('/cart/items', ['variant_id' => $selected->id, 'quantity' => 2])
            ->assertCreated()->assertJsonPath('items.0.variant_id', $selected->id)
            ->assertJsonPath('items.0.sku', 'BAS-CHAIR-SAND-01')->assertJsonPath('items.0.price', '2,050');
        $this->assertSame(2, session('cart')[$selected->id]);
        $this->assertArrayNotHasKey($default->id, session('cart'));
        $this->assertSame($inventoryBefore, $selected->fresh()->inventory_quantity);
    }

    public function test_product_detail_exposes_relational_variant_data_and_unavailable_option_state(): void
    {
        $this->get('/products/olive-velvet-lounge-chair')->assertOk()
            ->assertSee('BAS-CHAIR-OLV-01')->assertSee('BAS-CHAIR-SAND-01')->assertSee('BAS-CHAIR-SAND-OAK-01')
            ->assertSee('اللون')->assertSee('تشطيب القاعدة');
    }

    public function test_multi_sellable_variant_featured_product_requires_configuration_on_home(): void
    {
        $chair = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $chair->load('variants');
        $this->assertSame(3, $chair->variants->filter->isSellable()->count());
        $this->get('/')->assertOk()->assertSee('data-testid="configure-variants-'.$chair->id.'"', false)
            ->assertSee('اختر الخيارات')->assertDontSee('data-testid="quick-add-'.$chair->id.'"', false);
    }

    public function test_single_sellable_variant_featured_product_retains_exact_quick_add(): void
    {
        $lamp = Product::where('slug', 'ceramic-table-lamp')->firstOrFail();
        $lamp->load(['variants', 'defaultVariant']);
        $this->assertSame(1, $lamp->variants->filter->isSellable()->count());
        $this->get('/')->assertOk()->assertSee('data-testid="quick-add-'.$lamp->id.'"', false)
            ->assertSee('$store.cart.add('.$lamp->defaultVariant->id.', 1)', false);
    }

    public function test_comparison_empty_state_add_duplicate_remove_and_session_persistence(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $this->get('/comparison')->assertOk()->assertSee('المقارنة فارغة');
        $this->postJson('/comparison/'.$product->id)->assertCreated()->assertJsonPath('count', 1)->assertJsonPath('ids.0', $product->id)->assertJsonPath('already_present', false);
        $this->postJson('/comparison/'.$product->id)->assertOk()->assertJsonPath('count', 1)->assertJsonPath('already_present', true);
        $this->get('/catalog')->assertOk()->assertSessionHas('comparison', [$product->id]);
        $this->deleteJson('/comparison/'.$product->id)->assertOk()->assertJsonPath('count', 0);
        $this->assertSame([], session('comparison'));
    }

    public function test_comparison_enforces_three_product_maximum(): void
    {
        $products = Product::query()->published()->orderBy('id')->limit(4)->get();
        $this->assertCount(4, $products);
        foreach ($products->take(ComparisonController::MAX_ITEMS) as $product) {
            $this->postJson('/comparison/'.$product->id)->assertCreated();
        }
        $this->postJson('/comparison/'.$products->last()->id)->assertUnprocessable()->assertJsonValidationErrors('comparison');
        $this->assertCount(ComparisonController::MAX_ITEMS, session('comparison'));
    }

    public function test_comparison_page_uses_real_product_facts_only(): void
    {
        $chair = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $lamp = Product::where('slug', 'ceramic-table-lamp')->firstOrFail();
        $this->withSession(['comparison' => [$chair->id, $lamp->id]])->get('/comparison')->assertOk()
            ->assertSee('كرسي استرخاء مخملي')->assertSee('مصباح طاولة سيراميك')->assertSee('المقاعد')->assertSee('الإضاءة')
            ->assertSee('مخمل')->assertSee('سيراميك')->assertSee('1,950')->assertSee('420');
    }

    public function test_comparison_clear_removes_session_state(): void
    {
        $ids = Product::query()->published()->orderBy('id')->limit(2)->pluck('id')->all();
        $this->withSession(['comparison' => $ids])->delete('/comparison')->assertRedirect('/comparison');
        $this->assertFalse(session()->has('comparison'));
    }

    public function test_wishlist_page_add_remove_and_persistence_are_consistent(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $this->get('/wishlist')->assertOk()->assertSee('لا توجد قطع محفوظة');
        $this->postJson('/wishlist/'.$product->id.'/toggle')->assertOk()->assertJsonPath('saved', true)->assertJsonPath('count', 1);
        $this->get('/wishlist')->assertOk()->assertSee('كرسي استرخاء مخملي')->assertSee('1,950')->assertSessionHas('wishlist', [$product->id]);
        $this->get('/products/'.$product->slug)->assertOk()->assertSee('محفوظ في المفضلة');
        $this->delete('/wishlist/'.$product->id)->assertRedirect('/wishlist');
        $this->assertSame([], session('wishlist'));
    }

    public function test_wishlist_toggle_removes_existing_product_without_authentication(): void
    {
        $product = Product::where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $this->withSession(['wishlist' => [$product->id]])->postJson('/wishlist/'.$product->id.'/toggle')
            ->assertOk()->assertJsonPath('saved', false)->assertJsonPath('count', 0);
        $this->assertSame([], session('wishlist'));
    }
}
