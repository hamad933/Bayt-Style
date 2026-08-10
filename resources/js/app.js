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
            'Accept': 'application/json',
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
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await requestJson(`/wishlist/${productId}/toggle`, { method: 'POST', body: '{}' });
                this.saved = Boolean(data.saved);
                Alpine.store('notice').show(this.saved ? 'تم حفظ القطعة في المفضلة.' : 'تمت إزالة القطعة من المفضلة.');
            } catch (error) {
                Alpine.store('notice').show(error.message);
            } finally {
                this.busy = false;
            }
        },
    }));

    Alpine.data('productDetail', (productId, variantId, initialSaved = false) => ({
        quantity: 1,
        saved: Boolean(initialSaved),
        adding: false,
        increase() { if (this.quantity < 10) this.quantity += 1; },
        decrease() { if (this.quantity > 1) this.quantity -= 1; },
        async addToCart() {
            if (this.adding) return;
            this.adding = true;
            try {
                await Alpine.store('cart').add(variantId, this.quantity);
            } catch (error) {
                Alpine.store('notice').show(error.message);
            } finally {
                this.adding = false;
            }
        },
        async toggleWishlist() {
            try {
                const data = await requestJson(`/wishlist/${productId}/toggle`, { method: 'POST', body: '{}' });
                this.saved = Boolean(data.saved);
                Alpine.store('notice').show(this.saved ? 'تم حفظ القطعة في المفضلة.' : 'تمت إزالة القطعة من المفضلة.');
            } catch (error) {
                Alpine.store('notice').show(error.message);
            }
        },
    }));

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
