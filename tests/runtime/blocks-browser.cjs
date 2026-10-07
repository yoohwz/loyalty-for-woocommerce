const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
    const fixture = JSON.parse(fs.readFileSync(process.env.LOYF_BROWSER_FIXTURE, 'utf8'));
    const base = process.env.LOYF_BROWSER_URL;
    const browser = await chromium.launch({ headless: true, ...(process.env.LOYF_BROWSER_EXECUTABLE ? {executablePath:process.env.LOYF_BROWSER_EXECUTABLE}: {}) });
    const page = await browser.newPage();
    page.setDefaultTimeout(30000);
    const calls = [], errors = [], updates = [];
    function extensionRequests(request) {
        if (request.method() !== 'POST') return [];
        let payload; try { payload = JSON.parse(request.postData()); } catch (_) { return []; }
        const entries = payload.requests || [{ path:decodeURIComponent(request.url()), body:payload }];
        return entries.filter(entry => entry.path.includes('cart/extensions')).map(entry => entry.body.data);
    }
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (request.method()==='POST') calls.push(request.url()); updates.push(...extensionRequests(request)); });
    try {
        await page.goto(base + '/wp-login.php');
        await page.locator('#user_login').fill('blocks_browser');
        await page.locator('#user_pass').fill('disposable-only');
        await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login')), page.locator('#wp-submit').click()]);
        await page.goto(base + '/?add-to-cart=' + fixture.product);
        for (const target of [fixture.cart, fixture.checkout]) {
            await page.goto(base + '/?page_id=' + target);
            const surface = page.locator('.loyf-blocks-redemption');
            await surface.waitFor({ state:'visible', timeout:30000 });
            assert.match(await surface.innerText(), /Available points: 50/);
            await surface.getByLabel('Points to apply').fill('9999');
            await surface.getByRole('button', { name:'Apply points', exact:true }).click();
            await surface.getByRole('alert').waitFor();
            await surface.getByRole('button', { name:'Apply points', exact:true }).waitFor();
            await surface.getByLabel('Points to apply').fill('20');
            const replayStart = updates.length;
            if (target === fixture.cart) {
                let lost = false;
                await page.route('**/*', async route => {
                    if (!lost && extensionRequests(route.request()).length) { lost = true; await route.fetch(); await route.abort('failed'); }
                    else await route.continue();
                });
            }
            await surface.getByRole('button', { name:'Apply points', exact:true }).click();
            if (target === fixture.cart) {
                await surface.getByRole('button', { name:'Retry points update', exact:true }).click();
            }
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].some(node => /20 points applied/.test(node.textContent)));
            if (target === fixture.cart) { assert(updates.length >= replayStart + 2, 'Native extension batch observed'); assert.deepEqual(updates[replayStart], updates[replayStart + 1], 'Lost response retries original immutable UUID and terms'); }
            await surface.getByRole('button', { name:'Remove points', exact:true }).click();
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].every(node => !/20 points applied/.test(node.textContent)));
        }
        const denied = await page.evaluate(async () => {
            const response = await fetch('/?rest_route=/wc/store/v1/cart/extensions', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({namespace:'loyf-redemption',data:{action:'apply',points:'20',operation_id:crypto.randomUUID()}})});
            return response.status;
        });
        assert(denied >= 400, 'Native browser transport requires Store API nonce');
        const guest = await browser.newContext();
        const anonymous = await guest.request.get(base + '/?rest_route=/wc/store/v1/cart');
        const guestData = (await anonymous.json()).extensions['loyf-redemption'];
        assert.equal(guestData.enabled, false); assert.equal(guestData.available, 0); assert.equal(guestData.operation_id, '');
        assert.deepEqual(Object.keys(guestData).sort(), ['available','discount','earned','enabled','message','minimum','operation_id','selected']);
        await guest.close();
        assert(updates.length >= 5, 'Shipped client must use native Store API extension updates, including native batching');
        assert(!calls.some(url => url.includes('admin-ajax.php')), 'Blocks must not send Classic AJAX');
        assert.equal(errors.length, 0, 'Native browser exceptions: ' + errors.join('; '));
        console.log(`Shipped Blocks Cart/Checkout browser PASS Woo${fixture.version} ${fixture.storage}`);
    } catch(error) { await page.screenshot({path:process.env.LOYF_BROWSER_SCREENSHOT || '/tmp/loyf-blocks-browser-failure.png',fullPage:true}); throw error; }
    finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
