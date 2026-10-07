const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
    const fixture = JSON.parse(fs.readFileSync(process.env.LOYF_BROWSER_FIXTURE, 'utf8'));
    const base = process.env.LOYF_BROWSER_URL;
    const browser = await chromium.launch({ headless:true, ...(process.env.LOYF_BROWSER_EXECUTABLE ? { executablePath:process.env.LOYF_BROWSER_EXECUTABLE } : {}) });
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(base + '/wp-login.php');
        await page.evaluate(() => { document.querySelector('#user_login').value = 'blocks_browser'; document.querySelector('#user_pass').value = 'disposable-only'; });
        await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login')), page.locator('#wp-submit').click()]);
        await page.goto(base + '/?add-to-cart=' + fixture.product);
        for (const [kind, id] of [['cart',fixture.cart], ['checkout',fixture.checkout]]) {
            await page.goto(base + '/?page_id=' + id);
            await page.waitForFunction(() => window.wp && window.wc && window.wc.wcBlocksData && window.wp.data.select(window.wc.wcBlocksData.CART_STORE_KEY).getCartData().extensions?.['loyf-redemption']);
            const data = await page.evaluate(() => window.wp.data.select(window.wc.wcBlocksData.CART_STORE_KEY).getCartData().extensions['loyf-redemption']);
            assert.equal(data.enabled, false, 'Earning-only store has no redemption');
            assert(data.earned > 0, 'Earning rules remain enabled');
            const expected = process.env['LOYF_SHOW_EARNED_' + kind.toUpperCase()] === '1';
            assert.equal(data['show_earned_' + kind], expected, 'Persisted page preference');
            const message = page.locator('.loyf-blocks-redemption p').filter({ hasText:/You will earn \d+ points with this purchase\./ });
            if (expected) await message.waitFor({ state:'visible' });
            else {
                await page.locator('.wc-block-components-totals-wrapper').first().waitFor();
                await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
                assert.equal(await message.count(), 0, 'Disabled page has no earning message');
                assert.equal(await page.locator('.loyf-blocks-redemption').count(), 0, 'No empty earning-only panel');
            }
            assert.equal(await page.getByLabel('Points to apply').count(), 0, 'No redemption input in earning-only store');
            assert.equal(await page.getByRole('button', { name:'Apply points', exact:true }).count(), 0, 'No redemption action in earning-only store');
        }
        assert.equal(errors.length, 0, errors.join('; '));
        console.log(`Shipped Blocks earning-only display ${process.env.LOYF_SHOW_EARNED_CART}:${process.env.LOYF_SHOW_EARNED_CHECKOUT} PASS Woo${fixture.version} ${fixture.storage}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
