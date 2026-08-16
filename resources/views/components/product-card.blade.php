@props(['product', 'quickAdd' => false])
@php
    $variant = $product->defaultVariant;
    $media = $product->primaryMedia;
    $saved = in_array($product->id, array_map('intval', session('wishlist', [])), true);
    $compared = in_array($product->id, array_map('intval', session('comparison', [])), true);
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
            :aria-label="saved ? 'إزالة {{ $product->name_ar }} من المفضلة' : 'حفظ {{ $product->name_ar }} في المفضلة'"
            data-testid="wishlist-toggle"
        ><span x-text="saved ? '♥' : '♡'">{{ $saved ? '♥' : '♡' }}</span></button>
    </div>
    <div class="product-meta">{{ $product->material_ar }} · {{ $product->room_ar }}</div>
    <h3><a href="{{ route('products.show', $product) }}">{{ $product->name_ar }}</a></h3>
    @if($variant)
        <div class="product-price"><bdi>{{ number_format((float) $variant->price, 0) }}</bdi> <span>ر.س</span></div>
    @endif
    <div class="product-card-actions">
        @if($quickAdd && $variant)
            <button type="button" class="product-quick-add" @click="$store.cart.add({{ $variant->id }}, 1)">أضف إلى السلة</button>
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
            data-testid="comparison-toggle"
        ><span x-text="compared ? 'إزالة من المقارنة' : 'أضف للمقارنة'">{{ $compared ? 'إزالة من المقارنة' : 'أضف للمقارنة' }}</span></button>
    </div>
</article>
