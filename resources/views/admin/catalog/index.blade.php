@extends('layouts.admin')

@section('title', 'الكتالوج والمخزون | إدارة بيت وأسلوب')
@section('page-title', 'الكتالوج والمخزون')

@section('content')
<section class="admin-page-head">
    <div>
        <p class="admin-eyebrow">إدارة الكتالوج</p>
        <h1>حقيقة الكتالوج الحالية</h1>
        <p>المنتج هو كيان العرض، والخيار البيعي هو الوحدة الفعلية التي تحمل SKU والسعر وحالة التوفر.</p>
    </div>
    <div class="admin-page-stat" aria-label="عدد المنتجات">
        <strong>{{ number_format($products->total()) }}</strong>
        <span>منتج ضمن النتيجة</span>
    </div>
</section>

<form class="admin-filterbar" method="get" action="{{ route('admin.catalog.index') }}" role="search">
    <label class="admin-field admin-field--search">
        <span>بحث</span>
        <input type="search" name="q" value="{{ $search }}" placeholder="اسم المنتج، الرابط، SKU أو اسم الخيار">
    </label>
    <label class="admin-field admin-field--compact">
        <span>حالة النشر</span>
        <select name="status">
            <option value="all" @selected($status === 'all')>الكل</option>
            <option value="published" @selected($status === 'published')>منشور</option>
            <option value="draft" @selected($status === 'draft')>مسودة</option>
        </select>
    </label>
    <button class="admin-secondary-button" type="submit">تطبيق</button>
    @if ($search !== '' || $status !== 'all')
        <a class="admin-text-link" href="{{ route('admin.catalog.index') }}">مسح الفلاتر</a>
    @endif
</form>

<div class="admin-table-wrap" data-testid="catalog-table-wrap" style="width: 100%; min-width: 0; contain: inline-size; direction: ltr;">
    <table class="admin-table" style="direction: rtl;">
        <thead>
        <tr>
            <th>المنتج</th>
            <th>التصنيف</th>
            <th>النشر</th>
            <th>الخيارات</th>
            <th>SKU</th>
            <th>رصيد المخزون</th>
            <th><span class="sr-only">إجراء</span></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($products as $product)
            @php($published = $product->published_at && $product->published_at->lte(now()))
            <tr>
                <td>
                    <strong class="admin-table__primary">{{ $product->name_ar }}</strong>
                    <span class="admin-table__secondary" dir="ltr">{{ $product->slug }}</span>
                </td>
                <td>{{ $product->category?->name_ar ?? '—' }}</td>
                <td><span class="admin-status {{ $published ? 'is-positive' : 'is-muted' }}">{{ $published ? 'منشور' : 'مسودة' }}</span></td>
                <td>{{ $product->variants_count }}</td>
                <td>
                    @foreach ($product->variants->sortByDesc('is_default') as $variant)
                        <span class="admin-table__primary" dir="ltr">{{ $variant->sku }}</span>
                    @endforeach
                </td>
                <td>{{ number_format((int) ($product->variants_sum_inventory_quantity ?? 0)) }}</td>
                <td><a class="admin-row-action" href="{{ route('admin.catalog.edit', $product) }}">إدارة</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="admin-empty">لا توجد نتائج مطابقة.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if ($products->hasPages())
    <nav class="admin-pagination" aria-label="صفحات الكتالوج">
        @if ($products->onFirstPage())
            <span aria-disabled="true">السابق</span>
        @else
            <a href="{{ $products->previousPageUrl() }}">السابق</a>
        @endif
        <span>صفحة {{ $products->currentPage() }} من {{ $products->lastPage() }}</span>
        @if ($products->hasMorePages())
            <a href="{{ $products->nextPageUrl() }}">التالي</a>
        @else
            <span aria-disabled="true">التالي</span>
        @endif
    </nav>
@endif
@endsection
