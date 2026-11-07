/**
 * ApexSpend — category-helper.js
 * Frontend single source of truth for categories: replaces the 7 duplicated
 * categoryIcon() maps and drives dynamic dropdowns, chips and chart colors.
 *
 * Boot: pages embed `window.APEX_CATEGORIES = [...]` server-side (fast, no
 * flicker). `ApexCategories.get()` can re-fetch live data after edits.
 */
(function () {
    'use strict';

    // Canonical built-in palette — MUST mirror apex_builtin_categories() in
    // includes/categories.php so seed data and UI can't drift.
    const CATEGORY_STYLE = {
        'Food & Dining':       { icon: 'fa-utensils',          color: '#f59e0b' },
        'Housing & Utilities': { icon: 'fa-bolt',              color: '#3b82f6' },
        'Shopping & Retail':   { icon: 'fa-bag-shopping',      color: '#a855f7' },
        'Transportation':      { icon: 'fa-car',               color: '#4ade80' },
        'Entertainment':       { icon: 'fa-film',              color: '#ec4899' },
        'Health & Medical':    { icon: 'fa-briefcase-medical', color: '#22d3ee' },
        'Income':              { icon: 'fa-money-bill-wave',   color: '#8a8a8a' },
    };

    // Icon picker set — mirrors apex_icon_whitelist() in includes/categories.php.
    const ICONS = [
        'fa-tag', 'fa-utensils', 'fa-bolt', 'fa-bag-shopping', 'fa-car',
        'fa-film', 'fa-briefcase-medical', 'fa-money-bill-wave',
        'fa-house', 'fa-heart', 'fa-plane', 'fa-dog', 'fa-book', 'fa-graduation-cap',
        'fa-shirt', 'fa-gift', 'fa-gamepad', 'fa-mug-hot', 'fa-basket-shopping',
        'fa-dumbbell', 'fa-paw', 'fa-phone', 'fa-wifi', 'fa-droplet', 'fa-fire-flame-simple',
        'fa-baby', 'fa-children', 'fa-church', 'fa-spa', 'fa-umbrella',
        'fa-taxi', 'fa-bus', 'fa-gas-pump', 'fa-toolbox', 'fa-scissors',
        'fa-lightbulb', 'fa-laptop', 'fa-mobile-screen', 'fa-camera', 'fa-music',
        'fa-vault', 'fa-piggy-bank', 'fa-credit-card', 'fa-receipt', 'fa-wand-magic-sparkles',
    ];

    // Color swatch row — mirrors apex_color_palette().
    const COLORS = [
        '#f59e0b', '#3b82f6', '#a855f7', '#4ade80', '#ec4899', '#22d3ee',
        '#8a8a8a', '#f87171', '#34d399', '#fbbf24', '#60a5fa', '#e879f9',
    ];

    let cache = null;      // array of {id,name,icon,color,is_builtin,position}
    let byName = null;     // name → row

    function seed() {
        if (byName) return byName;
        const items = Array.isArray(window.APEX_CATEGORIES) && window.APEX_CATEGORIES.length
            ? window.APEX_CATEGORIES.slice()
            : Object.keys(CATEGORY_STYLE).map((name, i) => ({
                id: 'cat_builtin_' + i, name, icon: CATEGORY_STYLE[name].icon,
                color: CATEGORY_STYLE[name].color, is_builtin: 1, position: i + 1,
              }));
        byName = {};
        items.forEach((c) => { byName[c.name] = c; });
        cache = items;
        return byName;
    }

    async function get(force) {
        if (cache && !force) return cache;
        try {
            const res = await fetch('../api/api_get_categories.php');
            const data = await res.json();
            if (data.success && Array.isArray(data.items) && data.items.length) {
                cache = data.items;
                byName = {};
                cache.forEach((c) => { byName[c.name] = c; });
            }
        } catch (e) {
            // keep existing cache / builtin fallback
        }
        return cache;
    }

    function icon(name) {
        const row = seed()[name];
        if (row) return row.icon;
        return (CATEGORY_STYLE[name] || {}).icon || 'fa-tag';
    }

    function color(name) {
        const row = seed()[name];
        if (row) return row.color;
        return (CATEGORY_STYLE[name] || {}).color || '#f59e0b';
    }

    function names(onlyExpense) {
        const rows = seed();
        return cache
            .filter((c) => !onlyExpense || c.name !== 'Income')
            .sort((a, b) => (a.position - b.position) || a.name.localeCompare(b.name))
            .map((c) => c.name);
    }

    /**
     * Fill a <select> with the user's categories.
     * opts.keep — a name that must stay in the list even if not in categories
     * opts.current — preselect this value
     */
    function populateSelect(el, opts) {
        if (!el) return;
        opts = opts || {};
        const list = names(false);
        if (opts.keep && !list.includes(opts.keep)) list.push(opts.keep);
        const prev = opts.current != null ? opts.current : el.value;
        el.innerHTML = '';
        list.forEach((n) => {
            const o = document.createElement('option');
            o.value = n;
            o.textContent = n;
            if (prev && n === prev) o.selected = true;
            el.appendChild(o);
        });
    }

    /**
     * Stamp icons/colors on elements rendered from data-category.
     * Selector: `root.querySelectorAll('[data-category] .cat i, [data-category] i.cat-ic')`
     * Default handles the ledger pattern: `<span data-category="…"><i class="fa-solid …"></i></span>`.
     */
    function applyToIcons(root) {
        root = root || document;
        root.querySelectorAll('[data-category]').forEach((el) => {
            const cat = el.getAttribute('data-category');
            const ic = el.querySelector('.cat i') || el.querySelector('i.cat-ic') || el.querySelector('i');
            if (!ic) return;
            ic.className = 'fa-solid ' + icon(cat);
            el.style.setProperty('--cat-color', color(cat));
        });
    }

    window.ApexCategories = {
        CATEGORY_STYLE,
        ICONS,
        COLORS,
        get,
        icon,
        color,
        names,
        populateSelect,
        applyToIcons,
    };

    // Back-compat: some engines call a global categoryIcon(cat).
    window.categoryIcon = window.categoryIcon || icon;
})();
