import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import http from 'node:http';
import { chromium } from 'playwright';

const runtime = await readFile(new URL('../application/resources/placements.js', import.meta.url), 'utf8');
const admin = await readFile(new URL('../application/resources/admin.js', import.meta.url), 'utf8');
let fixture = '';
let scriptRequests = 0;
let legacyRequests = 0;
let heldScript = null;
let signalHeldScript = null;
const server = http.createServer((request, response) => {
    if (request.url === '/placements.js' || request.url === '/admin.js') {
        response.setHeader('Content-Type', 'application/javascript');
        response.end(request.url === '/placements.js' ? runtime : admin);
    } else if (request.url === '/block-script.js') {
        scriptRequests++;
        response.setHeader('Content-Type', 'application/javascript');
        response.end('window.order.push("external:" + document.currentScript.closest("[data-rqbp-placement]").dataset.rqbpPlacement);');
    } else if (request.url === '/legacy-script.js') {
        legacyRequests++;
        response.setHeader('Content-Type', 'application/javascript');
        response.end('window.legacyRan=true;');
    } else if (request.url === '/held-script.js') {
        response.setHeader('Content-Type', 'application/javascript');
        heldScript = response;
        signalHeldScript?.();
    } else {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(fixture);
    }
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
let executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
if (!executablePath && process.platform === 'darwin') {
    const chrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    try {
        await access(chrome);
        executablePath = chrome;
    } catch {}
}
const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
const page = await browser.newPage();
const failures = [];
page.on('pageerror', (error) => failures.push(error.message));
let passed = 0;

const rule = (id, overrides = {}) => ({ id, name: `Rule ${id}`, selector: '#target', position: 'after', occurrence: 'first', occurrence_index: 1, sort_order: id, html: `<b data-ad="${id}">Ad ${id}</b>`, ...overrides });
const encode = (value) => JSON.stringify(value).replaceAll('<', '\\u003c');
function fixtureHtml(body, rules, extra = {}) {
    return `<!doctype html><html><head><title>Placements test</title></head><body>${body}<script type="application/json" data-rqbp-payload>${encode({ page_url: '/fixture', rules, ...extra })}</script><script src="/placements.js"></script></body></html>`;
}
async function load(body, rules, extra = {}) {
    fixture = fixtureHtml(body, rules, extra);
    await page.goto(`${origin}/fixture`);
    await page.evaluate(() => window.rqBlockPlacements.refresh(document));
}
async function changed(selector) {
    await page.evaluate(async (selector) => {
        document.dispatchEvent(new CustomEvent('contentChange', { detail: { root: document.querySelector(selector) } }));
        await window.rqBlockPlacements.whenIdle();
    }, selector);
}
async function test(name, callback) {
    failures.length = 0;
    try {
        await callback();
        assert.deepEqual(failures, [], 'Unexpected browser JavaScript errors');
        passed++;
        console.log(`ok ${passed} - ${name}`);
    } catch (error) {
        console.error(`not ok - ${name}`);
        console.error(error.stack || error);
        process.exitCode = 1;
    }
}

try {
    await test('before and after rules use sort order independently of payload order', async () => {
        await load('<main><div id="target">Target</div></main>', [
            rule(4, { sort_order: 40 }), rule(2, { position: 'before', sort_order: 20 }),
            rule(1, { position: 'before', sort_order: 10 }), rule(3, { sort_order: 30 })
        ]);
        assert.deepEqual(await page.locator('main > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || 'target')), ['1', '2', 'target', '3', '4']);
        await changed('main');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 4);
    });

    await test('structural selectors ignore placements on subsequent content changes', async () => {
        await load('<ul id="list"><li class="row" id="a">A</li><li class="row" id="target">B</li><li class="row" id="c">C</li></ul>', [
            rule(1, { selector: '#list > li:nth-child(2)' }), rule(2, { selector: '#list > .row', occurrence: 'nth', occurrence_index: 2, position: 'before' })
        ]);
        await changed('#list');
        await changed('#list');
        assert.deepEqual(await page.locator('#list > *').evaluateAll((nodes) => nodes.map((node) => node.id || node.dataset.rqbpPlacement)), ['a', '2', 'target', '1', 'c']);
        assert.deepEqual(await page.locator('[data-rqbp-placement]').evaluateAll((nodes) => nodes.map((node) => node.tagName)), ['LI', 'LI']);
    });

    await test('all occurrences and scripts execute once at their final destination', async () => {
        await load('<script>window.order=[];</script><main><div class="slot">A</div><div class="slot">B</div></main>', [rule(1, {
            selector: '.slot', occurrence: 'all',
            html: '<script data-probe="preserved">window.order.push("inline:" + document.currentScript.closest("[data-rqbp-placement]").dataset.rqbpPlacement);</script><script src="/block-script.js"></script><script>window.order.push("last");</script><script type="application/ld+json">window.dataScriptWasExecuted=true;</script>'
        })]);
        assert.deepEqual(await page.evaluate(() => window.order), ['inline:1', 'external:1', 'last', 'inline:1', 'external:1', 'last']);
        assert.equal(await page.evaluate(() => window.dataScriptWasExecuted), undefined);
        assert.equal(await page.locator('script[data-probe="preserved"]').count(), 2);
        await changed('main');
        assert.equal((await page.evaluate(() => window.order)).length, 6);
    });

    await test('modern browsers skip nomodule scripts without a timeout or request', async () => {
        const initialLegacy = legacyRequests;
        const started = Date.now();
        await load('<main><div id="target">Target</div></main>', [rule(1, {
            html: '<script nomodule src="/legacy-script.js"></script><script>window.afterLegacy=true;</script>'
        })]);
        assert.equal(await page.evaluate(() => window.afterLegacy), true);
        assert.equal(await page.evaluate(() => window.legacyRan), undefined);
        assert.equal(legacyRequests, initialLegacy);
        assert.ok(Date.now() - started < 2000, 'Skipped nomodule must not incur the 15-second script timeout');
        assert.deepEqual((await page.evaluate(() => window.rqBlockPlacements.getDiagnostics()))[0].notes, []);
    });

    await test('modules execute once while plain text and JSON scripts stay inert', async () => {
        await load('<main><div id="target">Target</div></main>', [rule(1, {
            html: '<script type="module">window.moduleRuns=(window.moduleRuns||0)+1;</script><script type="text/plain">window.plainRan=true;</script><script type="application/json">{"inert":true}</script>'
        })]);
        assert.equal(await page.evaluate(() => window.moduleRuns), 1);
        assert.equal(await page.evaluate(() => window.plainRan), undefined);
        await changed('main');
        assert.equal(await page.evaluate(() => window.moduleRuns), 1);
        assert.equal(await page.locator('[data-rqbp-placement] script[type="text/plain"]').count(), 1);
        assert.equal(await page.locator('[data-rqbp-placement] script[type="application/json"]').count(), 1);
    });

    await test('replacing targets removes orphaned placements and initializes new targets once', async () => {
        await load('<script>window.executions=0;</script><main><div id="target">Old</div></main>', [rule(1, { html: '<script>window.executions++;</script><b>Ad</b>' })]);
        await page.evaluate(() => {
            const target = document.createElement('div');
            target.id = 'target';
            target.textContent = 'Replacement';
            document.getElementById('target').replaceWith(target);
        });
        await changed('main');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 1);
        assert.equal(await page.evaluate(() => window.executions), 2);
        await changed('main');
        assert.equal(await page.evaluate(() => window.executions), 2);
    });

    await test('new AJAX content is handled while already connected ads stay attached', async () => {
        await load('<script>window.executions=0;</script><main><div class="slot">A</div></main>', [rule(1, { selector: '.slot', occurrence: 'all', html: '<script>window.executions++;</script>' })]);
        await page.evaluate(() => {
            window.existingPlacement = document.querySelector('[data-rqbp-placement]');
            const node = document.createElement('div');
            node.className = 'slot';
            node.id = 'new';
            document.querySelector('main').append(node);
        });
        await changed('#new');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 2);
        assert.equal(await page.evaluate(() => window.executions), 2);
        assert.equal(await page.evaluate(() => window.existingPlacement === document.querySelector('[data-rqbp-placement]')), true);
    });

    await test('a burst of content changes uses one selector snapshot without duplicates', async () => {
        await load('<main><div class="slot">A</div></main>', [rule(1, { selector: '.slot', occurrence: 'all' })]);
        await page.evaluate(async () => {
            window.snapshotCount = 0;
            const root = document.documentElement;
            const clone = root.cloneNode;
            root.cloneNode = function (...args) {
                window.snapshotCount++;
                return clone.apply(this, args);
            };
            document.querySelector('main').insertAdjacentHTML('beforeend', '<div class="slot" id="burst">B</div>');
            for (let count = 0; count < 100; count++) {
                document.dispatchEvent(new CustomEvent('contentChange', { detail: { root: document.getElementById('burst') } }));
            }
            await window.rqBlockPlacements.whenIdle();
        });
        assert.equal(await page.evaluate(() => window.snapshotCount), 1);
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 2);
    });

    await test('deleted ads are not restarted against the same connected target', async () => {
        await load('<main><div id="target">A</div></main>', [rule(1)]);
        await page.locator('[data-rqbp-placement]').evaluate((node) => node.remove());
        await changed('main');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 0);
    });

    await test('invalid and missing selectors are isolated with safe diagnostics', async () => {
        await load('<main><div id="target">A</div></main>', [rule(1, { selector: '.missing' }), rule(2, { selector: '[' }), rule(3)], { diagnostics: [{ name: '<img src=x onerror="window.unsafeDiagnostic=true">', reason: 'Not allowed' }] });
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 1);
        const rows = await page.evaluate(() => window.rqBlockPlacements.getDiagnostics());
        assert.match(rows[0].reason, /No matching/);
        assert.match(rows[1].reason, /Invalid/);
        assert.equal(await page.locator('[data-rqbp-diagnostics] img').count(), 0);
        assert.equal(await page.evaluate(() => window.unsafeDiagnostic), undefined);
    });

    await test('unsupported table, SVG and phrasing contexts are skipped', async () => {
        await load('<main><table><tbody><tr id="row"><td>A</td></tr></tbody></table><p><span id="phrase">Text</span></p><svg><circle id="circle"></circle></svg><div id="target">Safe</div></main>', [rule(1, { selector: '#row' }), rule(2, { selector: '#phrase' }), rule(3, { selector: '#circle' }), rule(4)]);
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 1);
        assert.equal(await page.locator('table [data-rqbp-placement], p [data-rqbp-placement], svg [data-rqbp-placement]').count(), 0);
    });

    await test('a changed page URL removes old ads and requires a fresh server payload', async () => {
        await load('<script>window.order=[];</script><main><div class="slot">A</div></main>', [rule(1, { selector: '.slot', occurrence: 'all', html: '<script src="/block-script.js"></script>' })]);
        const initialRequests = scriptRequests;
        await page.evaluate(() => {
            history.pushState({}, '', '/different-page');
            document.querySelector('main').insertAdjacentHTML('beforeend', '<div class="slot" id="new">B</div>');
        });
        await changed('#new');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 0);
        assert.equal(scriptRequests, initialRequests);
        await page.evaluate(() => history.pushState({}, '', '/fixture'));
        await changed('main');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 0);
        assert.equal(scriptRequests, initialRequests);
    });

    await test('navigation aborts pending script waits and prevents remaining old inline code', async () => {
        const heldStarted = new Promise((resolve) => { signalHeldScript = resolve; });
        fixture = fixtureHtml('<main><div id="target">Target</div></main>', [rule(1, {
            html: '<script src="/held-script.js"></script><script>window.oldInlineRan=true;</script>'
        })]);
        try {
            await page.goto(`${origin}/fixture`, { waitUntil: 'domcontentloaded' });
            await heldStarted;
            const removedImmediately = await page.evaluate(() => {
                history.pushState({}, '', '/other-page');
                document.dispatchEvent(new CustomEvent('contentChange', { detail: { root: document.querySelector('main') } }));
                return !document.querySelector('[data-rqbp-placement]');
            });
            assert.equal(removedImmediately, true, 'The wrapper must be removed before the pending external response is released');
            await page.evaluate(() => Promise.race([
                window.rqBlockPlacements.whenIdle(),
                new Promise((resolve, reject) => setTimeout(() => reject(new Error('Stale script wait was not cancelled')), 1000))
            ]));
            assert.equal(await page.evaluate(() => window.oldInlineRan), undefined);
            heldScript.end('window.alreadyAttachedExternalRan=true;');
            heldScript = null;
            await page.waitForLoadState('load');
            await page.waitForTimeout(50);
            // Browsers may still execute an already attached external script.
            // The app only guarantees removal and no subsequent old activation.
            assert.equal(await page.evaluate(() => window.oldInlineRan), undefined);
            assert.equal(await page.locator('[data-rqbp-placement]').count(), 0);
        } finally {
            signalHeldScript = null;
            heldScript?.end('');
            heldScript = null;
        }
    });

    await test('preview counts targets without parsing or running block HTML', async () => {
        await load('<main><div id="target">A</div></main>', [rule(1, { html: '<img src=x onerror="window.previewRan=true"><script>window.previewRan=true;</script>' })], { diagnostic_only: true, diagnostics: [] });
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 0);
        assert.equal(await page.evaluate(() => window.previewRan), undefined);
        assert.equal((await page.evaluate(() => window.rqBlockPlacements.getDiagnostics()))[0].matches, 1);
    });

    await test('direct inline document.write scripts are skipped safely', async () => {
        await load('<main><div id="target">A</div></main>', [rule(1, { html: '<script>document.write("<h1>Destroy page</h1>");</script><b>Ad</b>' })]);
        assert.equal(await page.locator('#target').count(), 1);
        assert.equal(await page.locator('h1').count(), 0);
        assert.match((await page.evaluate(() => window.rqBlockPlacements.getDiagnostics()))[0].notes[0], /document.write/);
    });

    await test('the IPS jQuery contentChange contract mounts new content', async () => {
        const shim = '<script>window.jQuery=function(value){if(value===document){return{on:function(name,handler){window.jqHandler=handler;},trigger:function(name,args){window.jqHandler({type:name},...args);}};}const result=[value];result.jquery="test";return result;};</script>';
        await load(`${shim}<main><div class="slot">A</div></main>`, [rule(1, { selector: '.slot', occurrence: 'all' })]);
        await page.evaluate(async () => {
            document.querySelector('main').insertAdjacentHTML('beforeend', '<div class="slot" id="new">B</div>');
            window.jQuery(document).trigger('contentChange', [window.jQuery(document.getElementById('new'))]);
            await window.rqBlockPlacements.whenIdle();
        });
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 2);
    });

    await test('ACP CSS validation applies only to CSS mode and clears on mode changes', async () => {
        fixture = '<!doctype html><form data-rqbp-invalid="Neplatný selektor"><select name="rqbp_mode"><option value="css">CSS</option><option value="hook">Hook</option></select><input name="rqbp_selector" value="["><button type="submit">Save</button></form><script src="/admin.js"></script>';
        await page.goto(`${origin}/admin`);
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), false);
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.validationMessage), 'Neplatný selektor');
        await page.locator('[name=rqbp_mode]').selectOption('hook');
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), true);
        await page.locator('[name=rqbp_mode]').selectOption('css');
        await page.locator('[name=rqbp_selector]').fill('.valid:nth-child(2)');
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), true);
    });
} finally {
    await browser.close();
    await new Promise((resolve) => server.close(resolve));
}
console.log(`${passed} browser scenarios passed.`);
