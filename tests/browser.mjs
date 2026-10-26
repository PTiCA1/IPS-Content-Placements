import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import http from 'node:http';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
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
const serverFixture = (input) => JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [fileURLToPath(new URL('./server-fixture.php', import.meta.url))], { input: JSON.stringify(input), encoding: 'utf8' }));
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
function acpFixture(values = {}) {
    const positions = ['before', 'inside-start', 'inside-end', 'after'];
    const hooks = {
        'forums/front/index/forumRow|rowItem': positions,
        'forums/front/index/forumRow|rowHeading': ['before', 'after'],
        'forums/front/topics/topic|topicBody': ['inside-start', 'inside-end'],
        'forums/front/topics/topic|topicFooter': ['after'],
        'core/front/global/globalTemplate|body': ['inside-start', 'inside-end'],
        'core/front/global/globalTemplate|head': ['inside-start', 'inside-end'],
        'core/front/global/globalTemplate|main': positions
    };
    const options = (values) => '<option value="">—</option>' + values.map((value) => `<option value="${value}">${value}</option>`).join('');
    const config = { rqbp_mode: 'hook', rqbp_hook_app: '', rqbp_hook_template: '', rqbp_hook: '', rqbp_position: 'after', ...values };
    return `<!doctype html><form data-rqbp-hook-positions='${JSON.stringify(hooks)}'>
        <select name="rqbp_mode"><option value="hook">Hook</option><option value="css">CSS</option><option value="server">Server CSS</option></select>
        <select name="rqbp_hook_app">${options(['forums', 'core'])}</select>
        <select name="rqbp_hook_template"><option value="">—</option><optgroup label="Forums">${options(['forums/front/index/forumRow', 'forums/front/topics/topic']).replace('<option value="">—</option>', '')}</optgroup><optgroup label="Core">${options(['core/front/global/globalTemplate']).replace('<option value="">—</option>', '')}</optgroup></select>
        <select name="rqbp_hook">${options(Object.keys(hooks))}</select>
        <select name="rqbp_position">${positions.map((value) => `<option value="${value}">${value}</option>`).join('')}</select>
        <input name="rqbp_selector" value="#target"><button type="submit">Save</button>
        </form><script>for(const [name,value] of Object.entries(${encode(config)})){document.querySelector('[name="'+name+'"]').value=value;}</script><script src="/admin.js"></script>`;
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

    await test('all four positions preserve sort order and execute once on repeat scans', async () => {
        const positions = ['before', 'inside-start', 'inside-end', 'after'];
        const rules = positions.flatMap((position, index) => [
            rule(index * 2 + 1, { position, html: `<b>Ad</b><script>window.positionRuns=(window.positionRuns||0)+1;</script>` }),
            rule(index * 2 + 2, { position, html: `<b>Ad</b><script>window.positionRuns=(window.positionRuns||0)+1;</script>` })
        ]).reverse();
        await load('<main><section id="target"><p id="original">Original</p></section></main>', rules);
        assert.deepEqual(await page.locator('main > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || node.id)), ['1', '2', 'target', '7', '8']);
        assert.deepEqual(await page.locator('#target > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || node.id)), ['3', '4', 'original', '5', '6']);
        await changed('main');
        await changed('#target');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 8);
        assert.equal(await page.evaluate(() => window.positionRuns), 8);
        assert.deepEqual(await page.locator('#target > [data-rqbp-placement]').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement)), ['3', '4', '5', '6']);
    });

    await test('empty containers accept both inside positions in stable order', async () => {
        await load('<main><div id="target"></div></main>', [
            rule(4, { position: 'inside-end' }), rule(2, { position: 'inside-start' }),
            rule(3, { position: 'inside-end' }), rule(1, { position: 'inside-start' })
        ]);
        await changed('#target');
        assert.deepEqual(await page.locator('#target > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement)), ['1', '2', '3', '4']);
    });

    await test('new same-URL payloads add start rules in sorted order without restarting or moving old ads', async () => {
        const scripted = (id, order, position = 'inside-start') => rule(id, {
            position, sort_order: order,
            html: `<b>Ad ${id}</b><script>window.ruleRuns[${id}]=(window.ruleRuns[${id}]||0)+1;</script>`
        });
        for (const original of ['', '<p id="original">Original</p>']) {
            const rules = [scripted(1, 10), scripted(2, 30), scripted(9, 90, 'inside-end')];
            await load(`<script>window.ruleRuns={};</script><main><section id="target">${original}</section></main>`, rules);
            await page.evaluate(() => {
                window.oldPlacements = Array.from(document.querySelectorAll('[data-rqbp-placement]'));
                window.removedOldPlacements = [];
                window.placementObserver = new MutationObserver((changes) => {
                    for (const change of changes) {
                        for (const node of change.removedNodes) {
                            if (window.oldPlacements.includes(node)) {
                                window.removedOldPlacements.push(node.dataset.rqbpPlacement);
                            }
                        }
                    }
                });
                window.placementObserver.observe(document.getElementById('target'), { childList: true });
            });
            for (const [added, expected] of [
                [scripted(3, 50), ['1', '2', '3']],
                [scripted(4, 20), ['1', '4', '2', '3']],
                [scripted(5, 5), ['5', '1', '4', '2', '3']]
            ]) {
                rules.push(added);
                await page.evaluate(async (rules) => {
                    const data = document.createElement('script');
                    data.type = 'application/json';
                    data.setAttribute('data-rqbp-payload', '');
                    data.textContent = JSON.stringify({ page_url: '/fixture', rules });
                    document.body.append(data);
                    await window.rqBlockPlacements.refresh(document);
                }, rules);
                await changed('#target');
                assert.deepEqual(await page.locator('#target > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || node.id)), [...expected, ...(original ? ['original'] : []), '9']);
                assert.deepEqual(await page.evaluate(() => window.removedOldPlacements), []);
                assert.equal(await page.evaluate(() => window.oldPlacements.every((node) => node.isConnected)), true);
                assert.deepEqual(await page.evaluate(() => Object.values(window.ruleRuns)), rules.map(() => 1));
            }
            await page.evaluate(() => window.placementObserver.disconnect());
        }
    });

    await test('list and list-item wrappers depend on the actual insertion destination', async () => {
        const rules = [];
        let id = 0;
        for (const selector of ['#ul', '#ol', '#menu']) {
            for (const position of ['before', 'inside-start', 'inside-end', 'after']) {
                rules.push(rule(++id, { selector, position }));
            }
        }
        for (const position of ['before', 'inside-start', 'inside-end', 'after']) {
            rules.push(rule(++id, { selector: '#item', position }));
        }
        await load('<main><ul id="ul"><li id="item">Item</li></ul><ol id="ol"></ol><menu id="menu"></menu></main>', rules);
        for (const selector of ['#ul', '#ol', '#menu']) {
            assert.equal(await page.locator(`${selector} > [data-rqbp-placement]:not(li)`).count(), 0);
            assert.equal(await page.locator(`${selector} > [data-rqbp-placement]`).count(), selector === '#ul' ? 4 : 2);
        }
        assert.equal(await page.locator('#item > [data-rqbp-placement]:not(div)').count(), 0);
        assert.equal(await page.locator('#item > [data-rqbp-placement]').count(), 2);
        assert.equal(await page.locator('main > [data-rqbp-placement]:not(div)').count(), 0);
        await changed('main');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 16);
    });

    await test('body accepts only inside positions and rejects unsafe inside contexts', async () => {
        const rules = [rule(1, { selector: 'body', position: 'inside-start' }), rule(2, { selector: 'body', position: 'inside-end' }), rule(3, { selector: 'body', position: 'before' }), rule(4, { selector: 'body', position: 'after' })];
        let id = 4;
        for (const selector of ['html', 'head', 'title', '#script', '#textarea', '#select', '#table', '#cell', '#input', '#img', '#br', '#svg']) {
            for (const position of ['inside-start', 'inside-end']) {
                rules.push(rule(++id, { selector, position }));
            }
        }
        await load('<main><script id="script" type="text/plain">data</script><textarea id="textarea"></textarea><select id="select"><option>A</option></select><table id="table"><tbody><tr><td id="cell">Cell</td></tr></tbody></table><input id="input"><img id="img"><br id="br"><svg id="svg"></svg></main>', rules);
        await changed('body');
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 2);
        assert.deepEqual(await page.locator('body > [data-rqbp-placement]').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement)), ['1', '2']);
        assert.equal(await page.locator('body').evaluate((node) => node.firstElementChild.dataset.rqbpPlacement), '1');
        assert.equal(await page.locator('body').evaluate((node) => node.lastElementChild.dataset.rqbpPlacement), '2');
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

    await test('replacing targets initializes each of the four positions once', async () => {
        for (const position of ['before', 'inside-start', 'inside-end', 'after']) {
            await load('<script>window.executions=0;</script><main><div id="target">Old</div></main>', [rule(1, { position, html: '<script>window.executions++;</script><b>Ad</b>' })]);
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
        }
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

    await test('native ACP selects filter templates and hooks while enforcing valid positions', async () => {
        fixture = acpFixture();
        await page.goto(`${origin}/admin`);
        for (const name of ['rqbp_hook_app', 'rqbp_hook_template', 'rqbp_hook']) {
            assert.equal(await page.locator(`[name=${name}]`).inputValue(), '');
        }
        const visibleOptions = (name) => page.locator(`[name=${name}]`).evaluate((select) => Array.from(select.options).filter((option) => option.value && !option.hidden && !option.disabled).map((option) => option.value));
        await page.locator('[name=rqbp_hook_app]').selectOption('forums');
        assert.deepEqual(await visibleOptions('rqbp_hook_template'), ['forums/front/index/forumRow', 'forums/front/topics/topic']);
        assert.equal(await page.locator('[name=rqbp_hook_template]').inputValue(), 'forums/front/index/forumRow');
        assert.equal(await page.locator('[name=rqbp_hook]').inputValue(), 'forums/front/index/forumRow|rowItem');
        await page.locator('[name=rqbp_position]').selectOption('after');
        await page.locator('[name=rqbp_hook_app]').selectOption('core');
        assert.deepEqual(await visibleOptions('rqbp_hook_template'), ['core/front/global/globalTemplate']);
        assert.equal(await page.locator('[name=rqbp_hook]').inputValue(), 'core/front/global/globalTemplate|body');
        assert.deepEqual(await visibleOptions('rqbp_position'), ['inside-start', 'inside-end']);
        assert.equal(await page.locator('[name=rqbp_position]').inputValue(), 'inside-start');
        await page.locator('[name=rqbp_mode]').selectOption('css');
        assert.deepEqual(await visibleOptions('rqbp_position'), ['before', 'inside-start', 'inside-end', 'after']);
        await page.locator('[name=rqbp_position]').selectOption('after');
        await page.locator('[name=rqbp_mode]').selectOption('hook');
        assert.equal(await page.locator('[name=rqbp_position]').inputValue(), 'inside-start');
        await page.locator('[name=rqbp_hook_app]').selectOption('forums');
        await page.locator('[name=rqbp_hook_template]').selectOption('forums/front/topics/topic');
        assert.equal(await page.locator('[name=rqbp_hook]').inputValue(), 'forums/front/topics/topic|topicBody');
        assert.deepEqual(await visibleOptions('rqbp_hook'), ['forums/front/topics/topic|topicBody', 'forums/front/topics/topic|topicFooter']);
    });

    await test('native ACP selects preserve a valid existing template, hook and position', async () => {
        fixture = acpFixture({ rqbp_hook_app: 'forums', rqbp_hook_template: 'forums/front/topics/topic', rqbp_hook: 'forums/front/topics/topic|topicFooter', rqbp_position: 'after' });
        await page.goto(`${origin}/admin`);
        assert.equal(await page.locator('[name=rqbp_hook_template]').inputValue(), 'forums/front/topics/topic');
        assert.equal(await page.locator('[name=rqbp_hook]').inputValue(), 'forums/front/topics/topic|topicFooter');
        assert.equal(await page.locator('[name=rqbp_position]').inputValue(), 'after');
        assert.equal(await page.locator('[name=rqbp_position] option:not(:disabled)').count(), 1);
    });

    await test('native ACP exposes head and restricts its insertion type to inside positions', async () => {
        fixture = acpFixture({ rqbp_hook_app: 'core', rqbp_hook_template: 'core/front/global/globalTemplate', rqbp_hook: 'core/front/global/globalTemplate|head', rqbp_position: 'inside-end' });
        await page.goto(`${origin}/admin`);
        assert.equal(await page.locator('[name=rqbp_hook]').inputValue(), 'core/front/global/globalTemplate|head');
        assert.equal(await page.locator('[name=rqbp_position]').inputValue(), 'inside-end');
        assert.deepEqual(await page.locator('[name=rqbp_position]').evaluate((select) => Array.from(select.options).filter((option) => !option.disabled).map((option) => option.value)), ['inside-start', 'inside-end']);
    });

    await test('server-validated native head fragments preserve the browser head and execute once', async () => {
        const fragments = [
            '<meta name="rqbp-test" content="A > B"><link rel="alternate" href="/example">',
            '<script>window.headRuns=(window.headRuns||0)+1;window.headString="<div></head>";</script><style>.head-test{color:red}</style>',
            '<script type="application/json">{"example":"<div></head>"}</script>',
            '<!-- comment containing <div> --><meta name="comment-test" content="valid">',
            '<script>window.invalidHeadRan=true;</script><div>Invalid body content</div>',
            '<script><!--<script>example</script><meta name="unclosed-script" content="invalid">',
            '<script>const value="</script><div>Unexpected body</div>";</script>'
        ];
        const php = 'define("IPS\\\\SUITE_UNIQUE_KEY", "browser-test");require $argv[1];echo json_encode(array_map(static fn($html) => \\IPS\\rqblockplacements\\HeadContent::isValid($html) ? $html : "", json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR);';
        const accepted = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', ['-r', php, fileURLToPath(new URL('../application/sources/HeadContent/HeadContent.php', import.meta.url)), JSON.stringify(fragments)], { encoding: 'utf8' }));
        assert.deepEqual(accepted, [...fragments.slice(0, 4), '', '', '']);
        for (const position of ['inside-start', 'inside-end']) {
            const original = '<title>Original title</title><meta name="original" content="original">';
            const metadata = accepted.join('');
            fixture = `<!doctype html><html><head>${position === 'inside-start' ? metadata + original : original + metadata}<meta name="head-end" content="preserved"></head><body><main id="body-sentinel">Original body</main></body></html>`;
            await page.goto(`${origin}/native-head`);
            assert.equal(await page.locator('head > [name=head-end]').count(), 1);
            assert.equal(await page.locator('head > [name=rqbp-test]').getAttribute('content'), 'A > B');
            assert.equal(await page.locator('head > [name=original]').count(), 1);
            assert.equal(await page.locator('#body-sentinel').textContent(), 'Original body');
            assert.equal(await page.locator('body > *').count(), 1);
            assert.equal(await page.evaluate(() => window.headRuns), 1);
            assert.equal(await page.evaluate(() => window.headString), '<div></head>');
            assert.equal(await page.evaluate(() => window.invalidHeadRan), undefined);
            assert.equal(await page.locator('[data-rqbp-placement]').count(), 0);
        }
    });
    await test('server selectors agree with browser matching for structural forum selectors and comma unions', async () => {
        const content = '<ol id="categories" class="ipsBlockSpacer"><li id="category-1"><ol class="ipsData--category"><li id="forum-1" class="row selected" data-label="a,b">A</li><li id="forum-2" class="row">B</li></ol></li><li id="category-2"><ol class="ipsData--category"><li id="forum-3">C</li></ol></li><li id="category-3"><ol class="ipsData--category"><li id="forum-4">D</li></ol></li></ol><ol class="ipsData--topic-list"><li id="topic-1">A</li><li id="topic-2">B</li></ol><div id="entries" class="ipsEntries--topic"><span id="anchor-1"></span><article id="post-1" class="ipsEntry" data-ips-hook="postWrapper">A</article><span id="anchor-2"></span><article id="post-2" class="ipsEntry" data-ips-hook="postWrapper">B</article></div>';
        const selectors = [
            '#ipsLayout__main', '#ipsLayout__main .ipsBlockSpacer > li:nth-child(3)',
            '#ipsLayout__main .ipsData--category > li:nth-child(1)',
            '#ipsLayout__main .ipsData--topic-list > li:nth-child(2)',
            '#ipsLayout__main .ipsEntries--topic .ipsEntry[data-ips-hook="postWrapper"]:nth-of-type(1)',
            '#category-3, #forum-1, #forum-1', '#ipsLayout__main [data-label="a,b"], #topic-2',
            '#ipsLayout__main .row.selected', '#ipsLayout__main [data-ips-hook]',
            '#ipsLayout__main #category-1 + li', '#ipsLayout__main #category-1 ~ li',
            '#ipsLayout__main li:first-child', '#ipsLayout__main li:last-child',
            '#ipsLayout__main li:only-child', '#ipsLayout__main article:first-of-type',
            '#ipsLayout__main article:last-of-type', '#ipsLayout__main ol:only-of-type',
            '#ipsLayout__main li:not(.selected)', '#ipsLayout__main [id^="post-"]',
            '#ipsLayout__main [class~="row"]', '#ipsLayout__main [id$="-2"]'
        ];
        selectors.push(selectors.slice(1, 5).join(','));
        for (const argument of ['odd', 'even', 'n', '2n+1', '2n + 10', '-n+2', '-2n+3', '0n+2', '0', '-1']) {
            for (const pseudo of ['nth-child', 'nth-of-type', 'nth-last-child', 'nth-last-of-type']) {
                selectors.push(`#ipsLayout__main #entries > *:${pseudo}(${argument})`);
            }
        }
        const matched = serverFixture({ operation: 'select', html: content, selectors });
        fixture = `<!doctype html><html><head><title>Server selectors</title></head><body><div id="ipsLayout__main">${content}</div></body></html>`;
        await page.goto(`${origin}/server-selectors`);
        const native = await page.evaluate((selectors) => selectors.map((selector) => Array.from(document.querySelectorAll(selector), (node) => node.id || node.tagName.toLowerCase())), selectors);
        assert.deepEqual(matched, native);
    });

    await test('server placement exists in the initial HTML before downstream JavaScript without the insertion runtime', async () => {
        const original = '<ol class="ipsBlockSpacer"><li>A</li><li>B</li><li id="category-3">C</li></ol><script>window.originalString="<div></head>";</script>';
        const ad = '<b id="server-ad">Server advertisement</b><script>window.serverRuns=(window.serverRuns||0)+1;window.serverOwnWrapper=document.currentScript.closest("[data-rqbp-placement]").tagName;</script><script type="application/json">{"example":"<div>"}</script>';
        const result = serverFixture({ html: original, rules: [rule(1, { selector: '#ipsLayout__main .ipsBlockSpacer > li:nth-child(3)', html: ad })] });
        assert.equal(result.reports[0].inserted, 1);
        assert.ok(result.html.includes(ad));
        assert.ok(result.html.includes('<script>window.originalString="<div></head>";</script>'));
        fixture = `<!doctype html><html><head><title>Server insertion</title></head><body><div id="ipsLayout__main">${result.html}</div><script>window.downstreamFound=Boolean(document.querySelector("#server-ad"));window.downstreamRuns=window.serverRuns;</script></body></html>`;
        const response = await page.goto(`${origin}/server-placement`);
        assert.ok((await response.text()).includes('id="server-ad"'));
        assert.equal(await page.evaluate(() => window.downstreamFound), true);
        assert.equal(await page.evaluate(() => window.downstreamRuns), 1);
        assert.equal(await page.evaluate(() => window.serverOwnWrapper), 'LI');
        assert.equal(await page.evaluate(() => window.originalString), '<div></head>');
        assert.equal(await page.evaluate(() => window.rqBlockPlacements), undefined);
        assert.equal(await page.locator('#category-3 + li[data-rqbp-placement="1"]').count(), 1);
        assert.equal(await page.locator('[data-rqbp-payload]').count(), 0);
    });

    await test('server and browser rules retain original structural targets without rerunning server scripts', async () => {
        const original = '<ol id="categories"><li id="category-1">A</li><li id="category-2">B</li><li id="category-3">C</li></ol>';
        const server = serverFixture({ html: original, rules: [rule(101, { selector: '#category-2', position: 'before', html: '<b>Server</b><script>window.serverRuns=(window.serverRuns||0)+1;</script>' })] });
        await load(`<div id="ipsLayout__main">${server.html}</div>`, [rule(201, { selector: '#categories > li:nth-child(3)' })]);
        await changed('#categories');
        await changed('#categories');
        assert.equal(await page.locator('#category-3 + [data-rqbp-placement="201"]').count(), 1);
        assert.equal(await page.locator('[data-rqbp-placement="101"] + #category-2').count(), 1);
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 2);
        assert.equal(await page.evaluate(() => window.serverRuns), 1);
    });

    await test('server insertion preserves all four positions, context wrappers and sorted rule order', async () => {
        const original = '<ol><li id="target"><span id="original">Original</span></li></ol>';
        const rules = ['before', 'inside-start', 'inside-end', 'after'].flatMap((position, index) => [
            rule(index * 2 + 1, { position }), rule(index * 2 + 2, { position })
        ]).reverse();
        const result = serverFixture({ html: original, rules });
        assert.equal(result.reports.reduce((sum, report) => sum + report.inserted, 0), 8);
        fixture = `<!doctype html><html><head><title>Server order</title></head><body><div id="ipsLayout__main">${result.html}</div></body></html>`;
        await page.goto(`${origin}/server-order`);
        assert.deepEqual(await page.locator('ol > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || node.id)), ['1', '2', 'target', '7', '8']);
        assert.deepEqual(await page.locator('#target > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || node.id)), ['3', '4', 'original', '5', '6']);
        assert.equal(await page.locator('ol > [data-rqbp-placement]:not(li)').count(), 0);
        assert.equal(await page.locator('#target > [data-rqbp-placement]:not(div)').count(), 0);
    });

    await test('native-style pagination controls do not prevent server placement beside topic posts', async () => {
        const pagination = '<ul class="pagination"><li><a href="#page-1">1</a></li><input type="number" name="page" value="1"><button type="button">Go</button></ul>';
        const content = `${pagination}<form data-role="moderationTools"><span id="anchor"></span><article id="post-1" data-ips-hook="postWrapper">First</article><article id="post-2" data-ips-hook="postWrapper">Second</article></form>${pagination}`;
        const selector = '#ipsLayout__main form > article[data-ips-hook="postWrapper"]:nth-of-type(1)';
        const result = serverFixture({ html: content, rules: [rule(1, { selector })] });
        assert.equal(result.reports[0].matches, 1);
        assert.equal(result.reports[0].inserted, 1);
        assert.equal(result.html.split(pagination).length, 3, 'Both original pagination fragments remain byte-identical');
        fixture = `<!doctype html><html><head><title>Topic pagination</title></head><body><div id="ipsLayout__main">${result.html}</div></body></html>`;
        await page.goto(`${origin}/server-topic-pagination`);
        assert.equal(await page.locator(selector).count(), 1);
        assert.equal(await page.locator('#post-1 + [data-rqbp-placement="1"]').count(), 1);
        assert.equal(await page.locator('ul.pagination > input[name="page"]').count(), 2);
        assert.equal(await page.locator('ul.pagination > button').count(), 2);
        assert.equal(await page.locator('[data-rqbp-placement]').count(), 1);
    });

    await test('Pages article selectors tolerate duplicate attributes and omitted metadata list-item closers', async () => {
        const card = (index) => `<article class="ipsData__item" id="record-${index}"><a class="record-image" href="#record-${index}" ARIA-hidden="true" aria-hidden="false" tabindex="-1" tabindex="0">Image</a><ul class="record-metadata"><li class="author">Example author</li><li class="rating">Rating</ul></article>`;
        const content = `<div class="ipsData--cms-category-articles">${[1, 2, 3, 4].map(card).join('')}</div>`;
        const selector = '#ipsLayout__main .ipsData--cms-category-articles > article.ipsData__item:nth-child(3)';
        const selectors = [selector, '#ipsLayout__main .record-image[aria-hidden="true"][tabindex="-1"]', '#ipsLayout__main .record-image[aria-hidden="false"]', '#ipsLayout__main .record-metadata > li:nth-child(2)'];
        const matched = serverFixture({ operation: 'select', html: content, selectors });
        fixture = `<!doctype html><html><head><title>Pages records</title></head><body><div id="ipsLayout__main">${content}</div></body></html>`;
        await page.goto(`${origin}/server-pages-original`);
        const native = await page.evaluate((selectors) => selectors.map((selector) => Array.from(document.querySelectorAll(selector), (node) => node.id || node.tagName.toLowerCase())), selectors);
        assert.deepEqual(matched, native);
        const result = serverFixture({ html: content, rules: [rule(1, { selector, html: '<b id="pages-ad">Advertisement</b><script>window.pagesAdRuns=(window.pagesAdRuns||0)+1;</script>' })] });
        assert.equal(result.reports[0].matches, 1);
        assert.equal(result.reports[0].inserted, 1);
        assert.ok(result.html.includes(card(3)), 'The original third article remains byte-identical');
        fixture = `<!doctype html><html><head><title>Pages placement</title></head><body><div id="ipsLayout__main">${result.html}</div><script>window.pagesAdFound=Boolean(document.getElementById("pages-ad"));</script></body></html>`;
        await page.goto(`${origin}/server-pages-placement`);
        assert.equal(await page.locator('#record-3 + [data-rqbp-placement="1"]').count(), 1);
        assert.equal(await page.locator('.ipsData--cms-category-articles > article').count(), 4);
        assert.equal(await page.evaluate(() => window.pagesAdFound), true);
        assert.equal(await page.evaluate(() => window.pagesAdRuns), 1);
    });

    await test('all insertion positions beside implicitly closed list items preserve browser nesting and order', async () => {
        const content = '<ul id="list"><li id="first">First<li id="last">Last</ul>';
        const rules = ['before', 'inside-start', 'inside-end', 'after'].flatMap((position, index) => [
            rule(index * 2 + 1, { selector: '#first', position }), rule(index * 2 + 2, { selector: '#first', position })
        ]).reverse();
        const result = serverFixture({ html: content, rules });
        assert.equal(result.reports.reduce((total, report) => total + report.inserted, 0), 8);
        fixture = `<!doctype html><html><head><title>Optional list closing</title></head><body><div id="ipsLayout__main">${result.html}</div></body></html>`;
        await page.goto(`${origin}/server-optional-list`);
        assert.deepEqual(await page.locator('#list > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement || node.id)), ['1', '2', 'first', '7', '8', 'last']);
        assert.deepEqual(await page.locator('#first > *').evaluateAll((nodes) => nodes.map((node) => node.dataset.rqbpPlacement)), ['3', '4', '5', '6']);
        assert.equal(await page.locator('#list > [data-rqbp-placement]:not(li)').count(), 0);
        assert.equal(await page.locator('#first > [data-rqbp-placement]:not(div)').count(), 0);
    });

    await test('ACP server CSS retains all positions and browser syntax validation independently of native hooks', async () => {
        fixture = acpFixture({ rqbp_mode: 'server', rqbp_hook_app: 'core', rqbp_hook_template: 'core/front/global/globalTemplate', rqbp_hook: 'core/front/global/globalTemplate|head', rqbp_position: 'after' });
        await page.goto(`${origin}/admin`);
        assert.equal(await page.locator('[name=rqbp_position]').inputValue(), 'after');
        assert.equal(await page.locator('[name=rqbp_position] option:not(:disabled)').count(), 4);
        await page.locator('[name=rqbp_selector]').fill('[');
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), false);
        await page.locator('[name=rqbp_mode]').selectOption('hook');
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), true);
        assert.equal(await page.locator('[name=rqbp_position] option:not(:disabled)').count(), 2);
        await page.locator('[name=rqbp_mode]').selectOption('server');
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), false);
        await page.locator('[name=rqbp_selector]').fill('#ipsLayout__main li:nth-child(3), article[data-ips-hook="postWrapper"]:nth-of-type(1)');
        assert.equal(await page.locator('[name=rqbp_selector]').evaluate((node) => node.checkValidity()), true);
    });
} finally {
    await browser.close();
    await new Promise((resolve) => server.close(resolve));
}
console.log(`${passed} browser scenarios passed.`);
