@php($cartCount = collect(session('cart', []))->sum())
<header class="site-header" x-data="headerShell" @close-shell-ui.window="if (mobileOpen) { mobileOpen = false; $nextTick(() => $refs.mobileMenuTrigger && $refs.mobileMenuTrigger.focus()) } else { mobileOpen = false }; loginOpen = false">
    <div class="header-inner shell">
        <a href="{{ route('home') }}" class="brand" aria-label="بيت وأسلوب — الصفحة الرئيسية">
            <span class="brand-ar">بيت وأسلوب</span>
            <span class="brand-en" dir="ltr">Bayt & Style</span>
        </a>
        <nav class="desktop-nav" aria-label="التنقل الرئيسي">
            <a href="{{ route('catalog') }}?category=seating">المجموعات</a>
            <a href="{{ route('catalog') }}?room={{ urlencode('المعيشة') }}">الغرف</a>
            <a href="{{ route('catalog') }}" @class(['is-active' => request()->routeIs('catalog') || request()->routeIs('products.show')])>المنتجات</a>
            <a href="{{ route('home') }}#seasonal">الإلهام</a>
            <a href="#customer-service">خدمات العملاء</a>
        </nav>
        <form class="header-search" action="{{ route('catalog') }}" method="get" role="search">
            <label class="sr-only" for="header-search">ابحث في المنتجات</label>
            <input id="header-search" type="search" name="q" value="{{ request('q') }}" placeholder="ابحث في المنتجات">
            <button type="submit" aria-label="بحث">⌕</button>
        </form>
        <div class="header-actions">
            <a class="commerce-link" href="{{ route('wishlist.index') }}" aria-label="المفضلة">
                <span aria-hidden="true">♡</span><span class="commerce-count" x-text="$store.wishlist.count">{{ count(array_unique(array_map('intval', session('wishlist', [])))) }}</span>
            </a>
            <a class="commerce-link" href="{{ route('comparison.index') }}" aria-label="المقارنة">
                <span aria-hidden="true">≍</span><span class="commerce-count" x-text="$store.comparison.count">{{ count(array_unique(array_map('intval', session('comparison', [])))) }}</span>
            </a>
            <button class="text-action desktop-only" type="button" @click="loginOpen = true" :aria-expanded="loginOpen.toString()" aria-controls="login-dialog">تسجيل الدخول</button>
            <button class="icon-action" type="button" @click="$store.cart.openDrawer()" :aria-expanded="$store.cart.open.toString()" aria-controls="cart-drawer" aria-label="فتح السلة">
                <span aria-hidden="true">◯</span><span class="cart-badge" x-text="$store.cart.count">{{ $cartCount }}</span>
            </button>
            <button class="menu-toggle" type="button" x-ref="mobileMenuTrigger" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-controls="mobile-nav" aria-label="فتح القائمة">☰</button>
        </div>
    </div>

    <div id="mobile-nav" class="mobile-nav" x-show="mobileOpen" x-transition x-cloak>
        <nav class="shell" aria-label="تنقل الجوال">
            <a href="{{ route('catalog') }}">المنتجات</a>
            <a href="{{ route('catalog') }}?category=seating">المجموعات</a>
            <a href="{{ route('catalog') }}?room={{ urlencode('المعيشة') }}">الغرف</a>
            <a href="{{ route('wishlist.index') }}">المفضلة <span x-text="'(' + $store.wishlist.count + ')' "></span></a>
            <a href="{{ route('comparison.index') }}">المقارنة <span x-text="'(' + $store.comparison.count + ')' "></span></a>
            <a href="{{ route('cart.index') }}">السلة <span x-text="'(' + $store.cart.count + ')' "></span></a>
            <a href="{{ route('home') }}#seasonal">الإلهام</a>
            <a href="#customer-service">خدمات العملاء</a>
            <button type="button" @click="$refs.mobileMenuTrigger.focus(); mobileOpen = false; loginOpen = true">تسجيل الدخول</button>
        </nav>
    </div>

    <div class="dialog-backdrop" x-show="loginOpen" x-transition.opacity x-cloak @click.self="loginOpen = false">
        <section id="login-dialog" class="dialog-panel" role="dialog" aria-modal="true" aria-labelledby="login-title" x-ref="loginDialog" @keydown.tab="trapFocus($event, $refs.loginDialog)">
            <button class="dialog-close" type="button" @click="loginOpen = false" aria-label="إغلاق">×</button>
            <p class="eyebrow">الحساب</p><h2 id="login-title">تسجيل الدخول</h2>
            <p>تسجيل الدخول غير متاح حاليًا. يمكنك متابعة استكشاف المنتجات وإدارة السلة والمفضلة كزائر.</p>
            <button class="button button-ghost" type="button" @click="loginOpen = false">متابعة كزائر</button>
        </section>
    </div>

    <div class="drawer-backdrop" x-show="$store.cart.open" x-transition.opacity x-cloak @click.self="$store.cart.closeDrawer()">
        <aside id="cart-drawer" class="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="cart-title" x-ref="cartDrawer" x-effect="$store.cart.open && $nextTick(() => $refs.cartDrawer?.querySelector('button')?.focus())" @keydown.tab="$store.cart.trapFocus($event, $el)">
            <div class="drawer-head">
                <div><p class="eyebrow">السلة</p><h2 id="cart-title">مختاراتك الحالية</h2></div>
                <button type="button" @click="$store.cart.closeDrawer()" aria-label="إغلاق السلة">×</button>
            </div>
            <template x-if="$store.cart.loading"><p class="muted" role="status" aria-live="polite" aria-atomic="true">جارٍ تحديث السلة…</p></template>
            <template x-if="!$store.cart.loading && $store.cart.items.length === 0"><p class="empty-note">لم تضف أي قطعة بعد.</p></template>
            <div class="cart-lines" x-show="$store.cart.items.length">
                <template x-for="item in $store.cart.items" :key="item.variant_id">
                    <article class="cart-line">
                        <img x-show="item.image" :src="item.image" :alt="item.product">
                        <div class="cart-line-copy">
                            <strong x-text="item.product"></strong>
                            <span x-text="item.variant"></span>
                            <small dir="ltr" x-text="item.sku"></small>
                            <span><bdi x-text="item.price"></bdi> ر.س</span>
                            <div class="mini-qty" role="group" :aria-label="`كمية ${item.product}`">
                                <button type="button" @click="$store.cart.setQuantity(item.variant_id, item.quantity - 1)" :disabled="item.quantity <= 1" :aria-label="`تقليل كمية ${item.product}`">−</button>
                                <span x-text="item.quantity"></span>
                                <button type="button" @click="$store.cart.setQuantity(item.variant_id, item.quantity + 1)" :disabled="item.quantity >= 10" :aria-label="`زيادة كمية ${item.product}`">+</button>
                            </div>
                            <button class="remove-line" type="button" @click="$store.cart.remove(item.variant_id)" :aria-label="`إزالة ${item.product} من السلة`">إزالة</button>
                        </div>
                    </article>
                </template>
            </div>
            <div class="cart-total" x-show="$store.cart.items.length">
                <span>المجموع الحالي</span><strong><bdi x-text="$store.cart.total"></bdi> ر.س</strong>
            </div>
            <div x-show="$store.cart.items.length">
                <a class="button button-primary" href="{{ route('cart.index') }}" @click="$store.cart.closeDrawer()">عرض السلة وإتمام الطلب</a>
            </div>
            <p class="drawer-boundary">إضافة المنتجات إلى السلة لا تنشئ طلبًا ولا تحجز المخزون. نتحقق من السعر والتوفر مرة أخرى قبل إتمام الطلب.</p>
        </aside>
    </div>
</header>