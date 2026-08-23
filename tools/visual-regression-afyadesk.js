const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const baseUrl = process.env.AFYADESK_URL || 'http://127.0.0.1:8081';
const username = process.env.AFYADESK_USER || 'admin';
const password = process.env.AFYADESK_PASSWORD || 'admin';
const outputDir = path.resolve(__dirname, '..', 'files', '_screenshots', 'visual-regression');
const publicPages = [
  ['login', '/index.php'],
  ['helpdesk', '/Helpdesk'],
  ['service-catalog', '/ServiceCatalog'],
];
const protectedPages = [
  ['users', '/front/user.php'],
  ['notifications', '/front/setup.notification.php'],
  ['assets-dashboard', '/front/dashboard_assets.php'],
];

(async () => {
  const localBrowser = [
    process.env.PLAYWRIGHT_CHROME_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  ].find((candidate) => candidate && fs.existsSync(candidate));
  const browser = await chromium.launch(localBrowser ? { executablePath: localBrowser } : {});
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  await fs.promises.mkdir(outputDir, { recursive: true });

  await context.clearCookies();
  for (const [name, url] of publicPages) {
    await page.goto(`${baseUrl}${url}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await capture(page, name);
  }

  await page.goto(`${baseUrl}/index.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  const loginInput = page.locator('input[name="login_name"], input[name="fielda"]').first();
  if (await loginInput.count()) {
    await loginInput.fill(username);
    await page.locator('input[name="login_password"], input[name="fieldb"]').first().fill(password);
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForLoadState('domcontentloaded');
  }

  for (const [name, url] of protectedPages) {
    await page.goto(`${baseUrl}${url}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await capture(page, name);
  }

  await browser.close();
  console.log(`AfyaDesk screenshots saved to ${outputDir}`);
})();

async function capture(page, name) {
  await page.screenshot({ path: path.join(outputDir, `${name}.png`), fullPage: true });
  const overlaps = await page.locator('body').evaluate(() => {
    const bad = [];
    for (const el of document.querySelectorAll('.search-results .avatar, .navbar-brand img, footer')) {
      const rect = el.getBoundingClientRect();
      if (rect.width === 0 || rect.height === 0) {
        bad.push(el.outerHTML.slice(0, 120));
      }
    }
    return bad;
  });
  if (overlaps.length) {
    throw new Error(`${name} has hidden visual elements: ${overlaps.join(', ')}`);
  }
}
