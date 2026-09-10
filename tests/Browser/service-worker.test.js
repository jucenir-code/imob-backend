import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';
const source = readFileSync(new URL('../../public/sw.js', import.meta.url), 'utf8');
function worker() {
    const handlers = {};
    const shown = [];
    const opened = [];
    const scope = { location: { origin: 'https://cci.test' }, addEventListener: (name, handler) => { handlers[name] = handler; }, registration: { showNotification: async (title, options) => shown.push({title, options}) }, clients: { matchAll: async () => [], openWindow: async url => opened.push(url) } };
    vm.runInNewContext(source, { self: scope, URL });
    return { handlers, shown, opened, scope };
}
test('push shows conversation/property notices with safe destinations', async () => {
    const { handlers, shown } = worker();
    for (const url of ['/app/negociacoes/12', '/app/imoveis/34', 'https://evil.test/']) {
        let done;
        handlers.push({ data: { json: () => ({ title: 'CCI', url }) }, waitUntil: promise => { done = promise; } });
        await done;
    }
    assert.deepEqual(shown.map(n => n.options.data.url), ['/app/negociacoes/12', '/app/imoveis/34', '/app/imoveis']);
});
test('notification click focuses and navigates an existing app window', async () => {
    const { handlers, scope, opened } = worker();
    const actions = [];
    scope.clients.matchAll = async () => [{ url: 'https://cci.test/app/imoveis', navigate: async url => actions.push(url), focus: async () => actions.push('focus') }];
    let done;
    handlers.notificationclick({ notification: { data: { url: '/app/negociacoes/12' }, close: () => actions.push('close') }, waitUntil: p => { done = p; } });
    await done;
    assert.deepEqual(actions, ['close', 'https://cci.test/app/negociacoes/12', 'focus']);
    assert.equal(opened.length, 0);
});
test('notification click opens a window and refuses external URLs', async () => {
    const { handlers, opened } = worker();
    let done;
    handlers.notificationclick({ notification: { data: { url: '//evil.test/' }, close() {} }, waitUntil: p => { done = p; } });
    await done;
    assert.deepEqual(opened, ['https://cci.test/app/imoveis']);
});

test('foreground clients receive the push after a visible system notification', async () => {
    const { handlers, scope, shown } = worker();
    const received = [];
    scope.clients.matchAll = async () => [{ postMessage: data => received.push(data) }];
    let done;
    handlers.push({ data: { json: () => ({ title: 'Nova mensagem', user_id: 7, url: '/app/negociacoes/9' }) }, waitUntil: p => { done = p; } });
    await done;
    assert.equal(shown.length, 1);
    assert.equal(received[0].type, 'CCI_PUSH');
    assert.equal(received[0].user_id, 7);
});
