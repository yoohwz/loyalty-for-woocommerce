const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
    const base = process.env.LOYF_BROWSER_URL;
    const browser = await chromium.launch({ headless: true, ...(process.env.LOYF_BROWSER_EXECUTABLE ? { executablePath: process.env.LOYF_BROWSER_EXECUTABLE } : {}) });
    try {
        const context = await browser.newContext(); const page = await context.newPage();
        await page.goto(base + '/wp-login.php');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.evaluate(() => {
            document.querySelector('#user_login').value = 'loyf_admin';
            document.querySelector('#user_pass').value = 'disposable-only';
            document.querySelector('#loginform').requestSubmit(document.querySelector('#wp-submit'));
        })]);
        if (page.url().includes('action=confirm_admin_email')) { await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.getByRole('link', { name: 'Remind me later' }).click()]); }
        if (page.url().includes('wp-login')) throw new Error('Native admin login: ' + page.url() + ' ' + (await page.locator('body').innerText()).slice(0,2000));
        await page.goto(base + '/wp-admin/admin.php?page=loyf-setup');
        try { await page.locator('[data-loyf-step="0"]').waitFor({ state: 'visible' }); } catch (e) { console.error('Onboarding browser page:', page.url(), (await page.locator('body').innerText()).slice(0,2400)); throw e; }
        if (['browser-exit','browser-blank'].includes(process.env.LOYF11_CASE)) {
            await page.locator('[data-loyf-next]').click();
            await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Exit Quick Start', exact: true }).click()]);
            assert.equal(await page.locator('#loyf-quick-start').count(),0);
            await page.reload(); assert.equal(await page.locator('#loyf-quick-start').count(),0);
            console.log('Shipped native onboarding Exit/refresh browser PASS');
            if (process.env.LOYF11_CASE==='browser-blank') {
                await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=loyalty&section=general');
                assert.equal(await page.locator('[name=loyalty_using_points]').inputValue(),'');assert.equal(await page.locator('[name=loyalty_using_amount]').inputValue(),'');
                await page.locator('[name="loyalty_earning_points[customer]"]').fill('7');await page.locator('#loyalty_points_rounding').selectOption('round_down');
                await page.locator('button[name=save]').click();await page.getByText('Your settings have been saved.',{exact:false}).waitFor();
                console.log('Shipped fresh skipped Quick Start native General Save with blank redemption PASS');
            }
            return;
        }
        const posts = []; page.on('request', r => { if (r.method() === 'POST') posts.push(r.url()); });
        for (let step = 0; step < 5; step++) {
            if (step === 1) {
                await page.locator('[name="earn_points"]').fill('1.5'); await page.locator('[data-loyf-next]').click();
                assert.equal(await page.locator('[data-loyf-step="1"]').isVisible(),true);
                assert.equal(await page.evaluate(()=>document.activeElement.name),'earn_points'); assert.deepEqual(posts,[]);
                await page.locator('[name="earn_points"]').fill('2'); await page.locator('[name="earn_amount"]').fill('5');
            }
            if (step === 2) { await page.locator('[name="redeem"]').check(); }
            if (step === 3) { await page.locator('[name="first"]').check(); await page.locator('[name="first_points"]').fill('1.5'); await page.locator('[name="referral"]').check(); await page.locator('[data-loyf-skip]').click(); }
            else await page.locator('[data-loyf-next]').click();
            await page.locator(`[data-loyf-step="${step + 1}"]`).waitFor({ state: 'visible' });
            assert.equal(await page.evaluate(() => document.activeElement.tagName), 'H2');
        }
        assert.deepEqual(posts, []); assert.match(await page.locator('[data-loyf-summary]').innerText(), /40 points/);
        assert.equal(await page.locator('[name="first"]').isChecked(), false); assert.equal(await page.locator('[name="referral"]').isChecked(), false);
        assert.equal(await page.locator('[name="first_points"]').isDisabled(),true); assert.equal(await page.locator('[name="referral_points"]').isDisabled(),true);
        await page.locator('[data-loyf-back]').click(); await page.locator('[data-loyf-next]').click(); assert.deepEqual(posts, []);
        await page.evaluate(()=>document.querySelector('[name="earn_points"]').value='1.5');
        await page.locator('[data-loyf-launch]').click();
        assert.equal(await page.locator('[data-loyf-step="1"]').isVisible(),true);
        assert.equal(await page.evaluate(()=>document.activeElement.name),'earn_points'); assert.deepEqual(posts,[]);
        await page.locator('[name="earn_points"]').fill('2');
        for(let i=1;i<5;i++)await page.locator('[data-loyf-next]').click();
        const stale = await context.newPage(); await stale.goto(base + '/wp-admin/admin.php?page=loyf-setup');
        const deniedGet = await context.request.get(base + '/wp-admin/admin-post.php?action=loyf_onboarding&intent=launch'); assert.equal(deniedGet.status(), 403);
        const deniedNonce = await context.request.post(base + '/wp-admin/admin-post.php', { form: { action: 'loyf_onboarding', intent: 'launch', _wpnonce: 'invalid' } }); assert.equal(deniedNonce.status(), 403);
        await Promise.all([page.waitForURL(u => u.searchParams.get('page') === 'loyf-setup'), page.locator('[data-loyf-launch]').click()]);
        await page.getByText('Quick Start completed.', { exact: false }).waitFor();
        assert.equal(await page.locator('#loyf-quick-start').count(), 0);
        // Exercise the shipped authoritative Woo screen before replaying a stale wizard tab.
        await page.goto(base + '/wp-admin/admin.php?page=wc-settings&tab=loyalty');
        await page.locator('[name="loyalty_earning_points[customer]"]').fill('7');
        await Promise.all([page.waitForNavigation(), page.locator('[name="save"]').click()]);
        for (let i = 0; i < 5; i++) await stale.locator('[data-loyf-next]').click();
        await Promise.all([stale.waitForNavigation(), stale.locator('[data-loyf-launch]').click()]);
        assert.equal(await stale.locator('#loyf-quick-start').count(), 0);
        const guest = await browser.newContext();
        const response = await guest.request.post(base + '/wp-admin/admin-post.php', { form: { action: 'loyf_onboarding', intent: 'launch' } });
        assert.notEqual(response.status(), 200);
        console.log('Shipped native onboarding Back/Next/Skip/Launch/denial/stale-tab browser PASS');
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
