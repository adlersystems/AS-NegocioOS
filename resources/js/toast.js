const ICONS = {
    success: 'M4.5 12.75l6 6 9-13.5',
    error: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    warning: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    info: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
    close: 'M6 18L18 6M6 6l12 12',
};

const COLORS = {
    success: '--color-success',
    error: '--color-danger',
    warning: '--color-warning',
    info: '--color-info',
};

const DURATION = 4000;
const LEAVE_MS = 220;

function svg(path, className) {
    const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.setAttribute('fill', 'none');
    el.setAttribute('viewBox', '0 0 24 24');
    el.setAttribute('stroke-width', '1.5');
    el.setAttribute('stroke', 'currentColor');
    el.setAttribute('stroke-linecap', 'round');
    el.setAttribute('stroke-linejoin', 'round');
    el.setAttribute('class', className);

    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', path);
    el.appendChild(p);

    return el;
}

function region() {
    let el = document.getElementById('toast-region');

    if (! el) {
        el = document.createElement('div');
        el.id = 'toast-region';
        document.body.appendChild(el);
    }

    return el;
}

function buildToast(type, message) {
    const color = `var(${COLORS[type] ?? COLORS.info})`;

    const root = document.createElement('div');
    root.className = 'as-toast as-toast-enter';
    root.setAttribute('role', 'status');
    root.setAttribute('aria-live', 'polite');

    const iconW = document.createElement('span');
    iconW.className = 'as-toast-icon';
    iconW.style.color = color;
    iconW.appendChild(svg(ICONS[type] ?? ICONS.info, 'h-5 w-5'));

    const text = document.createElement('p');
    text.className = 'as-toast-text';
    text.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'as-toast-close';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.appendChild(svg(ICONS.close, 'h-4 w-4'));

    closeBtn.addEventListener('click', () => dismiss(root));

    root.appendChild(iconW);
    root.appendChild(text);
    root.appendChild(closeBtn);

    return root;
}

function dismiss(root) {
    if (root.dataset.leaving === '1') {
        return;
    }

    root.dataset.leaving = '1';
    root.classList.remove('as-toast-enter');
    root.classList.add('as-toast-leave');

    window.setTimeout(() => root.remove(), LEAVE_MS);
}

function showToast(type, message) {
    if (! message) {
        return;
    }

    const el = buildToast(type, message);
    region().appendChild(el);

    window.setTimeout(() => {
        el.classList.remove('as-toast-enter');
        el.classList.add('as-toast-show');
    }, 20);

    window.setTimeout(() => dismiss(el), DURATION);
}

function readInitial() {
    const script = document.querySelector('script[data-toast-initial]');

    if (! script) {
        return;
    }

    let payload = null;

    try {
        payload = JSON.parse(script.textContent);
    } catch (e) {
        return;
    }

    if (payload && typeof payload === 'object') {
        ['success', 'error', 'warning', 'info'].forEach((type) => {
            if (typeof payload[type] === 'string' && payload[type].trim() !== '') {
                showToast(type, payload[type]);
            }
        });
    }
}

window.showToast = showToast;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', readInitial);
} else {
    readInitial();
}
