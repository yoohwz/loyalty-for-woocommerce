// Retained native admin transport fixture for retired setup routes.
const assert = require('node:assert/strict');
const { chromium } = require(process.env.LOYF_PLAYWRIGHT_PATH);
(async () => {
 const base=process.env.LOYF_BROWSER_URL;
 const browser=await chromium.launch({headless:true,...(process.env.LOYF_BROWSER_EXECUTABLE?{executablePath:process.env.LOYF_BROWSER_EXECUTABLE}:{})});
 try {
  const context=await browser.newContext();const page=await context.newPage();
  await page.goto(base+'/wp-login.php');
  await page.locator('#user_login').fill('loyf_admin');await page.locator('#user_pass').fill('disposable-only');
  await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#wp-submit').click()]);
  if(page.url().includes('action=confirm_admin_email')){await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.getByRole('link',{name:'Remind me later'}).click()]);}
  await page.goto(base+'/wp-admin/admin.php?page=loyf-overview');
  assert.deepEqual(await page.locator('#toplevel_page_loyf-overview .wp-submenu a').allTextContents(),['Overview','Emails','Settings']);
  assert.equal(await page.locator('#adminmenu a[href*="loyf-setup"]').count(),0);
  assert.equal(await page.getByText('Quick Start',{exact:true}).count(),0);
  const retired=await context.request.get(base+'/wp-admin/admin.php?page=loyf-setup');assert.equal(retired.status(),403);
  for(const intent of ['launch','dismiss']) {
   const response=await context.request.post(base+'/wp-admin/admin-post.php',{form:{action:'loyf_onboarding',intent,_wpnonce:'historical-nonce',earn_points:'99'}});
   assert.equal(response.status(),400,'Retired POST '+intent);
  }
  const get=await context.request.get(base+'/wp-admin/admin-post.php?action=loyf_onboarding&intent=launch');assert.equal(get.status(),400);
  await page.goto(base+'/wp-admin/admin.php?page=wc-settings&tab=loyalty&section=notification');
  assert.equal(await page.getByRole('navigation',{name:'Loyalty settings sections'}).getByRole('link').count(),6);
  assert.equal(await page.locator('[aria-label="Loyalty settings sections"] a[href*="section=notification"], #adminmenu a[href*="tab=loyalty"][href*="section=notification"]').count(),0);
  assert.equal(await page.getByRole('heading',{name:'Email notifications',exact:true}).count(),0);
  assert.equal(await page.getByRole('link',{name:'Manage loyalty emails',exact:true}).count(),0);
  assert.equal(await page.locator('#loyalty_points_rounding').count(),1,'Retired section uses existing General fallback');
  console.log('Native setup retired URL/POST and Notification fallback PASS');
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
