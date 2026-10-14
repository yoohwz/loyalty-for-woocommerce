const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
  const f = JSON.parse(fs.readFileSync(process.env.LOYF_BROWSER_FIXTURE, 'utf8'));
  const base = process.env.LOYF_BROWSER_URL;
  const browser = await chromium.launch({headless:true,...(process.env.LOYF_BROWSER_EXECUTABLE?{executablePath:process.env.LOYF_BROWSER_EXECUTABLE}:{})});
  const context = await browser.newContext({permissions:['clipboard-read','clipboard-write']});
  try {
    const capture = token => context.request.get(base+'/?loyf10_capture=1&ref='+encodeURIComponent(token));
    const before = Math.floor(Date.now()/1000); const response = await capture(f.token);
    assert.equal((await response.json()).referrer, f.user);
    const cookie = (await context.cookies()).find(c=>c.name==='yowcl_ref');
    assert.equal(cookie.value,f.token); assert.equal(cookie.httpOnly,true); assert.equal(cookie.sameSite,'Lax'); assert.equal(cookie.secure,false); assert.equal(cookie.path,'/');
    assert.ok(Math.abs(cookie.expires-before-30*86400)<15);
    const replaced = await capture(f.other_token); assert.equal((await replaced.json()).referrer,f.other_user);
    for(const bad of [String(f.user),'malformed','<script>','<b>'+f.token+'</b>']) { await capture(bad); assert.equal((await context.cookies()).find(c=>c.name==='yowcl_ref').value,f.other_token); }
    const ssl = await context.request.get(base+'/?loyf10_capture=1&loyf10_ssl=1&ref='+f.token);
    const setCookie = ssl.headers()['set-cookie']; assert.ok(/secure/i.test(setCookie)&&/httponly/i.test(setCookie)&&/samesite=lax/i.test(setCookie));
    const page = await context.newPage(); await page.goto(base+'/wp-login.php');
    await page.evaluate(()=>{document.querySelector('#user_login').value='blocks_browser';document.querySelector('#user_pass').value='disposable-only';});
    await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login')),page.locator('#wp-submit').click()]);
    await page.goto(base+'/?page_id='+f.cart);
    // Current user's public link is independent of the browser's attribution cookie.
    await page.locator('[data-yoswc-loyalty-info] .yoswc-loyalty-info__bubble').click();
    const button=page.locator('[data-loyf-referral-copy="loyf-referral-bubble"]');
    assert.equal(await page.locator('#loyf-referral-bubble').inputValue(),base+'/?ref='+f.token);
    await page.waitForFunction(()=>{const key=window.wc?.wcBlocksData?.CART_STORE_KEY;if(!key||!window.wp?.data)return false;const store=window.wp.data.select(key);return store.hasFinishedResolution('getCartData')&&!store.isCustomerDataUpdating();});
    await page.waitForLoadState('networkidle'); // Finish Woo's initial customer/address hydration before measuring Copy.
    const posts=[];page.on('request',r=>{
      if(r.method()!=='POST')return;
      // Woo11.2 transports a cart GET inside its POST batch middleware.
      // Only that exact read-only shape is allowed; unknown/mutating batches fail.
      const url=new URL(r.url()),route=url.searchParams.get('rest_route')||url.pathname;
      if(route==='/wc/store/v1/batch'){
        try{const body=JSON.parse(r.postData());if(Array.isArray(body.requests)&&body.requests.length>0&&body.requests.every(q=>q.method==='GET'&&typeof q.path==='string'&&q.path.split('?')[0]==='/wc/store/v1/cart'))return;}catch(e){}
      }
      posts.push({url:r.url(),body:r.postData()});
    });
    await button.click();await page.waitForFunction(()=>document.querySelector('#loyf-referral-bubble').parentElement.querySelector('[role="status"]').textContent==='Link copied');
    assert.equal(await page.evaluate(()=>navigator.clipboard.readText()),base+'/?ref='+f.token);assert.deepEqual(posts,[]);
    await page.goto(base+'/?page_id='+f.account+'&'+encodeURIComponent(f.account_slug)+'=1');
    assert.equal(await page.locator('#loyf-referral-account').inputValue(),base+'/?ref='+f.token);
    assert.equal(await page.locator('.loyf-referral-card').count(),1,'Single My Points referral card');
    assert(await page.locator('.loyf-referral-card').evaluate(card=>card.previousElementSibling.matches('.loyalty-points-card')&&card.nextElementSibling.id==='my-points-table'),'Membercard → referral → history');
    assert.equal(await page.locator('.woocommerce-MyAccount-content a[href*="yoohw.com/product"]').count(),0,'No storefront Premium advertising');
    for(const width of [1280,390]){
      await page.setViewportSize({width,height:844});
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'My Points containment '+width);
      await page.locator('#loyf-referral-account').focus();await page.keyboard.press('Tab');
      assert.equal(await page.evaluate(()=>document.activeElement.dataset.loyfReferralCopy),'loyf-referral-account','Copy follows input in keyboard order');
      await page.keyboard.press('Enter');await page.locator('.loyf-referral-card [role="status"]').getByText('Link copied',{exact:true}).waitFor();
    }
    await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async()=>{throw new Error('denied');}}}));
    await page.locator('[data-loyf-referral-copy="loyf-referral-account"]').click();
    await page.locator('.loyf-referral-card [role="status"]').getByText('Select and copy the link',{exact:true}).waitFor();
    assert(await page.locator('#loyf-referral-account').evaluate(input=>document.activeElement===input&&input.selectionStart===0&&input.selectionEnd===input.value.length),'Copy denial selects readable link');
    console.log('Native referral cookie/copy browser PASS Woo'+f.version+' '+f.storage);
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
