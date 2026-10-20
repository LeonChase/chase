/* Optional browser check. Requires Playwright + Chromium; does not install anything.
 * Reads only the generated local demo credentials; never prints credentials.
 */
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const base = 'http://127.0.0.1:8000';
(async () => {
  const credentials = fs.readFileSync(path.join(root, 'var/demo-credentials.txt'), 'utf8');
  const match = credentials.match(/E-Mail: max\.mustermann@example\.test\nPasswort: ([^\n]+)/);
  assert(match, 'Local generated demo account required');
  const browser = await chromium.launch({executablePath: process.env.CHASE_CHROMIUM || '/usr/bin/chromium', args:['--no-sandbox','--disable-dev-shm-usage']});
  const checks=[]; const errors=[]; const external=[];
  const context = await browser.newContext({viewport:{width:1440,height:1000}});
  const page = await context.newPage();
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', r => {if(!r.url().startsWith(base) && !/^(data:|blob:)/.test(r.url()))external.push(r.url());});
  let originalTheme='light';
  try {
    await page.goto(base+'/properties');assert(new URL(page.url()).pathname==='/login');checks.push('Anonymous access redirects to login');
    await page.locator('input[name=email]').fill('max.mustermann@example.test');
    await page.locator('input[name=password]').fill(match[1]);
    await Promise.all([page.waitForURL(base+'/properties'),page.locator('button[type=submit]').click()]);
    await page.goto(base+'/');
    assert.equal(await page.locator('.module-card').count(),6);
    const titles=await page.locator('.module-card h3').allTextContents();
    assert.deepEqual(titles,['Dokumente','Immobilienbewertung','KI-Tools','Wissen / Academy','Werbekatalog','Visualisierung']);
    const cols=await page.locator('.module-grid').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length);
    assert.equal(cols,3);
    assert.equal(await page.locator('.main-nav a[href="/studio"], .main-nav a[href="/marketing"], .main-nav a[href="/news"]').count(),0);
    assert.equal(await page.locator('.profile-menu a[href="/usage"]').count(),0);
    assert.equal(await page.locator('.hero-actions').count(),0);
    checks.push('Genuine login; six tiles; planner dashboard without retired navigation/actions');
    originalTheme=await page.locator('html').getAttribute('data-theme');
    const switched=originalTheme==='dark'?'light':'dark';
    await Promise.all([page.waitForResponse(r=>r.url()===base+'/profile/theme'&&r.request().method()==='POST'),page.locator('[data-toggle-theme]').click()]);
    await page.reload();assert.equal(await page.locator('html').getAttribute('data-theme'),switched);
    await Promise.all([page.waitForResponse(r=>r.url()===base+'/profile/theme'&&r.request().method()==='POST'),page.locator('[data-toggle-theme]').click()]);
    checks.push('Theme stored server-side and survives reload');
    assert.equal((await page.goto(base+'/properties/obj-1003')).status(),404);
    assert.equal((await page.goto(base+'/documents/doc09/download')).status(),404);
    checks.push('Foreign property and restricted document do not disclose data');
    await page.setViewportSize({width:390,height:844});
    const routes=['/','/properties','/properties/obj-1001','/tasks','/tasks/new','/leads','/documents','/academy','/academy/lexicon','/advertising','/advertising/cart','/messages','/profile','/valuation?property=obj-1001','/help/assistant','/news','/search?q=Bodenrichtwert'];
    for(const route of routes){const response=await page.goto(base+route);assert.equal(response.status(),200,route);await page.waitForTimeout(150);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Horizontal overflow: '+route);}
    checks.push(routes.length+' current module routes return 200 without page overflow at 390px');
    await page.goto(base+'/');await page.locator('[data-open-sidebar]').click();assert(await page.locator('body').evaluate(e=>e.classList.contains('nav-open')));await page.keyboard.press('Escape');assert(!(await page.locator('body').evaluate(e=>e.classList.contains('nav-open'))));checks.push('Mobile navigation opens and closes using Escape');
    await page.locator('[data-profile-toggle]').click();await Promise.all([page.waitForURL(base+'/login'),page.locator('form[action="/logout"] button').click()]);checks.push('CSRF-protected logout returns to login');
    assert.deepEqual(errors,[]);assert.deepEqual(external,[]);checks.push('No JavaScript errors or external browser requests');
    fs.mkdirSync(path.join(root,'var'),{recursive:true});fs.writeFileSync(path.join(root,'var/browser-smoke.json'),JSON.stringify({status:'passed',checks},null,2)+'\n');
    console.log(checks.map(c=>'PASS '+c).join('\n'));
  } finally { await browser.close(); }
})().catch(error=>{console.error(error.message);process.exit(1);});
