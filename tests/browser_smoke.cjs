const {chromium} = require('../.tmp_browser/node_modules/playwright');
const fs = require('fs');
(async () => {
  const browser = await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const failures=[];
  for (const [role,id,routes] of [
    ['admin',1,['dashboard.php','vendors.php','reports.php','vendor_documents.php','collector_approvals.php','payment_calendar.php']],
    ['collector',2,['dashboard.php','collector_payments.php?search=V-01','collector_history.php','vendors_list.php']],
    ['vendor',3,['dashboard.php','documents.php','vendor_payment_history.php']]
  ]) {
    const context=await browser.newContext({viewport:{width:1440,height:1000}});
    const page=await context.newPage();
    page.on('pageerror',e=>failures.push(role+': '+e.message));
    await page.goto('http://127.0.0.1:8098/auth/login.php');
    await page.locator('[name=email]').fill(role+id+'@example.test');
    await page.locator('[name=password]').fill('Revision-Test-Password-2026!');
    await page.locator('.role-pill').filter({hasText:new RegExp(role,'i')}).click();
    await Promise.all([page.waitForURL('**/'+role+'/dashboard.php'),page.locator('button[type=submit]').click()]);
    for(const route of routes) {
      await page.goto('http://127.0.0.1:8098/'+role+'/'+route);
      await page.waitForTimeout(350);
      const text=await page.locator('body').innerText();
      if(/Fatal error|Warning:|Deprecated:/.test(text))failures.push(role+'/'+route+': PHP output');
      const desktopOverflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2);
      if(desktopOverflow)failures.push(role+'/'+route+': desktop overflow');
      if(route==='dashboard.php') await page.screenshot({path:'tests/'+role+'-desktop.png',fullPage:true});
      await page.setViewportSize({width:390,height:844});
      await page.screenshot({path:'tests/'+role+'-'+route.split('?')[0]+'.png',fullPage:true});
      if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2))failures.push(role+'/'+route+': mobile overflow');
      await page.setViewportSize({width:1440,height:1000});
    }
    if(role==='collector') {
      await page.goto('http://127.0.0.1:8098/collector/collector_payments.php?search=V-01');
      const choices=await page.locator('select[name=vendor_id] option').allTextContents();
      if(!choices.some(x=>x.includes('Dry Goods'))||!choices.some(x=>x.includes('Fresh Fish')))failures.push('Missing duplicate-stall selection');
      await page.getByRole('link',{name:'Dashboard',exact:true}).click();
      if(!page.url().endsWith('/collector/dashboard.php'))failures.push('Dashboard navigation incorrect');
      if(!await page.getByText('Vendor Performance Ranking').isVisible())failures.push('Dashboard missing ranking');
    }
    await context.close();
  }
  await browser.close();
  console.log(JSON.stringify({failures},null,2));
  process.exitCode=failures.length?1:0;
})().catch(e=>{console.error(e);process.exit(1);});
