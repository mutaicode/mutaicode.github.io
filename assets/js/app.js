/* ============ CYA Connect — Interactive layer ============ */
document.addEventListener('DOMContentLoaded', () => {

    /* Year stamp */
    document.querySelectorAll('[data-year]').forEach(node => {
        node.textContent = new Date().getFullYear();
    });

    /* Topbar scroll state */
    const topbar = document.querySelector('.topbar');
    if (topbar) {
        const onScroll = () => topbar.classList.toggle('is-scrolled', window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* Mobile nav */
    const navToggle = document.querySelector('[data-toggle-menu]');
    const navMenu   = document.querySelector('[data-nav-menu]');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            const open = navMenu.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        navMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* Pointer glow on buttons */
    document.querySelectorAll('.button').forEach(btn => {
        btn.addEventListener('mousemove', e => {
            const rect = btn.getBoundingClientRect();
            btn.style.setProperty('--mx', `${e.clientX - rect.left}px`);
            btn.style.setProperty('--my', `${e.clientY - rect.top}px`);
        });
    });

    /* Animated counters */
    const animateCounter = (el) => {
        const raw = el.dataset.count !== undefined ? el.dataset.count : el.textContent;
        const match = String(raw).match(/-?\d+(\.\d+)?/);
        if (!match) return;
        const target = parseFloat(match[0]);
        const suffix = String(raw).replace(match[0], '');
        const duration = 1100;
        const start = performance.now();
        const isFloat = match[0].includes('.');
        const step = (now) => {
            const t = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - t, 3);
            const value = target * eased;
            el.textContent = (isFloat ? value.toFixed(1) : Math.round(value)) + suffix;
            if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    };

    const counterObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.4 });

    document.querySelectorAll('[data-counter]').forEach(el => counterObserver.observe(el));

    /* Progress bars */
    const progressObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const value = Math.max(0, Math.min(100, parseFloat(bar.dataset.progress || '0')));
                bar.style.width = value + '%';
                progressObserver.unobserve(bar);
            }
        });
    }, { threshold: 0.3 });

    document.querySelectorAll('.progress > span[data-progress]').forEach(el => progressObserver.observe(el));

    /* Chart bars */
    const chartObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.querySelectorAll('.bar').forEach(bar => {
                    const h = Math.max(0, Math.min(100, parseFloat(bar.dataset.height || '0')));
                    bar.style.height = h + '%';
                });
                chartObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    document.querySelectorAll('[data-chart]').forEach(el => chartObserver.observe(el));

    /* Reveal on scroll */
    const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

    /* Table search + filter chips */
    document.querySelectorAll('[data-table-tools]').forEach(tools => {
        const table = document.querySelector(tools.dataset.tableTools);
        if (!table) return;
        const search = tools.querySelector('[data-table-search]');
        const chips  = tools.querySelectorAll('[data-filter]');
        const rows   = Array.from(table.querySelectorAll('tbody tr'));
        let activeFilter = 'all';

        const apply = () => {
            const q = (search?.value || '').toLowerCase().trim();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = (row.dataset.status || '').toLowerCase();
                const matchesSearch = !q || text.includes(q);
                const matchesFilter = activeFilter === 'all' || status === activeFilter;
                row.classList.toggle('is-hidden', !(matchesSearch && matchesFilter));
            });
        };

        search?.addEventListener('input', apply);
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('is-active'));
                chip.classList.add('is-active');
                activeFilter = (chip.dataset.filter || 'all').toLowerCase();
                apply();
            });
        });
    });

    /* Auto-dismiss notices */
    document.querySelectorAll('.notice[data-autodismiss]').forEach(notice => {
        setTimeout(() => {
            notice.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            notice.style.opacity = '0';
            notice.style.transform = 'translateY(-8px)';
            setTimeout(() => notice.remove(), 500);
        }, parseInt(notice.dataset.autodismiss, 10) || 5000);
    });

    /* Toast system */
    const toastStack = document.createElement('div');
    toastStack.className = 'toast-stack';
    document.body.appendChild(toastStack);

    window.showToast = (message, type = 'info', duration = 3800) => {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        toast.innerHTML = `<span style="font-weight:800">${icon}</span><span></span>`;
        toast.lastElementChild.textContent = message;
        toastStack.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('is-leaving');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };

    /* Server flash bridge */
    document.querySelectorAll('[data-flash]').forEach(el => {
        const type = el.dataset.flash || 'info';
        const msg  = el.dataset.flashMessage || el.textContent.trim();
        if (msg) window.showToast(msg, type, 5000);
        el.remove();
    });

    /* Form loading state */
    document.querySelectorAll('form[data-loading]').forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.classList.add('is-loading'); btn.disabled = true; }
        });
    });

    /* Confirm dialogs */
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!window.confirm(el.dataset.confirm)) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    });

    /* Gallery lightbox */
    const galleryItems = document.querySelectorAll('.gallery-item img');
    if (galleryItems.length) {
        const lightbox = document.createElement('div');
        lightbox.className = 'lightbox';
        lightbox.innerHTML = `<button class="lightbox-close" aria-label="Close">✕</button><img alt="">`;
        document.body.appendChild(lightbox);

        const lbImg = lightbox.querySelector('img');
        const close = () => lightbox.classList.remove('is-open');

        galleryItems.forEach(img => {
            img.addEventListener('click', () => {
                lbImg.src = img.src;
                lbImg.alt = img.alt || '';
                lightbox.classList.add('is-open');
            });
        });
        lightbox.addEventListener('click', e => {
            if (e.target === lightbox || e.target.classList.contains('lightbox-close')) close();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    }

    /* Status badge auto-coloring */
    document.querySelectorAll('[data-status]').forEach(el => {
        const s = el.dataset.status.toLowerCase();
        el.classList.remove('success', 'warning', 'danger', 'info');
        if (['present', 'approved', 'completed', 'ongoing'].includes(s)) el.classList.add('success');
        else if (['late', 'pending', 'upcoming', 'registered'].includes(s)) el.classList.add('warning');
        else if (['absent', 'rejected', 'cancelled'].includes(s)) el.classList.add('danger');
        else el.classList.add('info');
    });
});