/**
 * Render the module's user guide to PDF (NOT shipped — the PDF it produces is).
 *
 * PrestaShop's validation checklist wants merchant documentation in the module's
 * docs/ folder and recommends PDF. The source of truth is the HTML next to the
 * output, so the guide stays editable and diffable in the repository.
 *
 *   npm i playwright        # or reuse an existing install via NODE_PATH
 *   node tools/build-docs.js
 *
 * PW_CHANNEL=chrome uses an installed Chrome instead of a downloaded browser.
 */
const { chromium } = require('playwright');
const path = require('path');

const root = path.resolve(__dirname, '..');
const src = path.join(root, 'qameraai', 'docs', 'user-guide.html');
const out = path.join(root, 'qameraai', 'docs', 'user-guide.pdf');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: process.env.PW_CHANNEL || undefined });
  const page = await browser.newPage();
  await page.goto('file://' + src.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
  await page.pdf({
    path: out,
    format: 'A4',
    printBackground: true,
    margin: { top: '18mm', bottom: '18mm', left: '16mm', right: '16mm' },
  });
  await browser.close();
  console.log('built:', out);
})().catch(function (e) {
  console.error('doc build failed:', e.message);
  process.exit(1);
});
