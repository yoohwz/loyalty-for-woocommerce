const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
    const fixture = JSON.parse(fs.readFileSync(process.env.LOYF_BROWSER_FIXTURE, 'utf8'));
    const base = process.env.LOYF_BROWSER_URL;
    const browser = await chromium.launch({ headless: true, ...(process.env.LOYF_BROWSER_EXECUTABLE ? {executablePath:process.env.LOYF_BROWSER_EXECUTABLE}: {}) });
    const page = await browser.newPage({viewport:{width:1280,height:900}});
    page.setDefaultTimeout(30000);
    await page.addInitScript(() => { window.crypto.randomUUID = undefined; });
    const calls = [], errors = [], updates = [];
    function extensionRequests(request) {
        if (request.method() !== 'POST') return [];
        let payload; try { payload = JSON.parse(request.postData()); } catch (_) { return []; }
        const entries = payload.requests || [{ path:decodeURIComponent(request.url()), body:payload }];
        return entries.filter(entry => entry.path.includes('cart/extensions')).map(entry => entry.body.data);
    }
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (request.method()==='POST') calls.push(request.url()); updates.push(...extensionRequests(request)); });
    async function presentation(kind, width) {
        // Hydrate each native responsive layout at its target size. Woo9.9.5 already
        // loses its DiscountsMeta fill on a live Checkout breakpoint switch (base too).
        if (page.viewportSize().width !== width) {
            await page.setViewportSize({width, height:900});
            await page.reload({waitUntil:'domcontentloaded'});
        }
        const surface = page.locator('.loyf-blocks-redemption');
        await surface.waitFor({state:'visible'});
        await page.waitForFunction(() => !document.querySelector('.wp-block-woocommerce-cart.is-loading, .wp-block-woocommerce-checkout.is-loading'));
        await page.waitForFunction(() => [...document.querySelectorAll('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value')].some(node=>node.getBoundingClientRect().width>0 && /[0-9]/.test(node.textContent)));
        // Appearance measures hydrated Woo controls with the real theme font loaded.
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await surface.count(), 1, 'One Loyalty group after responsive rerender');
        await page.getByRole('button', {name:/^Add (a coupon|coupons)$/}).click();
        await page.getByLabel('Enter code', {exact:true}).fill('appearance-only');
        await surface.getByLabel('Points to apply').fill('');
        await surface.getByText('Loyalty points', {exact:true}).click();
        const appearance = await page.evaluate(() => {
            const group = document.querySelector('.loyf-blocks-redemption');
            const input = group.querySelector('input');
            const coupon = document.querySelector('.wc-block-components-totals-coupon input');
            const apply = group.querySelector('button');
            const nativeButton = document.querySelector('.wc-block-components-totals-coupon__button');
            const skin = node => { const css = getComputedStyle(node); return Object.fromEntries(['color','backgroundColor','borderRadius','fontFamily','fontSize','borderTopWidth','borderTopStyle','borderTopColor'].map(key => [key,css[key]])); };
            const visible = node => node.getBoundingClientRect().width>0;
            // Woo11.1 omits a redundant Cart subtotal; its native total has the same inset.
            const label = [...document.querySelectorAll('.wc-block-components-totals-item__label')].find(node=>node.textContent==='Subtotal' && visible(node)) || [...document.querySelectorAll('.wc-block-components-totals-footer-item .wc-block-components-totals-item__label')].find(visible);
            const row = label.closest('.wc-block-components-totals-item');
            const rect = input.getBoundingClientRect();
            return {input:skin(input), coupon:skin(coupon), button:skin(apply), nativeButton:skin(nativeButton), left:rect.left, expectedLeft:label.getBoundingClientRect().left, right:rect.right, expectedRight:row.getBoundingClientRect().right - parseFloat(getComputedStyle(row).paddingRight), overflow:document.documentElement.scrollWidth > innerWidth, inlineStyles:[...group.querySelectorAll('input[style],button[style]')].map(node=>node.getAttribute('style')), inlineSkin:[...group.querySelectorAll('input,button')].some(node=>['color','background','background-color','border','border-radius','padding','font','font-size','box-shadow','width','height'].some(property=>node.style.getPropertyValue(property))), nativeInput:input.parentElement.classList.contains('wc-block-components-text-input'), nativeButtonClass:apply.classList.contains('wc-block-components-button')};
        });
        assert.deepEqual(appearance.input, appearance.coupon, 'Input inherits native coupon appearance');
        assert.deepEqual(appearance.button, appearance.nativeButton, 'Button inherits native coupon appearance');
        assert(Math.abs(appearance.left-appearance.expectedLeft)<2, 'Loyalty left edge matches native totals: ' + JSON.stringify(appearance));
        assert(appearance.right<=appearance.expectedRight+2, 'Loyalty controls stay inside totals right edge');
        assert(!appearance.overflow && !appearance.inlineSkin && appearance.nativeInput && appearance.nativeButtonClass, JSON.stringify(appearance));
        // Native Tab order reaches Apply without submitting Checkout or a coupon.
        await surface.getByLabel('Points to apply').focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(()=>document.activeElement.textContent), 'Apply points');
        assert.notEqual(await surface.getByRole('button',{name:'Apply points',exact:true}).evaluate(node=>getComputedStyle(node).outlineStyle), 'none', 'Native keyboard focus is visible');
        await page.getByLabel('Enter code', {exact:true}).fill('');
        await page.getByRole('button', {name:/^Add (a coupon|coupons)$/}).click();
        if (process.env.LOYF_BROWSER_EVIDENCE) {
            fs.mkdirSync(process.env.LOYF_BROWSER_EVIDENCE,{recursive:true});
            await page.screenshot({path:process.env.LOYF_BROWSER_EVIDENCE + '/' + (process.env.LOYF_BROWSER_THEME || 'default') + '-' + kind + '-' + width + '.png',fullPage:true});
        }
        console.log('Native presentation ' + kind + ' ' + width + ' ' + JSON.stringify(appearance));
    }
    try {
        // Native login redirects directly into this fixture's Cart, adding its product once.
        await page.goto(base + '/wp-login.php?redirect_to=' + encodeURIComponent(base + '/?page_id=' + fixture.cart + '&add-to-cart=' + fixture.product), {waitUntil:'domcontentloaded'});
        // WordPress schedules delayed username focus; set both fixture fields atomically.
        await page.evaluate(() => { document.querySelector('#user_login').value = 'blocks_browser'; document.querySelector('#user_pass').value = 'disposable-only'; });
        await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login'), {waitUntil:'domcontentloaded'}), page.locator('#wp-submit').click()]);
        for (const target of [fixture.cart, fixture.checkout]) {
            // Navigate to the clean page URL so reload cannot replay add-to-cart.
            // The first desktop sample hydrates here; only a breakpoint
            // change needs the retained native reload before measuring the next sample.
            await page.setViewportSize({width:1280,height:900});
            await page.goto(base + '/?page_id=' + target, {waitUntil:'domcontentloaded'});
            const surface = page.locator('.loyf-blocks-redemption');
            await surface.waitFor({ state:'visible', timeout:30000 });
            await surface.getByText(/Available points: 50/).waitFor();
            assert.match(await surface.innerText(), /Available points: 50/);
            assert(!/You will earn/.test(await surface.innerText()), 'Disabled earning messages must not hide redemption');
            await presentation(target === fixture.cart ? 'cart' : 'checkout',1280);
            await presentation(target === fixture.cart ? 'cart' : 'checkout',390);
            const originalTotal = await page.locator('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value').filter({visible:true}).innerText();
            await surface.getByRole('button', {name:'Apply points',exact:true}).press('Enter');
            assert.match(await surface.getByRole('alert').innerText(), /Enter a whole points amount/);
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
            const applyResponse = page.waitForResponse(response => extensionRequests(response.request()).some(data => data.action === 'apply' && data.points === '20'));
            await surface.getByRole('button', { name:'Apply points', exact:true }).click();
            if (target === fixture.cart) {
                assert.equal(await surface.getByLabel('Points to apply').isEnabled(),false,'Retained request freezes input terms');
                await surface.getByRole('button', { name:'Retry points update', exact:true }).click();
            }
            assert.equal(await (await applyResponse).finished(), null, 'Apply replay response completed');
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].some(node => /20 points applied/.test(node.textContent)));
            await surface.getByRole('button', { name:'Apply points', exact:true }).waitFor();
            if (target === fixture.cart) { assert(updates.length >= replayStart + 2, 'Native extension batch observed'); assert.deepEqual(updates[replayStart], updates[replayStart + 1], 'Lost response retries original immutable UUID and terms'); }
            assert.notEqual(await page.locator('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value').filter({visible:true}).innerText(), originalTotal, 'Apply changes native totals');
            assert.equal(await surface.count(),1, 'Native cart update keeps one group');
            await page.reload({waitUntil:'domcontentloaded'});
            await surface.waitFor({ state:'visible' });
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].some(node => /20 points applied/.test(node.textContent)));
            assert.equal(await surface.getByLabel('Points to apply').inputValue(), '', 'Fresh selected page starts with an empty input');
            const removeStart = updates.length; let lostRemove = false;
            await page.route('**/*', async route => {
                if (!lostRemove && extensionRequests(route.request()).some(data => data.action === 'remove')) { lostRemove = true; await route.fetch(); await route.abort('failed'); }
                else await route.continue();
            });
            const removeResponse = page.waitForResponse(response => extensionRequests(response.request()).some(data => data.action === 'remove'));
            await surface.getByRole('button', { name:'Remove points', exact:true }).press('Enter');
            await surface.getByRole('button', { name:'Retry points update', exact:true }).click();
            assert.equal(await (await removeResponse).finished(), null, 'Remove replay response completed');
            await page.waitForFunction(() => [...document.querySelectorAll('.loyf-blocks-redemption')].every(node => !/20 points applied/.test(node.textContent)));
            await surface.getByRole('button', { name:'Apply points', exact:true }).waitFor();
            assert(lostRemove && updates.length >= removeStart + 2, 'Lost remove response replay observed');
            assert.deepEqual(updates[removeStart], updates[removeStart + 1], 'Empty input must replay the original remove request');
            assert.equal(await page.locator('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value').filter({visible:true}).innerText(), originalTotal, 'Remove restores native totals');
        }
        // Each denied-state actor owns a separate session/cart; no shared value writes.
        await Promise.all(['zero','low','held'].map(async state => {
            const context = await browser.newContext();
            const actor = await context.newPage();
            await actor.goto(base + '/wp-login.php?redirect_to=' + encodeURIComponent(base + '/?page_id=' + fixture.cart + '&add-to-cart=' + fixture.product), {waitUntil:'domcontentloaded'});
            // Keep the retained native login's atomic fields: WP schedules username focus.
            await actor.evaluate(state => { document.querySelector('#user_login').value='blocks_browser_' + state; document.querySelector('#user_pass').value='disposable-only'; },state);
            await Promise.all([actor.waitForURL(url=>!url.pathname.includes('wp-login'), {waitUntil:'domcontentloaded'}),actor.locator('#wp-submit').click()]);
            for (const target of [fixture.cart,fixture.checkout]) {
                await actor.goto(base + '/?page_id=' + target, {waitUntil:'domcontentloaded'});
                const group = actor.locator('.loyf-blocks-redemption');
                await group.waitFor({state:'visible'});
                if (state === 'held') {
                    assert.match(await group.getByRole('status').innerText(), /Points are unavailable/);
                    assert.equal(await group.locator('input,button').count(),0, 'Held balance has no mutation controls');
                } else {
                    await group.getByText(new RegExp('Available points: ' + (state==='zero' ? 0 : 5))).waitFor();
                    assert.match(await group.innerText(), new RegExp('Available points: ' + (state==='zero' ? 0 : 5)));
                    await group.getByLabel('Points to apply').fill('10');
                    await group.getByRole('button',{name:'Apply points',exact:true}).click();
                    await group.getByRole('alert').waitFor();
                    assert.equal(await group.getByRole('button',{name:'Remove points',exact:true}).count(),0, 'Insufficient selection is never displayed as applied');
                }
            }
            await context.close();
        }));
        const denied = await page.evaluate(async () => {
            const response = await fetch('/?rest_route=/wc/store/v1/cart/extensions', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({namespace:'loyf-redemption',data:{action:'apply',points:'20',operation_id:'00000000-0000-4000-8000-000000000001'}})});
            return response.status;
        });
        assert(denied >= 400, 'Native browser transport requires Store API nonce');
        const guest = await browser.newContext();
        const anonymous = await guest.request.get(base + '/?rest_route=/wc/store/v1/cart');
        const guestData = (await anonymous.json()).extensions['loyf-redemption'];
        assert.equal(guestData.enabled, false); assert.equal(guestData.available, 0); assert.equal(guestData.operation_id, '');
        assert.equal(guestData.show_earned_cart, false); assert.equal(guestData.show_earned_checkout, false);
        assert.deepEqual(Object.keys(guestData).sort(), ['available','discount','earned','enabled','message','minimum','operation_id','selected','show_earned_cart','show_earned_checkout']);
        const guestPage = await guest.newPage();
        await guestPage.goto(base + '/?add-to-cart=' + fixture.product, {waitUntil:'domcontentloaded'});
        for (const target of [fixture.cart,fixture.checkout]) {
            await guestPage.goto(base + '/?page_id=' + target, {waitUntil:'domcontentloaded'});
            await guestPage.locator('.wc-block-components-totals-footer-item').filter({visible:true}).waitFor();
            await guestPage.waitForFunction(() => !document.querySelector('.wp-block-woocommerce-cart.is-loading, .wp-block-woocommerce-checkout.is-loading'));
            await guestPage.waitForFunction(() => [...document.querySelectorAll('.wc-block-components-totals-footer-item .wc-block-components-totals-item__value')].some(node=>node.getBoundingClientRect().width>0 && /[0-9]/.test(node.textContent)));
            assert.equal(await guestPage.locator('.loyf-blocks-redemption').count(),0, 'Guest has no Loyalty controls');
        }
        await guest.close();
        assert(updates.length >= 5, 'Shipped client must use native Store API extension updates, including native batching');
        assert(!calls.some(url => url.includes('admin-ajax.php')), 'Blocks must not send Classic AJAX');
        assert.equal(errors.length, 0, 'Native browser exceptions: ' + errors.join('; '));
        console.log(`Shipped Blocks Cart/Checkout browser PASS Woo${fixture.version} ${fixture.storage}`);
    } catch(error) { await page.screenshot({path:process.env.LOYF_BROWSER_SCREENSHOT || '/tmp/loyf-blocks-browser-failure.png',fullPage:true}); throw error; }
    finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
