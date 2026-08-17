<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class S09AdminCatalogInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_guest_and_customer_cannot_access_admin_but_catalog_admin_can(): void
    {
        $this->get(route('admin.catalog.index'))->assertRedirect(route('admin.login'));

        $customer = $this->user(User::ROLE_CUSTOMER, 'customer-role@example.test');
        $this->actingAs($customer)->get(route('admin.catalog.index'))->assertForbidden();

        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'catalog-admin@example.test');
        $this->actingAs($admin)
            ->get(route('admin.catalog.index'))
            ->assertOk()
            ->assertSeeText('حقيقة الكتالوج الحالية')
            ->assertSeeText('الكتالوج والمخزون');
    }

    public function test_server_side_authorization_cannot_be_bypassed_by_posting_admin_actions(): void
    {
        $product = Product::query()->firstOrFail();
        $before = $product->name_ar;
        $customer = $this->user(User::ROLE_CUSTOMER, 'blocked@example.test');

        $this->actingAs($customer)
            ->patch(route('admin.catalog.update', $product), $this->productPayload($product, ['name_ar' => 'اسم غير مصرح']))
            ->assertForbidden();

        $this->assertSame($before, $product->fresh()->name_ar);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_product_and_variant_updates_preserve_canonical_relationships_and_options(): void
    {
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'relationships@example.test');
        $product = Product::query()->where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $variant = $product->variants()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $productId = $variant->product_id;
        $optionIds = $variant->attributeOptions()->pluck('variant_attribute_options.id')->sort()->values()->all();

        $this->actingAs($admin)
            ->patch(route('admin.catalog.update', $product), $this->productPayload($product, ['name_ar' => 'كرسي استرخاء مخملي محدث']))
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.variants.update', [$product, $variant]), [
                'sku' => $variant->sku,
                'name_ar' => 'مخمل رملي · جوزي داكن محدث',
                'price' => '2075.00',
                'is_active' => '1',
                'reason' => 'تحديث تشغيلي موثق للخيار',
                'inventory_quantity' => 999999,
                'product_id' => Product::query()->where('id', '!=', $product->id)->value('id'),
            ])
            ->assertRedirect();

        $variant->refresh();
        $this->assertSame($productId, $variant->product_id);
        $this->assertSame($optionIds, $variant->attributeOptions()->pluck('variant_attribute_options.id')->sort()->values()->all());
        $this->assertSame(18, $variant->inventory_quantity, 'Unsafe direct inventory input must be ignored by catalog updates.');
    }

    public function test_ordered_variant_cannot_be_destructively_deleted(): void
    {
        $order = $this->placeOrder();
        $variant = Variant::query()->findOrFail($order->lines()->firstOrFail()->variant_id);

        $this->expectException(LogicException::class);
        $variant->delete();
    }

    public function test_historical_order_snapshot_remains_unchanged_after_catalog_edits(): void
    {
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'snapshot@example.test');
        $order = $this->placeOrder();
        $line = $order->lines()->firstOrFail();
        $snapshot = $line->only(['product_name', 'variant_name', 'variant_sku', 'unit_price', 'quantity', 'line_total', 'currency']);
        $variant = Variant::query()->findOrFail($line->variant_id);
        $product = Product::query()->findOrFail($line->product_id);

        $this->actingAs($admin)
            ->patch(route('admin.catalog.update', $product), $this->productPayload($product, ['name_ar' => 'اسم كتالوج جديد بعد الطلب']))
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.variants.update', [$product, $variant]), [
                'sku' => 'BAS-CHAIR-SAND-NEW',
                'name_ar' => 'خيار جديد بعد الطلب',
                'price' => '2999.00',
                'is_active' => '1',
                'reason' => 'اختبار سلامة snapshot التاريخي',
            ])
            ->assertRedirect();

        $this->assertSame($snapshot, $line->fresh()->only(array_keys($snapshot)));
    }

    public function test_price_and_status_changes_create_durable_audit_evidence(): void
    {
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'audit@example.test');
        $product = Product::query()->where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $variant = $product->variants()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.variants.update', [$product, $variant]), [
                'sku' => $variant->sku,
                'name_ar' => $variant->name_ar,
                'price' => '2222.00',
                'reason' => 'تغيير سعر وحالة موثق للاختبار',
            ])
            ->assertRedirect();

        $log = AdminAuditLog::query()->where('action', 'catalog.variant.updated')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->actor_user_id);
        $this->assertSame($admin->email, $log->actor_identifier);
        $this->assertSame('variant', $log->entity_type);
        $this->assertSame($variant->id, $log->entity_id);
        $this->assertSame('2050.00', $log->before_values['price']);
        $this->assertSame('2222.00', $log->after_values['price']);
        $this->assertTrue($log->before_values['is_active']);
        $this->assertFalse($log->after_values['is_active']);
        $this->assertNotEmpty($log->correlation_id);

        $this->actingAs($admin)
            ->patch(route('admin.catalog.update', $product), $this->productPayload($product, ['status' => 'draft']))
            ->assertRedirect();

        $productLog = AdminAuditLog::query()->where('action', 'catalog.product.updated')->latest('id')->firstOrFail();
        $this->assertSame('published', $productLog->before_values['status']);
        $this->assertSame('draft', $productLog->after_values['status']);
    }

    public function test_inventory_adjustment_creates_typed_movement_audit_and_reconciled_projection(): void
    {
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'inventory@example.test');
        $product = Product::query()->where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $variant = $product->variants()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $before = $variant->inventory_quantity;
        $opening = $variant->inventoryMovements()->where('movement_type', 'opening_balance')->firstOrFail();
        $this->assertSame($before, $opening->quantity_after);

        $this->actingAs($admin)
            ->post(route('admin.inventory.adjust', [$product, $variant]), [
                'quantity_delta' => 7,
                'reason' => 'تصحيح جرد موثق للاختبار',
            ])
            ->assertRedirect();

        $variant->refresh();
        $this->assertSame($before + 7, $variant->inventory_quantity);

        $movement = InventoryMovement::query()->where('movement_type', 'admin_adjustment')->latest('id')->firstOrFail();
        $this->assertSame($variant->id, $movement->variant_id);
        $this->assertSame(7, $movement->quantity_delta);
        $this->assertSame($before, $movement->quantity_before);
        $this->assertSame($before + 7, $movement->quantity_after);
        $this->assertSame($admin->id, $movement->actor_user_id);
        $this->assertSame($admin->email, $movement->actor_identifier);

        $audit = AdminAuditLog::query()->where('action', 'inventory.variant.adjusted')->latest('id')->firstOrFail();
        $this->assertSame($movement->correlation_id, $audit->correlation_id);
        $this->assertSame($before, $audit->before_values['inventory_quantity']);
        $this->assertSame($before + 7, $audit->after_values['inventory_quantity']);
    }

    public function test_invalid_inventory_adjustment_fails_atomically(): void
    {
        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'atomic@example.test');
        $product = Product::query()->where('slug', 'olive-velvet-lounge-chair')->firstOrFail();
        $variant = $product->variants()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $before = $variant->inventory_quantity;
        $movementCount = InventoryMovement::query()->count();
        $auditCount = AdminAuditLog::query()->count();

        $this->actingAs($admin)
            ->from(route('admin.catalog.edit', $product))
            ->post(route('admin.inventory.adjust', [$product, $variant]), [
                'quantity_delta' => -($before + 1),
                'reason' => 'محاولة رصيد سالب يجب رفضها',
            ])
            ->assertRedirect(route('admin.catalog.edit', $product))
            ->assertSessionHasErrors('quantity_delta');

        $this->assertSame($before, $variant->fresh()->inventory_quantity);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame($auditCount, AdminAuditLog::query()->count());
    }

    public function test_append_only_inventory_and_audit_records_reject_operator_mutation(): void
    {
        $movement = InventoryMovement::query()->firstOrFail();
        $movement->reason = 'rewrite';

        try {
            $movement->save();
            $this->fail('Inventory movement update should have been rejected.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $admin = $this->user(User::ROLE_CATALOG_ADMIN, 'append-only@example.test');
        $product = Product::query()->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('admin.catalog.update', $product), $this->productPayload($product, ['name_ar' => $product->name_ar.' محدث']))
            ->assertRedirect();

        $audit = AdminAuditLog::query()->firstOrFail();
        $this->expectException(LogicException::class);
        $audit->delete();
    }

    public function test_direct_unsafe_inventory_projection_mutation_is_rejected(): void
    {
        $variant = Variant::query()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();
        $before = $variant->inventory_quantity;

        try {
            $variant->inventory_quantity = $before + 50;
            $variant->save();
            $this->fail('Direct inventory projection update should have been rejected.');
        } catch (LogicException) {
            $this->assertSame($before, $variant->fresh()->inventory_quantity);
        }
    }

    public function test_s01_to_s08_customer_surfaces_remain_available_after_s09(): void
    {
        $product = Product::query()->published()->whereHas('defaultVariant')->firstOrFail();

        $this->get(route('home'))->assertOk();
        $this->get(route('catalog'))->assertOk();
        $this->get(route('products.show', $product))->assertOk();
        $this->get(route('cart.index'))->assertOk();
    }

    private function user(string $role, string $email): User
    {
        return User::query()->create([
            'name' => 'هوية اختبار',
            'email' => $email,
            'password' => bin2hex(random_bytes(24)),
            'role' => $role,
        ]);
    }

    private function productPayload(Product $product, array $overrides = []): array
    {
        $payload = [
            'category_id' => $product->category_id,
            'name_ar' => $product->name_ar,
            'slug' => $product->slug,
            'short_description_ar' => $product->short_description_ar,
            'description_ar' => $product->description_ar,
            'details_ar' => $product->details_ar,
            'material_ar' => $product->material_ar,
            'room_ar' => $product->room_ar,
            'is_featured' => $product->is_featured ? '1' : '0',
            'status' => $product->published_at && $product->published_at->lte(now()) ? 'published' : 'draft',
            'reason' => 'تغيير كتالوج موثق للاختبار',
        ];

        return array_merge($payload, $overrides);
    }

    private function placeOrder(): Order
    {
        $variant = Variant::query()->where('sku', 'BAS-CHAIR-SAND-01')->firstOrFail();

        $this->postJson('/cart/items', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->get('/checkout')->assertOk();
        $token = (string) session('checkout_token');

        $this->post('/checkout', [
            'checkout_token' => $token,
            'full_name' => 'عميل تجريبي',
            'email' => 'customer@example.test',
            'phone' => '+966500000001',
            'country_code' => 'SA',
            'region' => 'الرياض',
            'city' => 'الرياض',
            'district' => 'حي تجريبي',
            'address_line' => 'شارع تطويري 10',
            'building_unit' => 'مبنى 2',
            'postal_code' => '00000',
            'delivery_notes' => 'بيانات اختبار فقط',
            'shipping_method' => 'demo_standard',
            'payment_method' => 'manual_pending_demo',
            'terms' => '1',
        ])->assertRedirect();

        return Order::query()->latest('id')->firstOrFail();
    }
}
