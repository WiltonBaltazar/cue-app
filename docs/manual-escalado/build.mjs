// Gera manual-do-escalado.pdf a partir de manual.html (duas passagens: a segunda preenche o índice).
import { chromium } from '../../node_modules/playwright/index.mjs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
const dir = path.dirname(fileURLToPath(import.meta.url));
const out = path.join(dir, 'manual-do-escalado.pdf');
const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto('file://' + path.join(dir, 'manual.html'), {
    waitUntil: 'networkidle',
});
await page.evaluate(() => document.fonts.ready);
const render = () =>
    page.pdf({ path: out, preferCSSPageSize: true, printBackground: true });
await render();
// Encontrar a página de início de cada capítulo pelo texto do cabeçalho.
const titles = await page.$$eval('.chapter .chapter-head h2', (hs) =>
    hs.map((h) => h.lastChild.textContent.trim()),
);
const pages = execFileSync('pdftotext', ['-layout', out, '-'])
    .toString()
    .split('\f');
const found = titles.map(
    (t) => pages.findIndex((p, i) => i >= 3 && p.includes(t.slice(0, 22))) + 1,
);
await page.evaluate(
    (found) =>
        document
            .querySelectorAll('.toc .p')
            .forEach((el, i) => (el.textContent = found[i] || '')),
    found,
);
await render();
console.log(
    'capítulos nas páginas',
    found.join(', '),
    '| total',
    pages.length - 1,
);
await browser.close();
