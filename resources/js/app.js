import Alpine from 'alpinejs';

window.Alpine = Alpine;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

function trapFocusWithin(event, container) {
    const focusable = [...container.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((element) => !element.disabled && element.offsetParent !== null);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            ...(options.headers || {}),
        },
        ...options,
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstError || payload.message || 'تعذر إكمال العملية.');
    }
    return payload;
}

document.addEventListener('alpine:init', () => {
    Alpine.store('notice', {
        message: '',
        timer: null,
        show(message) {
            this.message = message;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => { this.message = ''; }, 2800);
        },
    });

    Alpine.store('wishlist', {
        count: Number(document.documentElement.dataset.wishlistCount || 0),
        apply(data) {
            this.count = Number(data.count || 0);
        },
    });

    Alpine.store('comparison', {
        count: Number(document.documentElement.dataset.comparisonCount || 0),
        limit: 3,
        apply(data) {
            this.count = Number(data.count || 0);
            this.limit = Number(data.limit || 3);
        },
        async add(productId) {
            const data = await requestJson(`/comparison/${productId}`, { method: 'POST', body: '{}' });
            this.apply(data);
            Alpine.store('notice').show(data.already_present ? 'المنتج موجود بالفعل في المقارنة.' : 'تمت إضافة المنتج إلى المقارنة.');
            return data;
        },
        async remove(productId) {
            const data = await requestJson(`/comparison/${productId}`, { method: 'DELETE' });
            this.apply(data);
            Alpine.store('notice').show('تمت إزالة المنتج من المقارنة.');
            return data;
        },
    });

    Alpine.store('filters', {
        open: false,
        trigger: null,
        openDrawer() {
            this.trigger = document.activeElement;
            this.open = true;
            queueMicrotask(() => document.querySelector('#mobile-filter-panel button')?.focus());
        },
        closeDrawer() {
            this.open = false;
            if (this.trigger instanceof HTMLElement) this.trigger.focus();
        },
        trapFocus(event, container) { trapFocusWithin(event, container); },
    });

    Alpine.store('cart', {
        count: Number(document.documentElement.dataset.cartCount || 0),
        total: '0',
        items: [],
        open: false,
        loading: false,
        trigger: null,
        async refresh() {
            this.loading = true;
            try {
                const data = await requestJson('/cart/summary');
                this.apply(data);
            } catch (error) {
                Alpine.store('notice').show(error.message);
            } finally {
                this.loading = false;
            }
        },
        apply(data) {
            this.count = Number(data.count || 0);
            this.total = data.total || '0';
            this.items = Array.isArray(data.items) ? data.items : [];
        },
        async add(variantId, quantity) {
            const data = await requestJson('/cart/items', {
                method: 'POST',
                body: JSON.stringify({ variant_id: variantId, quantity }),
            });
            this.apply(data);
            Alpine.store('notice').show('تمت إضافة القطعة إلى السلة.');
            return data;
        },
        async setQuantity(variantId, quantity) {
            if (quantity < 1 || quantity > 10) return;
            try {
                const data = await requestJson(`/cart/items/${variantId}`, {
                    method: 'PATCH',
                    body: JSON.stringify({ quantity }),
                });
                this.apply(data);
            } catch (error) {
                Alpine.store('notice').show(error.message);
            }
        },
        async remove(variantId) {
            try {
                const data = await requestJson(`/cart/items/${variantId}`, { method: 'DELETE' });
                this.apply(data);
                Alpine.store('notice').show('تمت إزالة القطعة من السلة.');
            } catch (error) {
                Alpine.store('notice').show(error.message);
            }
        },
        async openDrawer() {
            this.trigger = document.activeElement;
            this.open = true;
            queueMicrotask(() => document.querySelector('.cart-drawer button')?.focus());
            await this.refresh();
        },
        closeDrawer() {
            this.open = false;
            if (this.trigger instanceof HTMLElement) this.trigger.focus();
        },
        trapFocus(event, container) { trapFocusWithin(event, container); },
    });

    Alpine.data('appShell', () => ({
        closeTransientUi() {
            Alpine.store('cart').closeDrawer();
            Alpine.store('filters').closeDrawer();
            window.dispatchEvent(new CustomEvent('close-shell-ui'));
        },
    }));

    Alpine.data('headerShell', () => ({
        mobileOpen: false,
        loginOpen: false,
        loginTrigger: null,
        init() {
            this.$watch('loginOpen', (open) => {
                if (open) {
                    this.loginTrigger = document.activeElement;
                    this.$nextTick(() => this.$refs.loginDialog?.querySelector('button')?.focus());
                } else if (this.loginTrigger instanceof HTMLElement) {
                    this.loginTrigger.focus();
                }
            });
        },
        trapFocus(event, container) { trapFocusWithin(event, container); },
    }));

    Alpine.data('wishlistToggle', (productId, initialSaved = false) => ({
        saved: Boolean(initialSaved),
        busy: false,
        init() {
            window.addEventListener('wishlist-changed', (event) => {
                if (Number(event.detail?.productId) === Number(productId)) this.saved = Boolean(event.detail.saved);
            });
        },
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await requestJson(`/wishlist/${productId}/toggle`, { method: 'POST', body: '{}' });
                this.saved = Boolean(data.saved);
                Alpine.store('wishlist').apply(data);
                window.dispatchEvent(new CustomEvent('wishlist-changed', { detail: { productId, saved: this.saved } }));
                Alpine.store('notice').show(this.saved ? 'تم حفظ القطعة في المفضلة.' : 'تمت إزالة القطعة من المفضلة.');
            } catch (error) {
                Alpine.store('notice').show(error.message);
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('comparisonToggle', (productId, initialCompared = false) => ({
        compared: Boolean(initialCompared),
        busy: false,
        init() {
            window.addEventListener('comparison-changed', (event) => {
                if (Number(event.detail?.productId) === Number(productId)) this.compared = Boolean(event.detail.compared);
            });
        },
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            try {
                if (this.compared) {
                    await Alpine.store('comparison').remove(productId);
                    this.compared = false;
                } else {
                    await Alpine.store('comparison').add(productId);
                    this.compared = true;
                }
                window.dispatchEvent(new CustomEvent('comparison-changed', { detail: { productId, compared: this.compared } }));
            } catch (error) {
                Alpine.store('notice').show(error.message);
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('productDetail', (config, initialSaved = false) => {
        const defaultVariant = config.variants.find((variant) => Number(variant.id) === Number(config.defaultVariantId)) || config.variants[0];

        return {
            config,
            quantity: 1,
            saved: Boolean(initialSaved),
            adding: false,
            selectedOptions: { ...(defaultVariant?.options || {}) },
            get selectedVariant() {
                return this.config.variants.find((variant) => this.config.dimensions.every((dimension) => {
                    return String(variant.options?.[dimension.key] ?? '') === String(this.selectedOptions[dimension.key] ?? '');
                })) || null;
            },
            get canAdd() {
                return Boolean(this.selectedVariant?.available);
            },
            get availabilityLabel() {
                if (!this.selectedVariant) return 'تركيبة غير موجودة';
                return this.selectedVariant.available ? 'متاح للشراء في بيانات التطوير' : 'غير متاح حاليًا';
            },
            increase() { if (this.quantity < 10) this.quantity += 1; },
            decrease() { if (this.quantity > 1) this.quantity -= 1; },
            isSelected(key, value) {
                return String(this.selectedOptions[key] ?? '') === String(value);
            },
            isOptionDisabled(key, value) {
                if (this.isSelected(key, value)) return false;
                const candidate = { ...this.selectedOptions, [key]: value };
                return !this.config.variants.some((variant) => {
                    if (!variant.available) return false;
                    return this.config.dimensions.every((dimension) => {
                        return String(variant.options?.[dimension.key] ?? '') === String(candidate[dimension.key] ?? '');
                    });
                });
            },
            choose(key, value) {
                if (this.isOptionDisabled(key, value)) return;
                this.selectedOptions = { ...this.selectedOptions, [key]: value };
            },
            async addToCart() {
                if (this.adding || !this.canAdd) return;
                this.adding = true;
                try {
                    await Alpine.store('cart').add(this.selectedVariant.id, this.quantity);
                } catch (error) {
                    Alpine.store('notice').show(error.message);
                } finally {
                    this.adding = false;
                }
            },
            async toggleWishlist() {
                try {
                    const data = await requestJson(`/wishlist/${this.config.productId}/toggle`, { method: 'POST', body: '{}' });
                    this.saved = Boolean(data.saved);
                    Alpine.store('wishlist').apply(data);
                    window.dispatchEvent(new CustomEvent('wishlist-changed', { detail: { productId: this.config.productId, saved: this.saved } }));
                    Alpine.store('notice').show(this.saved ? 'تم حفظ القطعة في المفضلة.' : 'تمت إزالة القطعة من المفضلة.');
                } catch (error) {
                    Alpine.store('notice').show(error.message);
                }
            },
        };
    });

    Alpine.data('gallery', (count) => ({
        active: 0,
        next() { this.active = (this.active + 1) % count; },
        previous() { this.active = (this.active - 1 + count) % count; },
    }));

    Alpine.data('accordions', () => ({
        open: new Set(['description']),
        isOpen(key) { return this.open.has(key); },
        toggle(key) {
            const next = new Set(this.open);
            next.has(key) ? next.delete(key) : next.add(key);
            this.open = next;
        },
    }));
});

Alpine.start();
