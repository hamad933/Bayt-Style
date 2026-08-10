<form action="{{ route('catalog') }}" method="get" class="filters-form" data-testid="filters-form">
    @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif

    <div class="filter-group">
        <div class="filter-label-row"><label for="{{ isset($drawerContext) ? 'mobile-category' : 'desktop-category' }}">الفئة</label>@if(request('category'))<a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}">مسح</a>@endif</div>
        <select id="{{ isset($drawerContext) ? 'mobile-category' : 'desktop-category' }}" name="category">
            <option value="">كل الفئات</option>
            @foreach($categories as $category)
                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name_ar }}</option>
            @endforeach
        </select>
    </div>
    <div class="filter-group">
        <div class="filter-label-row"><label for="{{ isset($drawerContext) ? 'mobile-room' : 'desktop-room' }}">الغرفة</label>@if(request('room'))<a href="{{ request()->fullUrlWithQuery(['room' => null, 'page' => null]) }}">مسح</a>@endif</div>
        <select id="{{ isset($drawerContext) ? 'mobile-room' : 'desktop-room' }}" name="room">
            <option value="">كل الغرف</option>
            @foreach($rooms as $room)
                <option value="{{ $room }}" @selected(request('room') === $room)>{{ $room }}</option>
            @endforeach
        </select>
    </div>
    <div class="filter-group">
        <div class="filter-label-row"><label for="{{ isset($drawerContext) ? 'mobile-material' : 'desktop-material' }}">الخامة</label>@if(request('material'))<a href="{{ request()->fullUrlWithQuery(['material' => null, 'page' => null]) }}">مسح</a>@endif</div>
        <select id="{{ isset($drawerContext) ? 'mobile-material' : 'desktop-material' }}" name="material">
            <option value="">كل الخامات</option>
            @foreach($materials as $material)
                <option value="{{ $material }}" @selected(request('material') === $material)>{{ $material }}</option>
            @endforeach
        </select>
    </div>
    <div class="filter-group">
        <div class="filter-label-row"><label for="{{ isset($drawerContext) ? 'mobile-price' : 'desktop-price' }}">السعر</label>@if(request('price'))<a href="{{ request()->fullUrlWithQuery(['price' => null, 'page' => null]) }}">مسح</a>@endif</div>
        <select id="{{ isset($drawerContext) ? 'mobile-price' : 'desktop-price' }}" name="price">
            <option value="">كل الأسعار</option>
            <option value="under-500" @selected(request('price') === 'under-500')>أقل من 500 ر.س</option>
            <option value="500-1000" @selected(request('price') === '500-1000')>500–1,000 ر.س</option>
            <option value="over-1000" @selected(request('price') === 'over-1000')>أكثر من 1,000 ر.س</option>
        </select>
    </div>
    <div class="filter-actions">
        <button class="button button-primary" type="submit" data-testid="apply-filters">عرض النتائج</button>
        <a class="text-link" href="{{ route('catalog') }}">مسح الكل</a>
    </div>
</form>
