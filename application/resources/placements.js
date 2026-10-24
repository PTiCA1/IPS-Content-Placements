/* Pages Block Placements | MIT | No dependency on the IPS JavaScript bundle. */
(function () {
    'use strict';

    if (window.rqBlockPlacements) {
        window.rqBlockPlacements.refresh(document);
        return;
    }

    const owned = '[data-rqbp-placement], [data-rqbp-diagnostics]';
    const seenPayloads = new WeakSet();
    let byTarget = new WeakMap();
    const placements = new Set();
    let payload = null;
    let payloadInvalidated = false;
    let work = Promise.resolve();
    let reports = [];
    let instance = 0;
    let jqueryBound = false;

    const currentUrl = () => window.location.pathname + window.location.search;
    const ownElement = (node) => node instanceof Element && Boolean(node.closest(owned));
    const number = (value, fallback = 0) => Number.isFinite(Number(value)) ? Number(value) : fallback;

    function ingest() {
        document.querySelectorAll('script[type="application/json"][data-rqbp-payload]').forEach((node) => {
            if (seenPayloads.has(node)) {
                return;
            }
            seenPayloads.add(node);
            try {
                const data = JSON.parse(node.textContent);
                if (!data || !Array.isArray(data.rules) || typeof data.page_url !== 'string') {
                    return;
                }
                if (data.page_url !== currentUrl()) {
                    return;
                }
                if (payload && (payload.page_url !== data.page_url || payloadInvalidated)) {
                    placements.forEach((record) => record.wrapper.remove());
                    placements.clear();
                    byTarget = new WeakMap();
                }
                payload = data;
                payloadInvalidated = false;
            } catch (error) {
                // A malformed payload must never stop the forum's own scripts.
                if (window.console) {
                    console.warn('Pages Block Placements: invalid JSON payload.', error);
                }
            }
        });
    }

    /* Select on a detached copy without our wrappers, so :nth-child(), :has(),
       sibling selectors, etc. keep their original meaning after an insertion. */
    function snapshot() {
        const clean = document.implementation.createHTMLDocument('');
        const clone = document.documentElement.cloneNode(true);
        const originals = new WeakMap();
        const stack = [[document.documentElement, clone]];
        while (stack.length) {
            const [original, copied] = stack.pop();
            if (original.matches(owned)) {
                copied.remove();
                continue;
            }
            originals.set(copied, original);
            const sourceChildren = original.children;
            const copiedChildren = Array.from(copied.children);
            for (let index = sourceChildren.length - 1; index >= 0; index--) {
                stack.push([sourceChildren[index], copiedChildren[index]]);
            }
        }
        clean.replaceChild(clone, clean.documentElement);
        return { clean, originals };
    }

    function rootsFrom(value) {
        if (!value || value === document || value === window) {
            return [document];
        }
        if (value instanceof Element || value instanceof DocumentFragment) {
            return ownElement(value) ? [] : [value];
        }
        if (value.jquery || Array.isArray(value) || value instanceof NodeList) {
            return Array.from(value).flatMap(rootsFrom);
        }
        if (value.content || value.newContent || value.root) {
            return rootsFrom(value.content || value.newContent || value.root);
        }
        return [document];
    }

    function relevant(target, roots) {
        return roots.some((root) => root === document || root === target || root.contains(target) || target.contains(root));
    }

    function prune() {
        placements.forEach((record) => {
            if (!record.target.isConnected || !record.wrapper.isConnected) {
                record.wrapper.remove();
                placements.delete(record);
                // Retain a tombstone while the target exists: a removed ad must
                // not be executed again by an unrelated contentChange event.
            }
        });
    }

    function wrapperTag(target) {
        const parent = target.parentElement;
        if (!parent || !document.body.contains(parent) || parent.namespaceURI !== 'http://www.w3.org/1999/xhtml') {
            return null;
        }
        if (/^(UL|OL|MENU)$/.test(parent.tagName)) {
            return target.tagName === 'LI' ? 'li' : null;
        }
        if (/^(TABLE|THEAD|TBODY|TFOOT|TR|COLGROUP|SELECT|OPTGROUP|DATALIST|SVG|MATH|HEAD|HTML|SCRIPT|STYLE|TEMPLATE|TEXTAREA|P|A|SPAN|LABEL|STRONG|EM|SMALL|B|I|U|S|CODE|PRE|BUTTON|LEGEND|SUMMARY|H[1-6])$/.test(parent.tagName)) {
            return null;
        }
        const display = window.getComputedStyle(parent).display;
        return display === 'inline' || display === 'inline-block' || display.startsWith('table') ? null : 'div';
    }

    function executable(script) {
        const type = (script.getAttribute('type') || '').trim().toLowerCase().split(';')[0];
        return !type || type === 'module' || /^(?:text|application)\/(?:java|ecma)script$/.test(type);
    }

    async function activateScripts(wrapper, report) {
        for (const inert of wrapper.querySelectorAll('script')) {
            if (!wrapper.isConnected || !executable(inert)) {
                continue;
            }
            if (!inert.src && /\bdocument\s*\.\s*write(?:ln)?\s*\(/.test(inert.textContent)) {
                report.notes.push('An inline document.write() script was skipped (unsupported).');
                continue;
            }
            const script = document.createElement('script');
            for (const attribute of inert.attributes) {
                script.setAttribute(attribute.name, attribute.value);
            }
            if (inert.nonce) {
                script.nonce = inert.nonce;
            }
            script.textContent = inert.textContent;
            const waits = Boolean(script.src) || script.type === 'module';
            let settled = Promise.resolve();
            if (waits) {
                // Dynamic classic scripts otherwise default to asynchronous.
                if (!script.hasAttribute('async')) {
                    script.async = false;
                }
                settled = new Promise((resolve) => {
                    const timer = window.setTimeout(() => {
                        report.notes.push('A block script did not finish loading within 15 seconds.');
                        resolve();
                    }, 15000);
                    const finish = (error) => {
                        window.clearTimeout(timer);
                        if (error) {
                            report.notes.push('A block script could not be loaded.');
                        }
                        resolve();
                    };
                    script.addEventListener('load', () => finish(false), { once: true });
                    script.addEventListener('error', () => finish(true), { once: true });
                });
            }
            inert.replaceWith(script);
            await settled;
        }
    }

    function informIps(wrapper) {
        if (window.jQuery) {
            window.jQuery(document).trigger('contentChange', [window.jQuery(wrapper)]);
        } else {
            document.dispatchEvent(new CustomEvent('contentChange', { detail: { root: wrapper } }));
        }
    }

    function diagnostics() {
        const previous = document.querySelector('[data-rqbp-diagnostics]');
        if (!payload || (!Array.isArray(payload.diagnostics) && !payload.debug)) {
            previous?.remove();
            return;
        }
        const strings = payload.strings || {};
        const panel = document.createElement('details');
        panel.setAttribute('data-rqbp-diagnostics', '');
        panel.className = 'ipsBox i-padding_3';
        const summary = document.createElement('summary');
        summary.textContent = strings.diagnostics || 'Pages Block Placements — diagnostics';
        panel.append(summary);
        const context = document.createElement('p');
        context.textContent = currentUrl() + (payload.diagnostic_only ? ' (preview only)' : '');
        panel.append(context);
        const list = document.createElement('ul');
        const all = [...(payload.diagnostics || []).map((row) => ({ ...row, notes: [] })), ...reports];
        all.forEach((row) => {
            const item = document.createElement('li');
            const counts = typeof row.matches === 'number' ? ` — ${strings.matches || 'matches'}: ${row.matches}, ${strings.inserted || 'inserted'}: ${row.inserted || 0}` : '';
            item.textContent = `${row.name || row.id || ''}${counts} — ${row.reason || row.status || 'ready'}`;
            (row.notes || []).forEach((note) => {
                item.append(document.createElement('br'), document.createTextNode(note));
            });
            list.append(item);
        });
        panel.append(list);
        if (previous) {
            panel.open = previous.open;
            previous.replaceWith(panel);
        } else {
            document.body.append(panel);
        }
    }

    async function scan(value) {
        bindJquery();
        ingest();
        prune();
        if (!payload) {
            return;
        }
        if (payload.page_url !== currentUrl()) {
            payloadInvalidated = true;
        }
        if (payloadInvalidated) {
            placements.forEach((record) => record.wrapper.remove());
            placements.clear();
            document.querySelector('[data-rqbp-diagnostics]')?.remove();
            // Returning to the same URL does not revive stale rendered HTML.
            // A new server payload is required after a page-context change.
            return;
        }
        const roots = rootsFrom(value);
        if (!roots.length) {
            return;
        }
        const { clean, originals } = snapshot();
        const groups = new Map();
        reports = [];
        const ordered = [...payload.rules].sort((a, b) => number(a.sort_order) - number(b.sort_order) || String(a.id).localeCompare(String(b.id), undefined, { numeric: true }));
        ordered.forEach((rule) => {
            const report = { id: rule.id, name: rule.name, matches: 0, inserted: 0, reason: rule.reason || 'ready', notes: [] };
            reports.push(report);
            let targets;
            try {
                if (typeof rule.selector !== 'string' || !rule.selector.trim()) {
                    throw new Error('empty selector');
                }
                targets = Array.from(clean.querySelectorAll(rule.selector), (node) => originals.get(node)).filter((node) => node && node.isConnected && !ownElement(node));
            } catch (error) {
                report.reason = 'Invalid or empty CSS selector.';
                return;
            }
            report.matches = targets.length;
            if (!targets.length) {
                report.reason = 'No matching element.';
                return;
            }
            if (rule.occurrence === 'nth' || rule.occurrence === 'index' || rule.occurrence === 'specific') {
                targets = targets.slice(Math.max(0, number(rule.occurrence_index, 1) - 1), Math.max(1, number(rule.occurrence_index, 1)));
            } else if (rule.occurrence !== 'all') {
                targets = targets.slice(0, 1);
            }
            if (!targets.length) {
                report.reason = 'The requested occurrence does not exist.';
            }
            targets.forEach((target) => {
                if (!relevant(target, roots)) {
                    return;
                }
                const tag = wrapperTag(target);
                if (!tag) {
                    report.reason = 'Unsupported HTML context.';
                    return;
                }
                if (payload.diagnostic_only || typeof rule.html !== 'string' || !rule.html.trim()) {
                    report.reason = rule.reason || (payload.diagnostic_only ? 'Preview only; block not inserted.' : 'The block has no available HTML.');
                    return;
                }
                if (!groups.has(target)) {
                    groups.set(target, { before: [], after: [] });
                }
                groups.get(target)[rule.position === 'before' ? 'before' : 'after'].push({ rule, target, tag, report });
            });
        });

        const added = [];
        function place(entry, cursor, position) {
            const { target, rule, tag, report } = entry;
            let records = byTarget.get(target);
            if (!records) {
                records = new Map();
                byTarget.set(target, records);
            }
            const key = String(rule.id);
            const existing = records.get(key);
            if (existing) {
                existing.notes.forEach((note) => {
                    if (!report.notes.includes(note)) {
                        report.notes.push(note);
                    }
                });
                if (existing.wrapper.isConnected) {
                    report.inserted++;
                    return existing.wrapper;
                }
                report.reason = 'Already initialized; removed placement was not restarted.';
                return cursor;
            }
            const wrapper = document.createElement(tag);
            wrapper.className = 'rqbp-placement';
            wrapper.setAttribute('data-rqbp-placement', key);
            wrapper.setAttribute('data-rqbp-instance', String(++instance));
            wrapper.setAttribute('data-rqbp-position', position);
            wrapper.innerHTML = rule.html;
            cursor.parentNode.insertBefore(wrapper, position === 'before' ? cursor : cursor.nextSibling);
            const record = { target, wrapper, notes: report.notes };
            records.set(key, record);
            placements.add(record);
            report.inserted++;
            added.push({ wrapper, report });
            return wrapper;
        }
        groups.forEach((group, target) => {
            let cursor = target;
            [...group.before].reverse().forEach((entry) => { cursor = place(entry, cursor, 'before'); });
            cursor = target;
            group.after.forEach((entry) => { cursor = place(entry, cursor, 'after'); });
        });
        // Nodes are in their final position before any executable code runs.
        // Activate in document order, including before-placement groups.
        added.sort((a, b) => a.wrapper.compareDocumentPosition(b.wrapper) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
        for (const { wrapper, report } of added) {
            if (wrapper.isConnected) {
                await activateScripts(wrapper, report);
                informIps(wrapper);
            }
        }
        diagnostics();
    }

    function refresh(value = document) {
        work = work.then(() => scan(value)).catch((error) => {
            if (window.console) {
                console.warn('Pages Block Placements: insertion was skipped.', error);
            }
        });
        return work;
    }

    function bindJquery() {
        if (jqueryBound || !window.jQuery) {
            return;
        }
        jqueryBound = true;
        window.jQuery(document).on('contentChange.rqblockplacements', (event, content) => {
            const value = content === undefined ? event.originalEvent?.detail || event.detail : content;
            if (rootsFrom(value).length) {
                refresh(value);
            }
        });
    }

    document.addEventListener('contentChange', (event) => {
        if (!rootsFrom(event.detail).length) {
            return;
        }
        refresh(event.detail || document);
    });

    window.rqBlockPlacements = {
        refresh,
        whenIdle: () => work,
        getDiagnostics: () => reports.map((row) => ({ ...row, notes: [...row.notes] }))
    };
    bindJquery();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => refresh(document), { once: true });
    } else {
        refresh(document);
    }
}());
