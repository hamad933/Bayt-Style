@extends('layouts.admin')

@section('title', $product->name_ar.' | إدارة بيت وأسلوب')
@section('page-title', 'إدارة المنتج')

@section('content')
<div class="admin-breadcrumbs">
    <a href="{{ route('admin.catalog.index') }}">الكتالوج والمخزون</a>
    <span aria-hidden="true">/</span>
    <span>{{ $product->name_ar }}</span>
</div>

<section class="admin-page-head admin-page-head--detail">
    <div>
        <p class="admin-eyebrow">Product #{{ $product->id }}</p>
        <h1>{{ $product->name_ar }}</h1>
        <p>التعديلات هنا تغيّر حقيقة الكتالوج الحالية فقط، ولا تعيد كتابة لقطات بيانات الطلبات التاريخية.</p>
    </div>
    <a class="admin-secondary-button" href="{{ route('products.show', $product) }}" target="_blank" rel="noopener">معاينة المتجر</a>
</section>

<section class="admin-panel" aria-labelledby="product-data-title">
    <div class="admin-panel__head">
        <div>
            <p class="admin-eyebrow">Product</p>
            <h2 id="product-data-title">بيانات العرض والتصنيف</h2>
        </div>
        <span class="admin-status {{ $product->published_at && $product->published_at->lte(now()) ? 'is-positive' : 'is-muted' }}">
            {{ $product->published_at && $product->published_at->lte(now()) ? 'منشور' : 'مسودة' }}
        </span>
    </div>

    <form class="admin-form admin-form--grid" method="post" action="{{ route('admin.catalog.update', $product) }}">
        @csrf
        @method('PATCH')
        <label class="admin-field admin-field--wide">
            <span>الاسم العربي</span>
            <input name="name_ar" value="{{ old('name_ar', $product->name_ar) }}" required maxlength="200">
        </label>
        <label class="admin-field">
            <span>التصنيف</span>
            <select name="category_id" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name_ar }}</option>
                @endforeach
            </select>
        </label>
        <label class="admin-field">
            <span>حالة النشر</span>
            <select name="status" required>
                <option value="published" @selected(old('status', $product->published_at && $product->published_at->lte(now()) ? 'published' : 'draft') === 'published')>منشور</option>
                <option value="draft" @selected(old('status', $product->published_at && $product->published_at->lte(now()) ? 'published' : 'draft') === 'draft')>مسودة</option>
            </select>
        </label>
        <label class="admin-field admin-field--wide">
            <span>الرابط المختصر</span>
            <input dir="ltr" name="slug" value="{{ old('slug', $product->slug) }}" required maxlength="180">
        </label>
        <label class="admin-field admin-field--wide">
            <span>الوصف المختصر</span>
            <textarea name="short_description_ar" rows="2" required maxlength="500">{{ old('short_description_ar', $product->short_description_ar) }}</textarea>
        </label>
        <label class="admin-field admin-field--wide">
            <span>الوصف</span>
            <textarea name="description_ar" rows="4" required maxlength="4000">{{ old('description_ar', $product->description_ar) }}</textarea>
        </label>
        <label class="admin-field admin-field--wide">
            <span>التفاصيل</span>
            <textarea name="details_ar" rows="3" maxlength="4000">{{ old('details_ar', $product->details_ar) }}</textarea>
        </label>
        <label class="admin-field">
            <span>الخامة</span>
            <input name="material_ar" value="{{ old('material_ar', $product->material_ar) }}" maxlength="160">
        </label>
        <label class="admin-field">
            <span>الغرفة</span>
            <input name="room_ar" value="{{ old('room_ar', $product->room_ar) }}" maxlength="160">
        </label>
        <label class="admin-checkbox">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
            <span>منتج مميز</span>
        </label>
        <label class="admin-field admin-field--wide">
            <span>سبب التغيير <small>مطلوب للتدقيق</small></span>
            <input name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="500" placeholder="مثال: تحديث وصف الكتالوج المعتمد">
        </label>
        <div class="admin-form-actions admin-field--wide">
            <button class="admin-primary-button" type="submit">حفظ المنتج وتسجيل الأثر</button>
        </div>
    </form>
</section>

