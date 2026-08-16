@extends('layouts.customer')

@section('title', 'بيت وأسلوب | Bayt & Style')
@section('description', 'دفء المنزل يبدأ من اختيار جميل — اكتشف أثاثًا وإضاءة وتفاصيل منزلية مختارة بعناية.')

@section('content')
<section class="home-hero">
    <div class="hero-media">
        <img src="{{ asset('images/editorial/hero.jpg') }}" alt="غرفة معيشة دافئة بأريكة فاتحة وتفاصيل زيتونية">
    </div>
    <div class="hero-copy">
        <p class="eyebrow">بيت وأسلوب · اختيار معاصر</p>
        <h1>دفء المنزل يبدأ<br>من اختيار جميل</h1>
        <p>قطع مختارة بعناية لمساحات هادئة وعملية. اكتشف أسلوبًا معاصرًا يجمع الراحة مع التفاصيل الراقية.</p>
        <div class="hero-actions-row">
            <a class="button button-primary" href="{{ route('catalog') }}">تسوق التشكيلة</a>
            <a class="text-link" href="#discovery">اكتشف الأسلوب <span aria-hidden="true">←</span></a>
        </div>
    </div>
</section>

<section class="section shell discovery-section" id="discovery">
    <div class="section-heading align-end">
        <p class="eyebrow">الاكتشاف حسب المساحة</p>
        <h2>ابدأ بما يناسب مساحتك</h2>
    </div>
    <div class="discovery-grid">
        <div class="discovery-small-stack">
            <a class="discovery-tile discovery-small-story" href="{{ route('catalog', ['category' => 'lighting']) }}">
                <div class="discovery-photo"><img src="{{ asset('images/editorial/lighting.jpg') }}" alt="تفاصيل إضاءة منزلية هادئة"></div>
                <div class="discovery-copy"><h3>إضاءة ولمسات</h3><p>أضف دفئًا وأناقة في كل زاوية.</p><span>استكشف ←</span></div>
            </a>
            <a class="discovery-tile discovery-small-story discovery-reverse" href="{{ route('catalog', ['room' => 'غرفة النوم']) }}">
                <div class="discovery-photo"><img src="{{ asset('images/editorial/bedroom.jpg') }}" alt="غرفة نوم هادئة بألوان طبيعية"></div>
                <div class="discovery-copy"><h3>النوم والهدوء</h3><p>لكل ليلة هادئة وبداية متوازنة.</p><span>استكشف ←</span></div>
            </a>
        </div>
        <a class="discovery-tile discovery-dining" href="{{ route('catalog', ['room' => 'الطعام والضيافة']) }}">
            <div class="discovery-photo"><img src="{{ asset('images/editorial/dining.jpg') }}" alt="مائدة عصرية تحت إضاءة معلقة"></div>
            <div class="discovery-copy"><h3>مائدة وضيافة</h3><p>تفاصيل تكمل لحظاتك حول المائدة.</p><span>استكشف ←</span></div>
        </a>
        <a class="discovery-tile discovery-living" href="{{ route('catalog', ['room' => 'المعيشة']) }}">
            <div class="discovery-photo"><img src="{{ asset('images/editorial/living.jpg') }}" alt="غرفة معيشة بأريكة زيتونية"></div>
            <div class="discovery-copy"><h3>جلسات ومعيشة</h3><p>مساحات تجمع العائلة والضيافة اليومية.</p><span>استكشف ←</span></div>
        </a>
    </div>
</section>

<section class="section shell seasonal" id="seasonal">
    <div class="seasonal-image"><img src="{{ asset('images/editorial/seasonal.jpg') }}" alt="تنسيق موسمي بخامات طبيعية وألوان هادئة"></div>
    <div class="seasonal-copy">
        <p class="eyebrow">تحرير الموسم</p>
        <h2>تنسيق الموسم</h2>
        <p>اكتشف قطعًا مختارة تعكس ذوق الموسم الحالي بخامات طبيعية، وألوان هادئة، وتفاصيل قليلة محسوبة.</p>
        <a class="text-link light" href="{{ route('catalog') }}">استكشف الآن <span aria-hidden="true">←</span></a>
    </div>
</section>

<section class="section shell product-shelf">
    <div class="section-heading align-end">
        <p class="eyebrow">اختيار تحريري</p>
        <h2>مختاراتنا لك</h2>
    </div>
    <div class="product-grid home-products">
        @foreach($featuredProducts as $product)
            <x-product-card :product="$product" :quick-add="true" />
        @endforeach
    </div>
</section>

<section class="service-strip" aria-label="خدمات تجربة التسوق">
    <div class="shell service-grid">
        <div><span class="service-icon" aria-hidden="true">▱</span><strong>معلومات التوصيل</strong><p>تُحدد المواعيد الفعلية حسب الوجهة والطلب.</p></div>
        <div><span class="service-icon" aria-hidden="true">↺</span><strong>سياسة الإرجاع</strong><p>تظهر الشروط المطبقة قبل إتمام الشراء.</p></div>
        <div><span class="service-icon" aria-hidden="true">◌</span><strong>معلومات المنتج</strong><p>تفاصيل واضحة عن الخامة والعناية ضمن البيانات المتاحة.</p></div>
        <div><span class="service-icon" aria-hidden="true">◇</span><strong>اختيارات مدروسة</strong><p>عرض تحريري هادئ وواضح للمنتجات.</p></div>
    </div>
</section>
@endsection
