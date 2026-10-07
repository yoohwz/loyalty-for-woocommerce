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
    page.on('request', request => { if (request.method()==='POST' && /cart(?:%2F|\/)extensions/.test(request.url())) updates.push(JSON.parse(request.postData()).data); });
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (request.method()==='POST') calls.push(request.url()); });
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
            await surface.getByLabel('Points to apply').fill('20');
            if (target === fixture.cart) {
                await page.route(/cart(?:%2F|\/)extensions/, async route => { await route.fetch(); await route.abort('failed'); }, { times:1 });
            }
            await surface.getByRole('button', { name:'Apply points', exact:true }).click();
            if (target === fixture.cart) {
                await surface.getByRole('button', { name:'Retry points update', exact:true }).click();
                assert.deepEqual(updates[0], updates[1], 'Lost response retries original immutable UUID and terms');
            }
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].some(node => /20 points applied/.test(node.textContent)));
            await surface.getByRole('button', { name:'Remove points', exact:true }).click();
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].every(node => !/20 points applied/.test(node.textContent)));
        }
        assert(calls.some(url => url.includes('cart%2Fextensions') || url.includes('cart/extensions')), 'Shipped client must use native Store API extension updates');
        assert(!calls.some(url => url.includes('admin-ajax.php')), 'Blocks must not send Classic AJAX');
        assert.equal(errors.length, 0, 'Native browser exceptions: ' + errors.join('; '));
        console.log(`Shipped Blocks Cart/Checkout browser PASS Woo${fixture.version} ${fixture.storage}`);
    } catch(error) { await page.screenshot({path:process.env.LOYF_BROWSER_SCREENSHOT || '/tmp/loyf-blocks-browser-failure.png',fullPage:true}); throw error; }
    finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
