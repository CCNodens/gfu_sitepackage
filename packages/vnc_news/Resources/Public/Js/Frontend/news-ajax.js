(function () {
    'use strict';

    const CFG = {
        containerSel: '.news-list-view.news-container[id]',
        listRootSel: '.news-articles',
        itemsGridSel: '.news-articles .grid',
        paginatorSels: [
            '.f3-widget-paginator', '[data-news-paginator]', 'nav.pagination',
            '.pagination', '.paginator', '.pager'
        ],
        nextLinkSels: [
            '.next a', 'a[rel="next"]', 'a[data-next]', '.pagination-next a',
            'a[aria-label*="next" i]', 'a[aria-label*="weiter" i]', 'a[aria-label*="nächste" i]'
        ],
        loadMoreBtnSel: '.news-view-more',
        masonry: {
            enabled: true,
            rowSizePx: 8,
            firstSortOrder: 'desc',
            sessionKeyPrefix: 'newsMasonrySorted:'
        },
        infinite: {
            enabled: true,           // Auto-Nachladen aktivieren
            rootMargin: '600px 0px', // wie früh vor dem Viewport schon geladen wird
            threshold: 0,            // sobald Sentinel im Sichtbereich
            sentinelClass: 'news-infinite-sentinel',
            // maxAutoLoads: 0       // optional: 0 = unbegrenzt, sonst Anzahl begrenzen
        }
    };

    const $  = (root, sel) => (root || document).querySelector(sel);
    const $$ = (root, sel) => Array.from((root || document).querySelectorAll(sel));

    // Sichtbarkeit ohne [hidden]-Attribut steuern (Tailwind-Kollision vermeiden)
    function setVisible(el, visible) {
        if (!el) return;
        el.style.display = visible ? '' : 'none';
        el.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    const getItemsGrid = (container) =>
        $(container, CFG.itemsGridSel) || $(container, CFG.listRootSel);

    function getPaginator(root) {
        for (const sel of CFG.paginatorSels) {
            const el = (root || document).querySelector(sel);
            if (el) return el;
        }
        return null;
    }

    function getNextUrl(container) {
        const pag = getPaginator(container) || container;

        // 1) direkte Kandidaten
        let a =
            pag.querySelector('.next a, a[rel="next"], a[data-next], .pagination-next a') ||
            Array.from(pag.querySelectorAll('a')).find(x => {
                const al = (x.getAttribute('aria-label') || '').toLowerCase();
                const txt = (x.textContent || '').toLowerCase();
                return /weiter|nächste|next|more|mehr/.test(al) || /weiter|nächste|next|more|mehr/.test(txt);
            });

        // 2) aus „aktuelle Seite“ ableiten
        if (!a) {
            const current = pag.querySelector('.current, [aria-current="page"]');
            if (current) {
                const li = current.closest('li');
                const sib = li?.nextElementSibling;
                const cand = (sib?.querySelector('a[href]')) ||
                    (current.nextElementSibling?.tagName === 'A' ? current.nextElementSibling : null);
                if (cand) a = cand;
            }
        }

        // 3) Fallback: irgendein Link mit href (nächster nach dem aktiven)
        if (!a) {
            const links = Array.from(pag.querySelectorAll('a[href]'));
            const activeIdx = links.findIndex(x => x.matches('.current, [aria-current="page"]'));
            if (links.length >= 2) a = links[Math.min(activeIdx + 1, links.length - 1)];
        }

        return a ? a.getAttribute('href') : null;
    }

    async function fetchHTML(href) {
        const res = await fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return await res.text();
    }

    function queryFresh(container, html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const freshRoot = doc.querySelector('#' + CSS.escape(container.id)) || doc;
        return {
            freshItemsGrid: freshRoot.querySelector(CFG.itemsGridSel) || freshRoot.querySelector(CFG.listRootSel),
            freshPaginator: getPaginator(freshRoot) || null
        };
    }

    // ---------- Masonry helpers ----------
    function ensureAutoRows(grid) {
        grid.style.gridAutoRows = CFG.masonry.rowSizePx + 'px';
        grid.style.gridAutoFlow = 'row dense';
        grid.style.alignItems   = 'start';
    }
    function rowSize(grid) {
        const v = parseFloat(getComputedStyle(grid).gridAutoRows);
        return v || CFG.masonry.rowSizePx;
    }
    function rowGap(grid) {
        const cs = getComputedStyle(grid);
        return parseFloat(cs.rowGap || cs.gap || '0') || 0;
    }
    // nicht .h-full messen, sondern „natürlichen“ Body der Card
    function measureTarget(item) {
        const t =
            item.querySelector('[data-ajax-body]') ||
            item.querySelector('[data-teaser]') ||
            item;
        return t.getBoundingClientRect().height;
    }
    function spanFor(item, grid) {
        const r = rowSize(grid);
        const g = rowGap(grid);
        const h = measureTarget(item);
        return Math.max(1, Math.ceil((h + g) / (r + g)));
    }

    function applyRowSpans(grid, items = null) {
        ensureAutoRows(grid);
        const nodes = items && items.length ? items : $$(grid, ':scope > *');
        nodes.forEach(el => { el.style.gridRowEnd = ''; });
        nodes.forEach(el => { el.style.gridRowEnd = `span ${spanFor(el, grid)}`; });
    }

    function attachResizeObserver(grid, items = null) {
        if (!('ResizeObserver' in window)) return;
        if (!grid.__masonryRO) {
            grid.__masonryRO = new ResizeObserver(entries => {
                entries.forEach(({ target }) => {
                    target.style.gridRowEnd = `span ${spanFor(target, grid)}`;
                });
            });
        }
        const nodes = items && items.length ? items : $$(grid, ':scope > *');
        nodes.forEach(el => grid.__masonryRO.observe(el));
    }

    const delay = (ms) => new Promise(r => setTimeout(r, ms));

    async function firstVisitSortAndLayout(container) {
        if (!CFG.masonry.enabled) return;
        const grid = getItemsGrid(container);
        if (!grid) return;

        const key = CFG.masonry.sessionKeyPrefix + container.id;
        const already = sessionStorage.getItem(key);

        ensureAutoRows(grid);
        // zwei Frames warten, damit anfängliche Höhen stimmen
        await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

        if (!already) {
            const kids = $$(grid, ':scope > *');
            const measured = kids.map(el => ({ el, h: measureTarget(el) }));
            measured.sort((a, b) =>
                CFG.masonry.firstSortOrder === 'asc' ? a.h - b.h : b.h - a.h
            );
            const frag = document.createDocumentFragment();
            measured.forEach(({ el }) => frag.appendChild(el));
            grid.appendChild(frag);
            sessionStorage.setItem(key, '1');
        }

        applyRowSpans(grid);
        attachResizeObserver(grid);
        window.addEventListener('resize', () => applyRowSpans(grid), { passive: true });
    }

    async function relayoutMasonry(container, newItems = null) {
        if (!CFG.masonry.enabled) return;
        const grid = getItemsGrid(container);
        if (!grid) return;

        ensureAutoRows(grid);
        if (newItems && newItems.length) {
            applyRowSpans(grid, newItems);
            attachResizeObserver(grid, newItems);
        } else {
            applyRowSpans(grid);
            attachResizeObserver(grid);
        }
        await delay(50);
        if (newItems && newItems.length) {
            applyRowSpans(grid, newItems);
        } else {
            applyRowSpans(grid);
        }
    }

    // ---------- Ajax integration ----------
    function appendFromHTML(html, container) {
        const grid = getItemsGrid(container);
        const curPaginator = getPaginator(container);
        if (!grid) return { ok: false, newItems: [] };

        const f = queryFresh(container, html);
        if (!f.freshItemsGrid) return { ok: false, newItems: [] };

        const newNodes = $$(f.freshItemsGrid, ':scope > *');
        const appended = [];
        newNodes.forEach(node => {
            appended.push(node);
            grid.appendChild(node);
        });

        if (f.freshPaginator) {
            if (curPaginator) curPaginator.replaceWith(f.freshPaginator);
            else container.appendChild(f.freshPaginator);
        }
        return { ok: true, newItems: appended };
    }

    function replaceFromHTML(html, container) {
        const grid = getItemsGrid(container);
        const curPaginator = getPaginator(container);
        const f = queryFresh(container, html);
        if (!f.freshItemsGrid) return false;

        if (grid) grid.innerHTML = f.freshItemsGrid.innerHTML;
        if (f.freshPaginator) {
            if (curPaginator) curPaginator.replaceWith(f.freshPaginator);
            else container.appendChild(f.freshPaginator);
        }
        return true;
    }

    // ---------- Button & Infinite Scroll ----------
    function getLoadMoreButton(container) {
        return $(container, CFG.loadMoreBtnSel);
    }

    function refreshButton(container) {
        const btn = getLoadMoreButton(container);
        if (!btn) return;
        const hasPaginator = !!getPaginator(container);
        const hasNext = !!getNextUrl(container);
        // Button sichtbar, sobald es überhaupt eine Pagination gibt;
        // ausblenden nur, wenn sicher keine nächste Seite
        setVisible(btn, hasNext || hasPaginator);
        btn.disabled = false;
        const ldef  = btn.querySelector('.label-default');
        const lload = btn.querySelector('.label-loading');
        if (ldef)  ldef.hidden = false;
        if (lload) lload.hidden = true;
    }

    async function doLoadNext(container) {
        if (container.__loading) return false;
        const nextUrl = getNextUrl(container);
        if (!nextUrl) return false;

        const btn = getLoadMoreButton(container);
        container.__loading = true;
        if (btn) {
            btn.disabled = true;
            const ldef  = btn.querySelector('.label-default');
            const lload = btn.querySelector('.label-loading');
            if (ldef)  ldef.hidden = true;
            if (lload) lload.hidden = false;
        }

        try {
            const html = await fetchHTML(nextUrl);
            const { ok, newItems } = appendFromHTML(html, container);
            if (ok) await relayoutMasonry(container, newItems);
            return ok;
        } catch (e) {
            console.error('Load more failed', e);
            return false;
        } finally {
            container.__loading = false;
            refreshButton(container);
        }
    }

    function createSentinel(container) {
        let s = container.querySelector('.' + CFG.infinite.sentinelClass);
        if (!s) {
            s = document.createElement('div');
            s.className = CFG.infinite.sentinelClass;
            s.setAttribute('aria-hidden', 'true');
            // unauffällig
            s.style.cssText = 'width:1px;height:1px;margin:0;padding:0;opacity:0;';
            // möglichst weit unten platzieren (nach Paginator/Btn)
            const btn = getLoadMoreButton(container);
            const pag = getPaginator(container);
            if (pag && pag.parentNode) pag.parentNode.appendChild(s);
            else if (btn && btn.parentNode) btn.parentNode.appendChild(s);
            else container.appendChild(s);
        }
        return s;
    }

    function bindInfinite(container) {
        if (!CFG.infinite.enabled) return;
        if (container.getAttribute('data-infinite') === 'off') return;

        const sentinel = createSentinel(container);
        const io = new IntersectionObserver(async (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                // nicht parallel nachladen
                if (container.__loading) continue;
                // keine nächste Seite? Observer deaktivieren
                if (!getNextUrl(container)) { io.unobserve(sentinel); return; }
                // laden
                const ok = await doLoadNext(container);
                if (!ok || !getNextUrl(container)) {
                    // nichts mehr zu laden → Observer abklemmen
                    io.unobserve(sentinel);
                }
            }
        }, { root: null, rootMargin: CFG.infinite.rootMargin, threshold: CFG.infinite.threshold });

        io.observe(sentinel);
        // Referenz speichern, falls man später detach braucht
        container.__infiniteObserver = io;
    }

    function updateHistoryIfNeeded(href, container) {
        if (!href) return;
        const mode = container.getAttribute('data-ajax-mode') || 'load-more';
        const useHistory = container.getAttribute('data-history') === 'push';
        if (useHistory && mode === 'numbered') {
            history.pushState({}, '', href);
        }
    }

    // ---------- Modes ----------
    function bindLoadMore(container) {
        const btn = getLoadMoreButton(container);
        if (btn) {
            refreshButton(container);
            btn.addEventListener('click', async () => { await doLoadNext(container); });
        }
        // Infinite Scroll zusätzlich aktivieren
        bindInfinite(container);
    }

    function bindNumbered(container) {
        container.addEventListener('click', async (e) => {
            const pag = getPaginator(container);
            if (!pag) return;
            const a = e.target.closest('a');
            if (!a || !pag.contains(a)) return;

            e.preventDefault();
            const href = a.getAttribute('href');
            if (!href) return;

            container.setAttribute('aria-busy', 'true');
            try {
                const html = await fetchHTML(href);
                const ok = replaceFromHTML(html, container);
                if (ok) {
                    updateHistoryIfNeeded(href, container);
                    await relayoutMasonry(container);
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } catch (e2) {
                console.error('Ajax pagination failed', e2);
                window.location.href = href;
            } finally {
                container.removeAttribute('aria-busy');
            }
        }, { passive: false });
    }

    // ---------- Init ----------
    async function init(container) {
        const mode = container.getAttribute('data-ajax-mode') || 'load-more';
        if (mode === 'numbered') {
            bindNumbered(container);
            const btn = getLoadMoreButton(container);
            if (btn) setVisible(btn, false); // Button im nummerierten Modus ausblenden
        } else {
            bindLoadMore(container);         // Button + Infinite Scroll
        }
        await firstVisitSortAndLayout(container);
    }

    document.addEventListener('DOMContentLoaded', () => {
        $$(document, CFG.containerSel).forEach(init);
    });

    // Back/Forward für nummeriert
    window.addEventListener('popstate', async () => {
        const container = $(document, CFG.containerSel);
        if (!container) return;
        try {
            const html = await fetchHTML(location.href);
            const ok = replaceFromHTML(html, container);
            if (ok) await relayoutMasonry(container);
        } catch {}
    });
})();
