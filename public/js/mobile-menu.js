(() => {
    const header = document.querySelector('.site-header');
    const button = document.querySelector('.nav-toggle');
    const nav = document.querySelector('#main-navigation');
    if (!header || !button || !nav) return;

    const close = () => {
        header.classList.remove('menu-open');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'メニューを開く');
    };
    header.classList.add('menu-ready');
    button.addEventListener('click', () => {
        const open = header.classList.toggle('menu-open');
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', open ? 'メニューを閉じる' : 'メニューを開く');
    });
    nav.addEventListener('click', (event) => {
        if (event.target.closest('a')) close();
    });
    document.addEventListener('click', (event) => {
        if (!header.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            close();
            button.focus();
        }
    });
    window.matchMedia('(max-width: 640px)').addEventListener('change', close);
    window.addEventListener('pageshow', close);
})();
