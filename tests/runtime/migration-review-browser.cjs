const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.LOYF_PLAYWRIGHT_PATH);
const base = process.env.LOYF_BROWSER_URL;
const fixture = mode => execFileSync(process.env.LOYF25_PHP || 'php', [process.env.LOYF_WP_CLI_PHAR, '--path=' + process.env.LOYF25_SITE, 'eval-file', process.env.LOYF25_FIXTURE, mode, '--quiet'], {env:process.env,encoding:'utf8'});
(async () => {
 const browser=await chromium.launch({headless:true,...(process.env.LOYF_BROWSER_EXECUTABLE?{executablePath:process.env.LOYF_BROWSER_EXECUTABLE}:{})});
 try {
  const context=await browser.newContext();const page=await context.newPage();page.on('requestfailed',request=>{if(request.resourceType()==='document' && request.failure()?.errorText!=='net::ERR_ABORTED'){console.error('Browser request failed:',request.method(),new URL(request.url()).origin+new URL(request.url()).pathname,request.failure()?.errorText);}});page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(30000);
  await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill('loyf_admin');await page.locator('#user_pass').fill('disposable-only-password');
  try { await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login')),page.locator('#loginform').evaluate(f=>{f.querySelector('#user_login').value='loyf_admin';f.querySelector('#user_pass').value='disposable-only-password';f.requestSubmit(f.querySelector('#wp-submit'));})]); } catch(error) { console.error(await page.evaluate(()=>({url:location.origin+location.pathname,formAction:document.querySelector('#loginform')?.action,formValid:document.querySelector('#loginform')?.checkValidity(),error:document.querySelector('#login_error')?.textContent})));throw error; }
  const settings=section=>base+'/wp-admin/admin.php?page=wc-settings&tab=loyalty&section='+section;
  const review=base+'/wp-admin/admin.php?page=loyf-migration-review';
  await page.goto(settings('general'));
  assert.equal(await page.locator('#loyf-migration-notice').count(),1,'One count derived from eight held features');
  assert.equal(await page.locator('input[name=loyalty_using_amount]').isDisabled(),true);
  assert.equal(await page.locator('#loyalty_points_using_point').isDisabled(),true);
  await page.locator('#loyalty_points_rounding').selectOption('round_up');
  await page.locator('button[name=save]').click();
  await page.getByText('Redemption is not active while its settings are being updated.',{exact:false}).waitFor();
  assert(!(await page.locator('body').innerText()).includes('Your settings have been saved.'),'Partial save cannot report all saved');
  fixture('verify-held');
  const form=await page.locator('#mainform').evaluate(f=>Object.fromEntries(new FormData(f)));form.save='Save changes';form.loyalty_using_amount='99';form.loyalty_using_points='88';form.loyalty_points_using_point='1';
  const forged=await context.request.post(settings('general'),{form});assert(forged.ok());fixture('verify-held');
  const denied={...form,loyalty_levels_nonce:'invalid',loyalty_points_rounding:'round_down'};const deniedResult=await context.request.post(settings('general'),{form:denied});const deniedHTML=await deniedResult.text();assert(deniedHTML.includes('Settings were not saved because the confirmation expired.'));assert(!deniedHTML.includes('Your settings have been saved.'));fixture('verify-held');
  await page.goto(settings('general'));await page.locator('#loyalty_points_rounding').selectOption('round_down');await page.locator('[name="loyalty_earning_points[customer]"]').fill('2');
  await page.locator('#mainform').evaluate(f=>{const input=document.createElement('input');input.type='hidden';input.name='_loyf25_rounding_fault';input.value='1';f.append(input);});
  await page.locator('button[name=save]').click();await page.getByText('The save could not be completed.',{exact:false}).waitFor();assert((await page.locator('body').innerText()).includes('Settings saved before this error:'));assert(!(await page.locator('body').innerText()).includes('Your settings have been saved.'));fixture('verify-partial');fixture('verify-held');
  await page.goto(settings('extra_points'));
  for(const name of ['signup','login','review']){assert(await page.locator('[name=loyalty_extra_'+name+'_points]').isDisabled());assert.equal(await page.locator('a[href*=loyf-migration-review]').count(),0);}
  assert.equal(await page.locator('a[href*=loyf-migration-review]').count(),0);
  await page.locator('[name=loyalty_extra_first_purchase_points]').fill('31');await page.locator('#loyalty_extra_first_purchase_enabled').check();await page.locator('button[name=save]').click();await page.getByText('Rewards being updated were skipped.',{exact:false}).waitFor();fixture('verify-held');fixture('verify-first');
  for(const section of ['yowcl_wc_email_loyalty_points_reward','yowcl_wc_email_loyalty_points_deduct','yowcl_wc_email_loyalty_level_update']){
   await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=email&section='+section);
   assert((await page.locator('#mainform').innerText()).includes('Not active'));
   assert.equal(await page.locator('button[name=save]').count(),0,'Held email has no false native save');
   const emailForm=await page.locator('#mainform').evaluate(f=>Object.fromEntries(new FormData(f)));emailForm.save='Save changes';emailForm.woocommerce_yowcl_loyalty_points_reward_enabled='1';
   const result=await context.request.post(page.url(),{form:emailForm});const html=await result.text();assert(!html.includes('Your settings have been saved.'),'Forged held native email POST suppresses false success');fixture('verify-held');
  }
  await page.goto(base+'/wp-admin/admin.php?page=loyf-setup');assert.equal(await page.locator('#loyf-quick-start').count(),0,'Historical Quick Start has no Launch authority');assert(!(await page.locator('body').innerText()).includes('private-dormant'));assert.equal(await page.locator('a[href*=loyf-migration-review]').count(),0);
  await page.goto(review);assert.equal(await page.locator('section[id^=loyf-review-]').count(),0,'Direct migration page is not routable');
  assert.equal(await page.locator('form[action*=admin-post]').count(),0,'No hidden migration form');
  for(const action of ['loyf_resolve_migration','loyf_confirm_migrations','loyf_replace_migration','loyf_retry_migration']){
   const result=await context.request.post(base+'/wp-admin/admin-post.php',{form:{action}});assert.equal(result.status(),400,'Retired endpoint has no handler '+action);
  }
  fixture('verify-held');fixture('drain-background');fixture('verify-background');
  await page.goto(settings('general'));assert.equal(await page.locator('#loyalty_points_using_point').isChecked(),false,'Inactive gate is reflected in the ordinary checkbox');
  assert((await page.locator('#loyf-migration-notice').innerText()).includes('previous configuration could not be verified'),'Completed pause is truthful');
  await page.locator('#loyalty_points_using_point').check();
  await page.goto(settings('general'));const amount=page.locator('[name=loyalty_using_amount]');assert.equal(await amount.inputValue(),'0.7');assert.equal(await amount.getAttribute('step'),'0.01');assert(await amount.evaluate(x=>x.checkValidity()));
  await page.locator('#loyalty_points_using_point').check();await page.locator('#loyalty_points_rounding').selectOption('round_down');await page.locator('button[name=save]').click();
  await page.getByText('Your settings have been saved.',{exact:false}).waitFor();fixture('verify-ready');fixture('verify-value');
  fixture('subprecision');await page.goto(settings('general'));assert.equal(await page.locator('[name=loyalty_using_amount]').inputValue(),'0');await page.locator('#loyalty_points_rounding').selectOption('round_up');await page.locator('button[name=save]').click();await page.getByText('Your settings have been saved.',{exact:false}).waitFor();fixture('verify-subprecision');fixture('verify-value');
  fixture('read-fault');await page.goto(settings('general'));await page.locator('[name=loyalty_using_amount]').fill('0.8');await page.locator('[name=loyalty_points_using_point]').uncheck();await page.locator('#mainform').evaluate(f=>{const i=document.createElement('input');i.type='hidden';i.name='_loyf25_redemption_read_fault';i.value='1';f.append(i);});await page.locator('button[name=save]').click();await page.getByText('The save could not be completed.',{exact:false}).waitFor();assert(!(await page.locator('body').innerText()).includes('Your settings have been saved.'));fixture('verify-read-fault');fixture('verify-value');
  fixture('precision');await page.goto(settings('general'));assert.equal(await page.locator('[name=loyalty_using_amount]').inputValue(),'0.7');await page.locator('#loyalty_points_rounding').selectOption('round_down');await page.locator('button[name=save]').click();await page.getByText('Your settings have been saved.',{exact:false}).waitFor();fixture('verify-precision');fixture('verify-value');
  fixture('read-fault');await page.goto(settings('general'));await page.locator('#loyalty_points_rounding').selectOption('round_up');await page.locator('#mainform').evaluate(f=>{const i=document.createElement('input');i.type='hidden';i.name='_loyf25_redemption_term_fault';i.value='1';f.append(i);});await page.locator('button[name=save]').click();await page.getByText('The save could not be completed.',{exact:false}).waitFor();assert(!(await page.locator('body').innerText()).includes('Your settings have been saved.'));fixture('verify-read-fault');fixture('verify-precision');fixture('verify-value');
  fixture('fresh-blank');await page.goto(settings('general'));assert.equal(await page.locator('[name=loyalty_using_points]').inputValue(),'');assert.equal(await page.locator('[name=loyalty_using_amount]').inputValue(),'');await page.locator('#loyalty_points_rounding').selectOption('round_down');await page.locator('button[name=save]').click();await page.getByText('Your settings have been saved.',{exact:false}).waitFor();fixture('verify-blank');fixture('verify-value');
  await page.goto(settings('general'));await page.locator('[name=loyalty_points_using_point]').check();await page.locator('#loyalty_points_rounding').selectOption('round_up');await page.locator('button[name=save]').click();await page.getByText('The save could not be completed.',{exact:false}).waitFor();assert(!(await page.locator('body').innerText()).includes('Your settings have been saved.'));fixture('verify-blank');fixture('verify-value');
  await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=email&section=yowcl_wc_email_loyalty_points_reward');
  await page.locator('[name=woocommerce_yowcl_loyalty_points_reward_subject]').fill('Merchant updated subject');await page.locator('button[name=save]').click();await page.getByText('Your settings have been saved.',{exact:false}).waitFor();fixture('verify-email');fixture('verify-value');
  await page.goto(settings('tools'));
  assert.equal(await page.locator('form').count(),1,'Tools uses the native outer form');assert.equal(await page.locator('button[name=save]').count(),0);
  assert.equal(await page.locator('[name=start_new_import]').evaluate(x=>x.form.id),'mainform');
  assert.equal(await page.locator('[name=import_csv]').evaluate(x=>x.form.id),'mainform');
  assert.equal(await page.locator('[name=loyf_start_new_import_nonce]').evaluate(x=>x.form.id),'mainform');
  assert.equal(await page.locator('[name=wc_loyalty_import_nonce]').evaluate(x=>x.form.id),'mainform');
  const identity=await page.locator('[name=operation_id]').inputValue();await page.locator('[name=start_new_import]').click();assert.notEqual(await page.locator('[name=operation_id]').inputValue(),identity,'Explicit new import changes only the existing recovery pointer');assert(!(await page.locator('body').innerText()).includes('Your settings have been saved.'));fixture('verify-value');
  await page.setViewportSize({width:390,height:844});
  for(const section of ['general','extra_points','tools']){
   await page.goto(settings(section));assert.equal(await page.locator('a[href*=loyf-migration-review],form[action*=loyf_confirm],form[action*=loyf_retry]').count(),0,'No stale controls '+section);
   assert.equal(await page.getByRole('link',{name:'Loyalty Migration Status',exact:true}).count(),0,'No submenu');
   await page.keyboard.press('Tab');assert(await page.evaluate(()=>document.activeElement!==document.body),'Keyboard reaches ordinary controls');
  }
  if(process.env.LOYF25_SCREENSHOT){await page.screenshot({path:process.env.LOYF25_SCREENSHOT,fullPage:true});}
  fixture('background-seed');await page.goto(settings('general'));fixture('verify-queued');
  assert((await page.locator('#loyf-migration-notice').innerText()).includes('updating your settings in the background'));
  const dismissResponse=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('loyf_dismiss_migration'));
  await page.locator('#loyf-migration-notice .notice-dismiss').click();assert.equal((await (await dismissResponse).json()).success,true);
  await page.locator('#loyf-migration-notice').waitFor({state:'detached'});await page.reload();assert.equal(await page.locator('#loyf-migration-notice').count(),0);fixture('verify-queued');
  fixture('drain-background');fixture('verify-background');await page.reload();assert.equal(await page.locator('#loyf-migration-notice').count(),1,'Meaningful completed pause has one new dismissal token');
  const pauseResponse=page.waitForResponse(r=>r.url().includes('admin-ajax.php')&&r.request().postData()?.includes('loyf_dismiss_migration'));
  await page.locator('#loyf-migration-notice .notice-dismiss').click();assert.equal((await (await pauseResponse).json()).success,true);await page.locator('#loyf-migration-notice').waitFor({state:'detached'});await page.reload();assert.equal(await page.locator('#loyf-migration-notice').count(),0,'Pause dismissal persists without requeue');fixture('verify-background');
  console.log('Native automatic migration browser PASS: no submenu/direct page/POST handler/forms/dead links; queued/paused dismissal without queue mutation; held General/email denial and partial saves; ordinary redemption/email enablement, raw decimal preservation, Tools form and keyboard/mobile controls; no accounting events.');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
