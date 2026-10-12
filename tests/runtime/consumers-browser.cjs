const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
    const fixture = JSON.parse(fs.readFileSync(process.env.LOYF_BROWSER_FIXTURE, 'utf8'));
    const base = process.env.LOYF_BROWSER_URL;
    const browser = await chromium.launch({ headless:true, ...(process.env.LOYF_BROWSER_EXECUTABLE ? {executablePath:process.env.LOYF_BROWSER_EXECUTABLE} : {}) });
    let debugPage;
    try {
    function versionAtLeast(actual,minimum){const a=actual.split('.').map(Number),b=minimum.split('.').map(Number);return a[0]>b[0]||(a[0]===b[0]&&a[1]>=b[1]);}
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
        for(const slug of ['loyf-overview','loyf-settings','loyf-emails']){const denied=await context.request.get(base+'/wp-admin/admin.php?page='+slug);assert.equal(denied.status(),403,'Customer admin access denied');}
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
    await page.goto(base+'/wp-admin/admin.php?page=loyf-overview');await page.getByRole('heading',{name:'Loyalty overview',exact:true}).waitFor();await page.getByRole('heading',{name:'Known gross points redeemed',exact:true}).waitFor();
    assert.equal(await page.locator('#toplevel_page_loyf-overview').count(),1,'One top-level Loyalty menu');
    assert.equal(await page.locator('#toplevel_page_loyf-overview .wp-submenu a').allTextContents().then(items=>items.filter(item=>item==='Overview').length),1,'One Overview submenu');
    assert.equal(await page.locator('#toplevel_page_woocommerce .wp-submenu a[href*="loyf-overview"]').count(),0,'No duplicate Woo Overview');
    assert.equal(await page.locator('.loyf-overview-card').count(),5);
    await page.getByLabel('Flow period',{exact:true}).selectOption('30');await page.getByRole('button',{name:'View',exact:true}).click();assert.equal(new URL(page.url()).searchParams.get('period'),'30');
    await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile overview has no overflow');
    await page.goto(base+'/wp-admin/admin.php?page=loyf-settings');assert.equal(new URL(page.url()).searchParams.get('tab'),'loyalty','Settings shortcut reaches existing Woo form');
    assert.equal(await page.locator('#mainform').count(),1,'One native settings form');
    assert(await page.evaluate(()=>getComputedStyle(document.querySelector('.yoswc-premium-preview-card')).borderInlineStartColor===getComputedStyle(document.querySelector('#adminmenu a.wp-has-current-submenu')).backgroundColor),'Preview follows native admin scheme');
    assert.equal(await page.getByRole('navigation',{name:'Loyalty settings sections'}).getByRole('link').count(),7,'All Free sections retained');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile General settings has no overflow');
    const rounding=page.locator('#loyalty_points_rounding');const original=await rounding.inputValue();const changed=original==='round_up'?'round_down':'round_up';
    await rounding.selectOption(changed);await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[name=save]').click()]);await page.getByText(/Your settings have been saved|General earning and level settings were saved/).waitFor();await page.reload();assert.equal(await rounding.inputValue(),changed,'Native Woo save/reload persists');
    await rounding.selectOption(original);await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[name=save]').click()]);await page.getByText(/Your settings have been saved|General earning and level settings were saved/).waitFor();
    assert.equal(await page.locator('.loyf-admin-header a[href*="loyf-overview"]').count(),0,'No Overview button on Settings');
    await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=loyalty&section=extra_points');
    assert.equal(await page.locator('[name=loyalty_extra_first_purchase_enabled]').count(),0);
    assert(await page.evaluate(()=>getComputedStyle(document.querySelector('.yoswc-contextual-premium-card')).borderInlineStartColor===getComputedStyle(document.querySelector('#adminmenu a.wp-has-current-submenu')).backgroundColor),'Extra points card follows scheme');
    const first=page.locator('#loyalty_extra_first_purchase_points');const firstOriginal=await first.inputValue();
    for(const amount of ['29','0',firstOriginal]) {
        await first.fill(amount);await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[name=save]').click()]);await page.reload();assert.equal(await first.inputValue(),amount,'Amount-only First purchase save/reload');
    }
    for(const section of ['extra_points','referrals','customization','notification','tools','premium']) {
        await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=loyalty&section='+section);
        assert.equal(await page.locator('#mainform form').count(),0,'No nested form '+section);
        assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile Settings '+section);
    }
    await page.getByRole('navigation',{name:'Loyalty settings sections'}).getByRole('link',{name:'Notification',exact:true}).click();
    await page.getByRole('link',{name:'Manage loyalty emails',exact:true}).click();await page.locator('.loyf-loyalty-emails').waitFor();
    assert.equal(await page.locator('.loyf-loyalty-emails tbody tr').count(),3,'Only three Free emails');
    assert.deepEqual(await page.locator('.loyf-loyalty-emails strong').allTextContents(),['Points earned','Points deducted','Level update']);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Mobile Loyalty emails');
    assert(await page.evaluate(()=>[...document.querySelectorAll('.loyf-loyalty-emails .button')].every(e=>e.getBoundingClientRect().right<=innerWidth)),'Mobile Manage buttons are not clipped');
    for(const label of ['Points earned','Points deducted','Level update']) {
        await page.getByRole('link',{name:'Manage '+label,exact:true}).click();
        const enabled=page.locator('input[id$="_enabled"]');const subject=page.locator('input[id$="_subject"]');
        const wasEnabled=await enabled.isChecked();const oldSubject=await subject.inputValue();
        await enabled.setChecked(!wasEnabled);await subject.fill('LOYF-33 '+label);
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[name=save]').click()]);await page.reload();
        assert.equal(await enabled.isChecked(),!wasEnabled);assert.equal(await subject.inputValue(),'LOYF-33 '+label,'Native email subject save/reload');
        await page.getByRole('link',{name:'Back to Loyalty emails',exact:true}).click();await page.locator('.loyf-loyalty-emails').waitFor();
        const row=page.locator('.loyf-loyalty-emails tbody tr').filter({has:page.getByText(label,{exact:true})});assert.equal(await row.getAttribute('data-loyf-status'),wasEnabled?'Disabled':'Enabled','Group shows actual native enablement');
        await page.getByRole('link',{name:'Manage '+label,exact:true}).click();await enabled.setChecked(wasEnabled);await subject.fill(oldSubject);
        await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('button[name=save]').click()]);await page.reload();assert.equal(await enabled.isChecked(),wasEnabled);assert.equal(await subject.inputValue(),oldSubject,'Native email restore');
        await page.getByRole('link',{name:'Back to Loyalty emails',exact:true}).click();await page.locator('.loyf-loyalty-emails').waitFor();
    }
    await page.getByRole('navigation',{name:'Email groups'}).getByRole('link',{name:'General',exact:true}).click();
    if(versionAtLeast(fixture.version,'9.9')) {
        const nativeListing=page.locator('#wc_settings_email_listing_slotfill');await nativeListing.waitFor();
        assert(await page.getByRole('navigation',{name:'Email groups'}).evaluate(nav=>Boolean(nav.compareDocumentPosition(document.querySelector('#wc_settings_email_listing_slotfill'))&Node.DOCUMENT_POSITION_FOLLOWING)),'Group navigation precedes native block listing');
        const types=JSON.parse(await nativeListing.getAttribute('data-email-types'));assert(types.length>3);assert(!types.some(email=>email.id.startsWith('yowcl_loyalty_')),'Native block General excludes only three Free emails');
        const templateURL=await nativeListing.getAttribute('data-edit-template-url');assert(templateURL&&templateURL.includes('post.php?post='),'Native template editing URL retained');
        const editTemplate=page.getByText('Edit template',{exact:true});await editTemplate.first().waitFor();await Promise.all([page.waitForURL(url=>url.pathname.endsWith('/post.php')),editTemplate.first().click()]);assert(page.url().includes('template='),'Native template editor reachable');
    } else { await page.locator('table.loyf-general-emails').waitFor();assert(await page.locator('table.loyf-general-emails tbody tr').count()>3);assert.equal(await page.locator('table.loyf-general-emails a[href*=yowcl_wc_email_loyalty]').count(),0,'General contains ordinary emails only'); }

    await page.goto(base+'/wp-admin/admin.php?page=loyf-emails');assert.equal(new URL(page.url()).searchParams.get('email_group'),'loyalty','Emails submenu redirects to Loyalty group');
    await page.goto(base+'/wp-admin/user-edit.php?user_id='+fixture.user_a);
    assert(await page.evaluate(()=>getComputedStyle(document.querySelector('.yoswc-user-points-upsell')).borderInlineStartColor===getComputedStyle(document.querySelector('#adminmenu a.wp-has-current-submenu')).backgroundColor),'Profile Premium card follows scheme');
    await page.goto(base+'/wp-admin/users.php');
    assert(await page.evaluate(()=>getComputedStyle(document.querySelector('.yoswc-user-points-upsell--modal')).borderInlineStartColor===getComputedStyle(document.querySelector('#adminmenu a.wp-has-current-submenu')).backgroundColor),'Points modal Premium card follows scheme');
    await page.setViewportSize({width:1280,height:900});
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
