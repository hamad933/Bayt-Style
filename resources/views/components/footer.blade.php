<footer class="site-footer" id="customer-service">
    <div class="shell footer-grid">
        <div class="footer-brand">
            <p class="brand-ar">بيت وأسلوب</p>
            <p class="brand-en" dir="ltr">Bayt & Style</p>
            <p>أسلوب معاصر يلهم مساحتك، ويجعل تفاصيلها أكثر هدوءًا ودفئًا.</p>
        </div>
        <div>
            <h2>تسوق</h2>
            <nav aria-label="روابط التسوق">
                <a href="{{ route('catalog') }}?category=seating">المجموعات</a>
                <a href="{{ route('catalog') }}?room={{ urlencode('المعيشة') }}">الغرف</a>
                <a href="{{ route('catalog') }}">المنتجات</a>
                <a href="{{ route('home') }}#seasonal">الإلهام</a>
            </nav>
        </div>
        <div>
            <h2>معلومات</h2>
            <div class="footer-links" aria-label="معلومات المتجر">
                <span>من نحن</span>
                <span>خدمات العملاء</span>
                <span>سياسة الشحن</span>
                <span>سياسة الإرجاع</span>
            </div>
        </div>
        <div>
            <h2>الحساب</h2>
            <div class="footer-links" aria-label="خدمات الحساب">
                <span>تسجيل الدخول</span>
                <span>المفضلة</span>
                <span>الطلبات</span>
            </div>
        </div>
        <div class="newsletter">
            <h2>النشرة البريدية</h2>
            <p>تابع أحدث المجموعات والأفكار التحريرية عند تفعيل هذه الخدمة.</p>
            <div class="newsletter-placeholder" aria-label="النشرة البريدية غير متاحة حاليًا">النشرة البريدية — قريبًا</div>
        </div>
    </div>
    <div class="shell footer-bottom">
        <span>© بيت وأسلوب</span>
        <span>الأسعار معروضة بالريال السعودي.</span>
    </div>
</footer>