<section class="admin-panel" aria-labelledby="variants-title">
    <div class="admin-panel__head">
        <div>
            <p class="admin-eyebrow">Product → Variant</p>
            <h2 id="variants-title">خيارات البيع والمخزون</h2>
            <p>لا يوجد مسار إداري لتعديل كمية المخزون مباشرة. كل تعديل مخزون أدناه ينشئ حركة مخزون مهيكلة وسجل تدقيق قبل تحديث الرصيد التشغيلي الحالي.</p>
        </div>
        <span class="admin-panel__meta">{{ $product->variants->count() }} خيارات</span>
    </div>

    <div class="admin-variant-list">
        @foreach ($product->variants->sortByDesc('is_default') as $variant)
            <article class="admin-variant" data-testid="variant-{{ $variant->id }}">
                <header class="admin-variant__head">
                    <div>
                        <div class="admin-variant__titleline">
                            <h3>{{ $variant->name_ar }}</h3>
                            @if ($variant->is_default)<span class="admin-status is-info">افتراضي</span>@endif
                            <span class="admin-status {{ $variant->is_active ? 'is-positive' : 'is-muted' }}">{{ $variant->is_active ? 'نشط' : 'غير نشط' }}</span>
                        </div>
                        <p dir="ltr">{{ $variant->sku }}</p>
                    </div>
                    <div class="admin-inventory-balance">
                        <strong>{{ number_format($variant->inventory_quantity) }}</strong>
                        <span>الرصيد الحالي</span>
                    </div>
                </header>

                <div class="admin-variant__options">
                    <span>التكوين الحالي</span>
                    @forelse ($variant->optionSelection() as $attribute => $value)
                        <strong><bdi dir="ltr">{{ $attribute }}</bdi>: {{ $value }}</strong>
                    @empty
                        <strong>لا توجد خصائص إضافية.</strong>
                    @endforelse
                </div>

                <div class="admin-variant__forms">
                    <form class="admin-form admin-form--compact" method="post" action="{{ route('admin.variants.update', [$product, $variant]) }}">
                        @csrf
                        @method('PATCH')
                        <h4>بيانات البيع</h4>
                        <label class="admin-field">
                            <span>SKU</span>
                            <input dir="ltr" name="sku" value="{{ $variant->sku }}" required maxlength="80">
                        </label>
                        <label class="admin-field">
                            <span>اسم الخيار</span>
                            <input name="name_ar" value="{{ $variant->name_ar }}" required maxlength="160">
                        </label>
                        <label class="admin-field">
                            <span>السعر ({{ $variant->currency }})</span>
                            <input dir="ltr" type="number" name="price" min="0" max="99999999.99" step="0.01" value="{{ $variant->price }}" required>
                        </label>
                        <label class="admin-checkbox">
                            <input type="checkbox" name="is_active" value="1" @checked($variant->is_active)>
                            <span>الخيار نشط للبيع</span>
                        </label>
                        <label class="admin-field admin-field--wide">
                            <span>سبب التغيير</span>
                            <input name="reason" required minlength="5" maxlength="500" placeholder="سبب تشغيلي واضح">
                        </label>
                        <button class="admin-secondary-button" type="submit">حفظ بيانات الخيار</button>
                    </form>

                    <form class="admin-form admin-form--inventory" method="post" action="{{ route('admin.inventory.adjust', [$product, $variant]) }}">
                        @csrf
                        <h4>تعديل مخزون موثّق</h4>
                        <p class="admin-help">استخدم قيمة موجبة للإضافة وسالبة للتخفيض. يمنع النظام أي نتيجة سالبة.</p>
                        <label class="admin-field">
                            <span>التغيير في الكمية</span>
                            <input dir="ltr" type="number" name="quantity_delta" min="-1000000" max="1000000" step="1" required placeholder="مثال: 5 أو -2">
                        </label>
                        <label class="admin-field admin-field--wide">
                            <span>سبب التعديل</span>
                            <input name="reason" required minlength="5" maxlength="500" placeholder="مثال: جرد تشغيلي موثّق">
                        </label>
                        <button class="admin-primary-button" type="submit">تسجيل حركة المخزون</button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
</section>

<section class="admin-panel" aria-labelledby="media-title">
    <div class="admin-panel__head">
        <div>
            <p class="admin-eyebrow">Catalog Media</p>
            <h2 id="media-title">مرجع الوسائط الحالي</h2>
            <p>الوسائط المعروضة هي الحقيقة الموجودة في التطبيق؛ ولا يضيف هذا السطح نظام إدارة ملفات جديدًا.</p>
        </div>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table admin-table--compact">
            <thead><tr><th>الترتيب</th><th>المسار</th><th>النص البديل</th></tr></thead>
            <tbody>
            @forelse ($product->media as $media)
                <tr><td>{{ $media->sort_order }}</td><td><span dir="ltr">{{ $media->path }}</span></td><td>{{ $media->alt_ar }}</td></tr>
            @empty
                <tr><td colspan="3" class="admin-empty">لا توجد وسائط مرتبطة.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="admin-two-column">
    <section class="admin-panel" aria-labelledby="movement-title">
        <div class="admin-panel__head"><div><p class="admin-eyebrow">Inventory Movement</p><h2 id="movement-title">سجل حركة المخزون</h2></div></div>
        <div class="admin-history-list">
            @forelse ($movements as $movement)
                <article class="admin-history-item">
                    <div><strong dir="ltr">{{ $movement->variant?->sku }}</strong><span>{{ $movement->movement_type === 'opening_balance' ? 'رصيد افتتاحي' : 'تعديل إداري' }}</span></div>
                    <div class="admin-history-quantity" dir="ltr">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }} · {{ $movement->quantity_before }} → {{ $movement->quantity_after }}</div>
                    <p>{{ $movement->reason }}</p>
                    <small>{{ $movement->actor_identifier }} · <bdi dir="ltr">{{ $movement->correlation_id }}</bdi></small>
                </article>
            @empty
                <p class="admin-empty">لا توجد حركات مخزون.</p>
            @endforelse
        </div>
    </section>

    <section class="admin-panel" aria-labelledby="audit-title">
        <div class="admin-panel__head"><div><p class="admin-eyebrow">Audit</p><h2 id="audit-title">سجل التدقيق</h2></div></div>
        <div class="admin-history-list">
            @forelse ($auditLogs as $log)
                <article class="admin-history-item">
                    <div><strong dir="ltr">{{ $log->action }}</strong><span>{{ $log->entity_type }} #{{ $log->entity_id }}</span></div>
                    <p>{{ $log->reason }}</p>
                    <small>{{ $log->actor_identifier }} · <bdi dir="ltr">{{ $log->correlation_id }}</bdi></small>
                </article>
            @empty
                <p class="admin-empty">لا توجد تغييرات حساسة بعد.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
