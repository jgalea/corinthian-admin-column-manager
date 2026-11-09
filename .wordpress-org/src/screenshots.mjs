// Run with Pro deactivated. Output dir is the first argument.
import { chromium } from 'playwright';
const out = process.argv[2];
const base = 'http://localhost:8930/wp-admin/';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
const clean = () => p.evaluate(() => document.querySelectorAll('.notice, .update-nag, #wpfooter, .cookie-banner, [class*="cookie"]').forEach((n) => n.remove()));
await p.goto('http://localhost:8930/wp-login.php');
await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'password'); await p.click('#wp-submit');
await p.waitForURL(/wp-admin/);

// 1. Posts list, sorted by word count
await p.goto(base + 'edit.php?orderby=corinthian-words&order=desc');
await p.waitForSelector('.corinthian-resize', { state: 'attached' });
await clean();
await p.mouse.move(700, 800);
await p.screenshot({ path: out + '/screenshot-1.png', clip: { x: 160, y: 32, width: 1280, height: 564 } });

// 2. Editor
await p.goto(base + 'options-general.php?page=corinthian');
await p.waitForSelector('input[value="Editorial view"]');
await p.getByRole('button', { name: /Topic/ }).first().click().catch(() => {});
await clean();
await p.mouse.move(1300, 800);
await p.screenshot({ path: out + '/screenshot-2.png', clip: { x: 170, y: 44, width: 990, height: 850 } });

// 3. Drag-resize Author column (not released, so nothing is saved)
await p.goto(base + 'edit.php?orderby=corinthian-words&order=desc');
await p.waitForSelector('.corinthian-resize', { state: 'attached' });
await clean();
const h = await p.locator('th.manage-column', { hasText: 'Author' }).locator('.corinthian-resize').boundingBox();
const x = h.x + h.width / 2, y = h.y + h.height / 2;
await p.mouse.move(x, y); await p.mouse.down(); await p.mouse.move(x + 45, y, { steps: 12 });
await p.screenshot({ path: out + '/screenshot-3.png', clip: { x: 170, y: 176, width: 1000, height: 300 } });

// 4. Users
const p2 = p;
await p2.goto(base + 'users.php');
await p2.evaluate(() => document.querySelectorAll('.notice, .update-nag, #wpfooter').forEach((n) => n.remove()));
await p2.screenshot({ path: out + '/screenshot-4.png', clip: { x: 160, y: 32, width: 1280, height: 464 } });
await b.close();
