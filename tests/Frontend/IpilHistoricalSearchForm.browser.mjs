// Synthetic request-boundary fixture: real Index.vue, controls, Wayfinder and Inertia.
// No Laravel database, credentials, or rescued records are used.
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../../', import.meta.url));
const path = '/staff/ipil-history';
const entry = '/gate8c-form-test.js';
const categories = {
    businesses: ['Synthetic Business', { id: 1, name: 'Synthetic Business' }],
    owners: ['Synthetic Owner', { id: 2, name: 'Synthetic Owner' }],
    applications: [
        'TEST-APP-01',
        {
            id: 3,
            source_application_number: 'TEST-APP-01',
            business_name: 'Synthetic Business',
        },
    ],
    permits: [
        'TEST-PERMIT-01',
        { id: 4, permit_number: 'TEST-PERMIT-01', application_id: 3 },
    ],
    receipts: [
        'TEST-OR-01',
        { id: 5, receipt_number: 'TEST-OR-01', application_id: 3 },
    ],
};
function payload(url) {
    const filters = Object.fromEntries(url.searchParams);
    const next = new URL(url);
    next.searchParams.set('page', '2');
    return {
        component: 'ipil-history/Index',
        url: url.pathname + url.search,
        version: 'synthetic-form-test',
        props: {
            errors: {},
            filters,
            businesses: {
                data: [],
                from: 1,
                to: 20,
                total: 40,
                links: [
                    {
                        url: next.pathname + next.search,
                        label: 'Next »',
                        active: false,
                    },
                ],
            },
            matches: Object.fromEntries(
                Object.entries(categories).map(([key, [query, row]]) => [
                    key,
                    filters.q === query ? [row] : [],
                ]),
            ),
            options: {
                years: [2026],
                types: ['Renewal'],
                statuses: ['Released'],
                barangays: ['TEST BARANGAY'],
            },
            anchors: { owners: 0, businesses: 0 },
        },
    };
}
const driver = `
import { createApp, h, nextTick } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Index from '/resources/js/pages/ipil-history/Index.vue';
await createInertiaApp({page: JSON.parse(document.querySelector('#app').dataset.page), resolve: () => Index, setup: ({el, App, props, plugin}) => createApp({render: () => h(App, props)}).use(plugin).mount(el)});
const results = [];
const check = (ok, name) => { if (!ok) throw new Error(name); results.push(name); };
const until = async (fn) => { for (let n=0;n<150;n++) { if(fn()) return; await new Promise(r=>setTimeout(r,20)); } throw new Error('request/state timeout'); };
const set = async (selector, value) => { const el=document.querySelector(selector); el.value=value; el.dispatchEvent(new Event(el.tagName==='SELECT'?'change':'input',{bubbles:true})); await nextTick(); await nextTick(); };
const submit = () => document.querySelector('button[type=submit]').click();
const query = () => new URLSearchParams(location.search);
try {
 await until(()=>document.querySelector('#history_q'));
 for (const [kind, [value]] of Object.entries(${JSON.stringify(categories)})) {
   await set('#history_q',value);
   check(new FormData(document.querySelector('form')).get('q')===value,kind+' native GET field is named');
   submit();
   await until(()=>query().get('q')===value && document.querySelector('main').textContent.includes(value));
   check(query().get('q')===value, kind+' query travels through visible submit');
   check(document.querySelector('main').textContent.includes(value),kind+' response rendered');
 }
 const selects=[...document.querySelectorAll('form select')];
 for (const [index,value] of ['2026','Renewal','Released','TEST BARANGAY'].entries()) { selects[index].value=value; selects[index].dispatchEvent(new Event('change',{bubbles:true})); }
 await set('input[placeholder="Source classification"]','TEST CLASS');
 const nativeFields=new FormData(document.querySelector('form'));
 check(nativeFields.get('year')==='2026' && nativeFields.get('type')==='Renewal' && nativeFields.get('status')==='Released' && nativeFields.get('barangay')==='TEST BARANGAY' && nativeFields.get('classification')==='TEST CLASS','native GET retains all filter fields');
 submit();
 await until(()=>query().get('classification')==='TEST CLASS');
 for (const [key,value] of Object.entries({year:'2026',type:'Renewal',status:'Released',barangay:'TEST BARANGAY',classification:'TEST CLASS'})) check(query().get(key)===value,key+' preserved');
 await set('select[aria-label="Sort businesses"]','owner');
 await until(()=>query().get('sort')==='owner');
 await set('select[aria-label="Sort direction"]','desc');
 await until(()=>query().get('direction')==='desc');
 check(query().get('q')==='TEST-OR-01'&&query().get('year')==='2026','sort preserves query and filters');
 document.querySelector('nav[aria-label="Business directory pages"] a').click();
 await until(()=>query().get('page')==='2');
 check(query().get('q')==='TEST-OR-01'&&query().get('sort')==='owner'&&query().get('direction')==='desc'&&query().get('year')==='2026','pagination preserves active state');
 await set('#history_q','Synthetic Business'); submit();
 await until(()=>query().get('q')==='Synthetic Business');
 check(!query().has('page'),'new submission resets page');
 [...document.querySelectorAll('button')].find(b=>b.textContent.includes('Clear')).click();
 await until(()=>location.search==='' && document.querySelector('#history_q').value==='');
 await nextTick();
 check(document.querySelector('#history_q').value==='','Clear resets visible query');
 check([...document.querySelectorAll('form select')].every(s=>s.value===''),'Clear resets filters');
 check(document.querySelector('input[placeholder="Source classification"]').value==='','Clear resets classification');
 check(document.querySelector('select[aria-label="Sort businesses"]').value==='name'&&document.querySelector('select[aria-label="Sort direction"]').value==='asc','Clear resets sort defaults');
 submit(); await new Promise(r=>setTimeout(r,300));
 check(!query().has('q')&&!query().has('year'),'submit after Clear does not resurrect old values');
 document.body.dataset.testStatus='passed';
} catch(error) { document.body.dataset.testStatus='failed'; results.push(error.message); }
const output=document.createElement('pre'); output.id='test-results'; output.textContent=JSON.stringify({status:document.body.dataset.testStatus,checks:results}); document.body.append(output);
`;
const server = await createServer({
    root,
    configFile: false,
    logLevel: 'error',
    server: { host: '127.0.0.1', port: 8878, strictPort: true },
    resolve: { alias: { '@': resolve(root, 'resources/js') } },
    plugins: [
        {
            name: 'synthetic-ipil-form-fixture',
            enforce: 'pre',
            resolveId(id) {
                if (id === entry) return entry;
            },
            load(id) {
                if (id === entry) return driver;
                if (id === resolve(root, 'resources/js/layouts/AppLayout.vue'))
                    return '<template><slot /></template>';
            },
            configureServer(server) {
                server.middlewares.use((req, res, next) => {
                    const url = new URL(req.url, 'http://127.0.0.1:8878');
                    if (url.pathname !== path) return next();
                    const page = payload(url);
                    if (req.headers['x-inertia']) {
                        res.setHeader('Content-Type', 'application/json');
                        res.setHeader('X-Inertia', 'true');
                        res.end(JSON.stringify(page));
                        return;
                    }
                    res.setHeader('Content-Type', 'text/html');
                    res.end(
                        '<!doctype html><html><body><div id="app" data-page="' +
                            JSON.stringify(page)
                                .replaceAll('&', '&amp;')
                                .replaceAll('"', '&quot;') +
                            '"></div><script type="module" src="' +
                            entry +
                            '"></script></body></html>',
                    );
                });
            },
        },
        vue(),
    ],
});
await server.listen();
if (process.argv.includes('--serve')) {
    console.log(
        'Synthetic form regression fixture: http://127.0.0.1:8878/staff/ipil-history',
    );
} else {
    let browser;
    try {
        browser = await chromium.launch();
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.goto('http://127.0.0.1:8878' + path);
        await page.waitForSelector('#test-results', { timeout: 30000 });
        const result = JSON.parse(
            await page.locator('#test-results').textContent(),
        );
        assert.equal(result.status, 'passed', JSON.stringify(result));
        assert.deepEqual(errors, []);
        console.log(JSON.stringify(result));
    } finally {
        await browser?.close();
        await server.close();
    }
}
