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
const guides = [
  // PrestaShop's own convention for module documentation is docs/readme_<iso>.pdf,
  // so that is what ships; the HTML beside it stays the editable source.
  { src: 'user-guide.html', out: 'readme_en.pdf' },
  { src: 'user-guide-pl.html', out: 'readme_pl.pdf' },
];

(async () => {
  const browser = await chromium.launch({ headless: true, channel: process.env.PW_CHANNEL || undefined });
  const page = await browser.newPage();
  for (const guide of guides) {
    const src = path.join(root, 'qameraai', 'docs', guide.src);
    const out = path.join(root, 'qameraai', 'docs', guide.out);
    await page.goto('file://' + src.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
    await page.pdf({
      path: out,
      format: 'A4',
      printBackground: true,
      margin: { top: '18mm', bottom: '18mm', left: '16mm', right: '16mm' },
    });
    console.log('built:', out);
  }
  await browser.close();
})().catch(function (e) {
  console.error('doc build failed:', e.message);
  process.exit(1);
});
