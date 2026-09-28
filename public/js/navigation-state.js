(() => {
    const nav = document.querySelector('.site-nav');
    const page = location.pathname + location.search;
    const read = (key) => {
        try { return JSON.parse(sessionStorage.getItem(key)); } catch { return null; }
    };
    const write = (key, value) => {
        try { sessionStorage.setItem(key, JSON.stringify(value)); } catch { /* Storage may be disabled. */ }
    };
    const restore = () => {
        if (nav) nav.scrollLeft = read('league.navScroll') || 0;
        if (read('league.restorePage') === page) {
            const position = read('league.position:' + page);
            if (position) window.scrollTo({ top: position.y, left: position.x, behavior: 'instant' });
            write('league.restorePage', null);
        }
    };
    if (nav) nav.addEventListener('scroll', () => write('league.navScroll', nav.scrollLeft), { passive: true });
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;
        if (link.matches('.player-link')) {
            write('league.position:' + page, { x: window.scrollX, y: window.scrollY });
        }
        if (link.matches('[data-player-back]')) {
            const target = new URL(link.href);
            write('league.restorePage', target.pathname + target.search);
        }
    });
    window.addEventListener('pageshow', restore);
    if (nav) nav.scrollLeft = read('league.navScroll') || 0;
})();
