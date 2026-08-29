@props(['product', 'quickAdd' => false])
@php
    $variant = $product->defaultVariant;
    $media = $product->primaryMedia;
    $saved = in_array($product->id, array_map('intval', session('wishlist', [])), true);
    $compared = in_array($product->id, array_map('intval', session('comparison', [])), true);
    $sellableVariants = $quickAdd
        ? ($product->relationLoaded('variants')
            ? $product->variants->filter(fn ($candidate) => $candidate->isSellable())
            : $product->variants()->where('is_active', true)->where('inventory_quantity', '>', 0)->get())
        : collect();
    $singleSellableVariant = $sellableVariants->count() === 1 ? $sellableVariants->first() : null;
@endphp
<article class="product-card" data-testid="product-card">
    <div class="product-image-wrap">
        <a href="{{ route('products.show', $product) }}" aria-label="عرض {{ $product->name_ar }}">
            @if($media)
                <img src="{{ asset($media->path) }}" alt="{{ $media->alt_ar }}" loading="lazy">
            @endif
        </a>
        <button
            type="button"
            class="wishlist-button"
            x-data="wishlistToggle({{ $product->id }}, {{ $saved ? 'true' : 'false' }})"
            @click="toggle"
            :aria-pressed="saved.toString()"
            :class="{ 'is-saved': saved }"
            :disabled="busy"
            :aria-busy="busy.toString()"
            :aria-label="busy ? 'جارٍ تحديث مفضلة {{ $product->name_ar }}' : (saved ? 'إزالة {{ $product->name_ar }} من المفضلة' : 'حفظ {{ $product->name_ar }} في المفضلة')"
            data-testid="wishlist-toggle"
        ><span x-text="busy ? '…' : (saved ? '♥' : '♡')">{{ $saved ? '♥' : '♡' }}</span></button>
    </div>
    <div class="product-meta">{{ $product->material_ar }} · {{ $product->room_ar }}</div>
    <h3><a href="{{ route('products.show', $product) }}">{{ $product->name_ar }}</a></h3>
    @if($variant)
        <div class="product-price"><bdi>{{ number_format((float) $variant->price, 0) }}</bdi> <span>ر.س</span></div>
    @endif
    <div class="product-card-actions">
        @if($quickAdd && $singleSellableVariant)
            <button
                type="button"
                class="product-quick-add"
                x-data="{
                    busy: false,
                    async add() {
                        if (this.busy) return;
                        this.busy = true;
                        try {
                            await this.$store.cart.add({{ $singleSellableVariant->id }}, 1);
                        } catch (error) {
                            this.$store.notice.show(error.message);
                        } finally {
                            this.busy = false;
                        }
                    }
                }"
                @click="add"
                :disabled="busy"
                :aria-busy="busy.toString()"
                x-text="busy ? 'جارٍ الإضافة…' : 'أضف إلى السلة'"
                data-testid="quick-add-{{ $product->id }}"
            >أضف إلى السلة</button>
        @elseif($quickAdd && $sellableVariants->count() > 1)
            <a
                class="product-quick-add"
                href="{{ route('products.show', $product) }}"
                data-testid="configure-variants-{{ $product->id }}"
            >اختر الخيارات</a>
        @else
            <a class="product-link" href="{{ route('products.show', $product) }}">معاينة القطعة <span aria-hidden="true">←</span></a>
        @endif
        <button
            type="button"
            class="compare-text-action"
            x-data="comparisonToggle({{ $product->id }}, {{ $compared ? 'true' : 'false' }})"
            @click="toggle"
            :aria-pressed="compared.toString()"
            :disabled="busy"
            :aria-busy="busy.toString()"
            data-testid="comparison-toggle"
        ><span x-text="busy ? 'جارٍ التحديث…' : (compared ? 'إزالة من المقارنة' : 'أضف للمقارنة')">{{ $compared ? 'إزالة من المقارنة' : 'أضف للمقارنة' }}</span></button>
    </div>
</article>
