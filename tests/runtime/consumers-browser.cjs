const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
    const fixture = JSON.parse(fs.readFileSync(process.env.LOYF_BROWSER_FIXTURE, 'utf8'));
    const base = process.env.LOYF_BROWSER_URL;
    const browser = await chromium.launch({ headless:true, ...(process.env.LOYF_BROWSER_EXECUTABLE ? {executablePath:process.env.LOYF_BROWSER_EXECUTABLE} : {}) });
    let debugPage;
    try {
    const kinds = ['points-balance','points-history','level-progress','ways-to-earn','referral-link'];
    async function login(context, user) {
        const page = await context.newPage(); await page.goto(base + '/wp-login.php');
        await page.evaluate(user => { document.querySelector('#user_login').value=user; document.querySelector('#user_pass').value='disposable-only'; }, user);
        await Promise.all([page.waitForURL(url => !url.pathname.includes('wp-login')),page.locator('#wp-submit').click()]); return page;
    }
    const guest = await browser.newContext();
    const publicResponse = await guest.request.get(base + '/?page_id=' + fixture.page);
    const cachedHTML = await publicResponse.text();
    for (const secret of ['9007199254740993', fixture.token_a, fixture.token_b]) assert(!cachedHTML.includes(secret),'Cacheable HTML must be neutral');
    const guestPage = await guest.newPage(); await guestPage.goto(base + '/?page_id=' + fixture.page);
    await guestPage.locator('[data-loyf-component="points-balance"]').getByText(/Sign in to view/).waitFor();
    const guestPreview=await guest.request.get(base+'/?rest_route=/wp/v2/block-renderer/loyf/points-balance&context=edit&user_id='+fixture.user_a);assert(guestPreview.status()>=400,'Guest REST preview cannot read a customer');
    const denied = await guest.request.post(base + '/wp-admin/admin-post.php',{form:{action:'loyf_export_customers'}}); assert(denied.status()>=400,'Guest export denied');
    for (const [user, value, own, foreign] of [['consumer_a','9007199254740993.125',fixture.token_a,fixture.token_b],['consumer_b','0.875',fixture.token_b,fixture.token_a]]) {
        const context = await browser.newContext(); const page = await login(context,user);
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        // Replay exactly the same cached anonymous document to two authenticated viewers.
        await page.route('**/?page_id=' + fixture.page,route=>route.fulfill({status:200,contentType:'text/html; charset=UTF-8',body:cachedHTML}));
        await page.goto(base + '/?page_id=' + fixture.page + '&user_id=' + fixture.user_a);
        // Separate query URL above is also ordinary fresh PHP; next navigation exercises cached replay.
        await page.goto(base + '/?page_id=' + fixture.page);
        await page.locator('[data-loyf-component="points-balance"]').getByText(new RegExp(value.replace('.','\\.'))).waitFor();
        const referral = page.locator('[data-loyf-component="referral-link"]');
        await referral.getByLabel('Your referral link').waitFor();
        assert((await referral.getByLabel('Your referral link').inputValue()).includes(own));
        assert(!(await page.locator('.loyf-customer-component').allTextContents()).join('').includes(foreign));
        await referral.getByRole('button',{name:'Copy link',exact:true}).click(); await referral.getByRole('status').getByText(/Link copied|Select and copy/).waitFor();
        if(user==='consumer_a') assert((await page.locator('[data-loyf-component="points-balance"]').innerText()).includes('held'));
        assert((await page.locator('[data-loyf-component="points-history"]').getByRole('link').getAttribute('href')).includes('my-points'));
        const nonce = await (await context.request.get(base + '/wp-admin/admin-ajax.php?action=rest-nonce')).text();
        const privateResponse = await context.request.post(base + '/wp-admin/admin-ajax.php',{form:{action:'loyf_customer_components',nonce,'kinds[]':'points-balance',user_id:fixture.user_a}});
        assert.equal(privateResponse.status(),200);assert(privateResponse.headers()['cache-control'].includes('no-store'));
        assert((await privateResponse.json()).data['points-balance'].includes(value),'Private reader always uses current viewer');
        const customerPreview=await context.request.get(base+'/?rest_route=/wp/v2/block-renderer/loyf/points-balance&context=edit&user_id='+fixture.user_a,{headers:{'X-WP-Nonce':nonce}});assert(customerPreview.status()>=400,'Customer editor/REST permission boundary');
        const exportDenial = await context.request.post(base + '/wp-admin/admin-post.php',{form:{action:'loyf_export_customers',loyf_export_nonce:nonce}});assert.equal(exportDenial.status(),403,'Customer export denied');
        await page.setViewportSize({width:375,height:812});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'Responsive component layout');
        assert.equal(errors.length,0,'Shipped client browser exceptions');
        await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pagehide',{persisted:true})));
        const cleared=await page.locator('body').innerHTML();assert(!cleared.includes(value)&&!cleared.includes(own),'Back/forward snapshot clears private DOM');
        await context.close();
    }
    const admin = await browser.newContext({acceptDownloads:true}); const page = await login(admin,'loyf_admin');debugPage=page;
    await page.goto(base + '/wp-admin/post.php?post=' + fixture.editor + '&action=edit');
    await page.waitForFunction(()=>window.wp && wp.data.select('core/block-editor') && wp.blocks.getBlockType('loyf/points-balance'));
    const welcome = page.getByRole('button',{name:'Close',exact:true});if(await welcome.count()){await welcome.first().click();}
    // Native editor insertion, real dynamic preview, actual native save/publish.
    await page.evaluate(kinds=>wp.data.dispatch('core/block-editor').insertBlocks(kinds.map(kind=>wp.blocks.createBlock('loyf/'+kind))),kinds);
    await page.waitForFunction(()=>wp.data.select('core/block-editor').getBlocks().filter(block=>block.name.startsWith('loyf/')).length===5);
    const editorFrame = page.locator('iframe[name="editor-canvas"]');
    const editorSurface = await editorFrame.count() ? editorFrame.contentFrame() : page;
    await editorSurface.locator('[data-loyf-component]').first().waitFor();
    assert(!(await editorSurface.locator('[data-loyf-component]').allTextContents()).join('').includes('9007199254740993'),'Editor preview contains no customer value');
    await page.keyboard.press('Escape');await page.keyboard.press('Escape');
    const publish = page.getByRole('button',{name:'Publish',exact:true});await publish.first().click();
    const confirm = page.locator('.editor-post-publish-panel').getByRole('button',{name:'Publish',exact:true});
    await Promise.race([confirm.waitFor({state:'visible'}),page.waitForFunction(()=>wp.data.select('core/editor').getCurrentPost().status==='publish')]);
    if(await confirm.isVisible())await confirm.click();
    await page.waitForFunction(()=>wp.data.select('core/editor').getCurrentPost().status==='publish');
    const saved = await page.evaluate(()=>wp.data.select('core/editor').getCurrentPost().content);
    for(const kind of kinds)assert(saved.includes('wp:loyf/'+kind),'Saved native block '+kind);
    assert(!saved.includes(fixture.token_a)&&!saved.includes('9007199254740993'),'Saved content is cache-neutral');
    const editorNonce=await page.evaluate(()=>window.wpApiSettings.nonce);
    const preview=await admin.request.get(base+'/?rest_route=/wp/v2/block-renderer/loyf/points-balance&context=edit&user_id='+fixture.user_a,{headers:{'X-WP-Nonce':editorNonce}});
    assert.equal(preview.status(),200);assert(!(await preview.text()).includes('9007199254740993'),'REST preview ignores foreign user');
    await page.goto(base+'/wp-admin/admin.php?page=loyf-overview');await page.getByRole('heading',{name:'Loyalty overview',exact:true}).waitFor();assert((await page.locator('body').innerText()).includes('Known gross points redeemed'));
    await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=loyalty&section=tools');
    const nonce=await page.locator('[name="loyf_export_nonce"]').inputValue();
    for(const [method,form]of[['get',{action:'loyf_export_customers',loyf_export_nonce:nonce}],['post',{action:'loyf_export_customers',loyf_export_nonce:'bad'}]]){const response=await admin.request[method](base+'/wp-admin/admin-post.php',method==='get'?{params:form}:{form});assert.equal(response.status(),403,'Intentional POST and own nonce required');}
    const downloadPromise=page.waitForEvent('download');await page.getByRole('button',{name:'Download customer CSV',exact:true}).click();const download=await downloadPromise;assert.equal(download.suggestedFilename(),'loyalty-customers.csv');
    const csv=fs.readFileSync(await download.path(),'utf8');assert(csv.includes('9007199254740993.125'));assert(csv.includes('999999999999999999.500'));assert(csv.includes('"\t=1+1,""payload"""'),'Actual HTTP CSV formula quoting');assert(csv.trim().endsWith(',complete'),'Completed download terminal');
    assert(!csv.includes(fixture.token_a)&&!csv.includes('source_event_key'),'CSV privacy');
    const exported=await admin.request.post(base+'/wp-admin/admin-post.php',{form:{action:'loyf_export_customers',loyf_export_nonce:nonce}});assert(exported.headers()['content-type'].includes('text/csv; charset=UTF-8'));assert(exported.headers()['content-disposition'].includes('attachment'));assert(exported.headers()['cache-control'].includes('no-store'));
    const a=await browser.newContext();const customer=await login(a,'consumer_a');await customer.goto(base+'/?page_id='+fixture.editor);await customer.locator('[data-loyf-component="points-balance"]').getByText(/9007199254740993\.125/).waitFor();await a.close();
    await admin.close();await guest.close();console.log('Native editor/shortcode/cache/export browser PASS Woo'+fixture.version+' '+fixture.storage);
    } catch(error) { if(debugPage) { console.error((await debugPage.locator('body').innerText()).slice(0,5000));await debugPage.screenshot({path:'/tmp/loyf12-editor-failure.png',fullPage:true}); }throw error; } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
