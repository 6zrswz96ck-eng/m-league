const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const source = readFileSync('public/js/navigation-state.js', 'utf8');

function open(path, storage) {
    const events = {};
    const nav = { scrollLeft: 0, addEventListener: (name, fn) => { events['nav:' + name] = fn; } };
    const window = { scrollX: 0, scrollY: 0, scrollTo: (position) => { window.scrollY = position.top; }, addEventListener: (name, fn) => { events[name] = fn; } };
    runInNewContext(source, {
        URL, window, location: new URL(path, 'https://example.test'),
        sessionStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value) },
        document: { querySelector: () => nav, addEventListener: (name, fn) => { events[name] = fn; } },
    });
    events.pageshow();
    return { window, nav, events, click: (selector, href) => events.click({ target: { closest: () => ({ href, matches: (value) => value === selector }) } }) };
}

test('return restores each list scroll and horizontal menu position', () => {
    for (const path of ['/players/ranking', '/?category=2']) {
        const storage = new Map();
        const list = open(path, storage);
        list.window.scrollY = 840;
        list.nav.scrollLeft = 230;
        list.events['nav:scroll']();
        list.click('.player-link', 'https://example.test/players/1');
        const detail = open('/players/1', storage);
        assert.equal(detail.nav.scrollLeft, 230);
        detail.click('[data-player-back]', 'https://example.test' + path);
        const returned = open(path, storage);
        assert.equal(returned.window.scrollY, 840);
        assert.equal(returned.nav.scrollLeft, 230);
        assert.equal(storage.get('league.restorePage'), 'null');
    }
});
