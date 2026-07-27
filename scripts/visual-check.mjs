// Utilitário para checagem visual ad-hoc via Playwright.
// Uso: node scripts/visual-check.mjs <path> [--tab "<tab-toggle-selector>"] [--wait-for "<selector>"] [--element "<selector>"] [--out <nome.png>]
//
// Exemplos:
//   node scripts/visual-check.mjs /account --tab '[data-kt-tab-toggle="#tab_settings"]' --wait-for 'text=Foto de Perfil' --element '#avatar_preview_frame'
//   node scripts/visual-check.mjs /dashboard
//
// Requer os containers Docker do projeto rodando (nginx-my-expenses na porta 8000)
// e o usuário fixo criado por VisualCheckUserSeeder (php artisan db:seed --class=VisualCheckUserSeeder).

import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';

const BASE_URL = process.env.VISUAL_CHECK_BASE_URL ?? 'http://localhost:8000';
const EMAIL = process.env.VISUAL_CHECK_EMAIL ?? 'visual-check@local.test';
const PASSWORD = process.env.VISUAL_CHECK_PASSWORD ?? 'visual-check-password';
const OUTPUT_DIR = path.resolve(import.meta.dirname, '../storage/app/visual-checks');

function parseArgs(argv) {
    const [targetPath, ...rest] = argv;
    const opts = { targetPath };
    for (let i = 0; i < rest.length; i += 2) {
        const key = rest[i].replace(/^--/, '');
        opts[key] = rest[i + 1];
    }
    return opts;
}

async function main() {
    const { targetPath, tab, ['wait-for']: waitFor, element, out } = parseArgs(process.argv.slice(2));

    if (!targetPath) {
        console.error('Uso: node scripts/visual-check.mjs <path> [--tab "<selector>"] [--wait-for "<selector>"] [--element "<selector>"] [--out <nome.png>]');
        process.exit(1);
    }

    fs.mkdirSync(OUTPUT_DIR, { recursive: true });

    const browser = await chromium.launch({ args: ['--no-sandbox'] });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

    const consoleErrors = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });

    await page.goto(`${BASE_URL}/login`);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
        page.waitForNavigation(),
        page.click('button:has-text("Entrar")'),
    ]);

    await page.goto(`${BASE_URL}${targetPath}`);

    if (tab) {
        await page.click(tab);
    }
    if (waitFor) {
        await page.waitForSelector(waitFor);
    }

    const fileName = out ?? `${targetPath.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'screenshot'}.png`;
    const outputPath = path.join(OUTPUT_DIR, fileName);

    if (element) {
        await page.locator(element).screenshot({ path: outputPath });
    } else {
        await page.screenshot({ path: outputPath, fullPage: true });
    }

    await browser.close();

    console.log(`Screenshot salvo em: ${outputPath}`);
    if (consoleErrors.length) {
        console.log('Erros de console encontrados:');
        consoleErrors.forEach((e) => console.log(` - ${e}`));
    }
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
