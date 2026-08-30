const compactOrderDetail = window.matchMedia('(max-width: 1000px)');

function setStyle(element, property, value) {
    if (!element) return;
    element.style[property] = value;
}

function clearCompactOverrides(panel) {
    if (!panel) return;
    for (const property of ['order', 'display', 'flexDirection']) {
        panel.style[property] = '';
    }

    for (const child of panel.querySelectorAll('.s10-boundary, .s10-facts, .s10-sensitive-form')) {
        child.style.order = '';
    }
}

function syncOrderDetailSequence() {
    const grid = document.querySelector('.s10-order-head + .s10-grid.s10-grid--two');
    if (!grid) return;

    const itemsPanel = grid.querySelector('.s10-table--compact')?.closest('.admin-panel');
    const paymentPanel = grid.querySelector('.s10-state--financial')?.closest('.admin-panel');
    if (!itemsPanel || !paymentPanel) return;

    if (compactOrderDetail.matches) {
        if (grid.firstElementChild !== paymentPanel) {
            grid.insertBefore(paymentPanel, itemsPanel);
        }

        setStyle(paymentPanel, 'order', '-1');
        setStyle(paymentPanel, 'display', 'flex');
        setStyle(paymentPanel, 'flexDirection', 'column');
        setStyle(paymentPanel.querySelector('.s10-boundary'), 'order', '1');
        setStyle(paymentPanel.querySelector('.s10-facts'), 'order', '2');
        setStyle(paymentPanel.querySelector('.s10-sensitive-form'), 'order', '3');

        setStyle(itemsPanel, 'order', '0');
        setStyle(itemsPanel, 'display', 'block');
        itemsPanel.style.flexDirection = '';
        return;
    }

    if (grid.firstElementChild !== itemsPanel) {
        grid.insertBefore(itemsPanel, paymentPanel);
    }
    clearCompactOverrides(itemsPanel);
    clearCompactOverrides(paymentPanel);
}

function installSensitiveSubmitState(form) {
    if (!form || form.dataset.submitStateReady === 'true') return;
    form.dataset.submitStateReady = 'true';
    form.setAttribute('aria-busy', 'false');

    const submitButton = form.querySelector('button[type="submit"], button:not([type])');
    if (!submitButton) return;

    const idleLabel = submitButton.textContent.trim();
    const status = document.createElement('p');
    status.className = 's10-submit-status';
    status.hidden = true;
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    status.setAttribute('aria-atomic', 'true');
    status.textContent = 'جارٍ تنفيذ الإجراء…';
    form.append(status);

    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.dataset.idleLabel = idleLabel;
        submitButton.textContent = 'جارٍ التنفيذ…';
        status.hidden = false;
    });
}

function installSensitiveSubmitStates() {
    for (const form of document.querySelectorAll('.admin-main form[method="post"]')) {
        installSensitiveSubmitState(form);
    }
}

syncOrderDetailSequence();
installSensitiveSubmitStates();
compactOrderDetail.addEventListener('change', syncOrderDetailSequence);
