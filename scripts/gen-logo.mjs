// Gera os assets derivados do layout (logo, favicons, PWA, OG) a partir dos logos oficiais
// em public/assets/logo/ (cestazen-texto.png = horizontal, cestazen-avatar.png = ícone).
// Rasteriza via Chromium (Playwright). Uso: node scripts/gen-logo.mjs
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = new URL('../public/', import.meta.url).pathname;
const BRAND = '#4B7672';
const LOGO_TINT = '#345853'; // mais escuro que o PNG original: traço fino reduzido a ~136px perde contraste
const TAGLINE = 'Controle de gastos pessoais via importação de NFC-e';

const b64 = (rel) => fs.readFileSync(path.join(ROOT, rel)).toString('base64');
const write = (rel, data) => { fs.writeFileSync(path.join(ROOT, rel), data); console.log('wrote', rel); };

function ico(pngs) {
  // ICO com entradas PNG (suportado por todos os browsers modernos)
  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(pngs.length, 4);
  const entries = [];
  let offset = 6 + 16 * pngs.length;
  for (const { size, buf } of pngs) {
    const e = Buffer.alloc(16);
    e.writeUInt8(size, 0); e.writeUInt8(size, 1);
    e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6);
    e.writeUInt32LE(buf.length, 8); e.writeUInt32LE(offset, 12);
    entries.push(e); offset += buf.length;
  }
  return Buffer.concat([header, ...entries, ...pngs.map((p) => p.buf)]);
}

const browser = await chromium.launch();
const page = await browser.newPage();

// Roda dentro do browser: recorta o conteúdo (bbox do alpha), opcionalmente recolore e desenha em canvas.
const out = await page.evaluate(async ({ texto, avatar, BRAND, LOGO_TINT, TAGLINE }) => {
  const load = async (b64) => { const i = new Image(); i.src = `data:image/png;base64,${b64}`; await i.decode(); return i; };

  // recorta o conteúdo visível e, se `tint`, recolore preservando o alpha
  const trimmed = (img, tint) => {
    const c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
    const x = c.getContext('2d'); x.drawImage(img, 0, 0);
    const d = x.getImageData(0, 0, c.width, c.height).data;
    let [x0, y0, x1, y1] = [c.width, c.height, 0, 0];
    for (let i = 3; i < d.length; i += 4) {
      if (d[i] < 16) continue;
      const px = ((i - 3) / 4) % c.width, py = Math.floor((i - 3) / 4 / c.width);
      x0 = Math.min(x0, px); y0 = Math.min(y0, py); x1 = Math.max(x1, px); y1 = Math.max(y1, py);
    }
    const w = x1 - x0 + 1, h = y1 - y0 + 1;
    const t = document.createElement('canvas'); t.width = w; t.height = h;
    const tx = t.getContext('2d'); tx.drawImage(c, x0, y0, w, h, 0, 0, w, h);
    if (tint) { tx.globalCompositeOperation = 'source-in'; tx.fillStyle = tint; tx.fillRect(0, 0, w, h); }
    return t;
  };

  // desenha `src` contido e centrado num canvas W×H (com margem `pad`, fundo `bg` ou transparente)
  const draw = (src, W, H, { pad = 0, bg = null } = {}) => {
    const c = document.createElement('canvas'); c.width = W; c.height = H;
    const x = c.getContext('2d');
    x.imageSmoothingQuality = 'high';
    if (bg) { x.fillStyle = bg; x.fillRect(0, 0, W, H); }
    const s = Math.min((W - 2 * pad) / src.width, (H - 2 * pad) / src.height);
    const w = src.width * s, h = src.height * s;
    x.drawImage(src, (W - w) / 2, (H - h) / 2, w, h);
    return c;
  };

  const png = (c) => c.toDataURL('image/png').split(',')[1];

  const [textoImg, avatarImg] = await Promise.all([load(texto), load(avatar)]);
  const logo = trimmed(textoImg, LOGO_TINT);
  const logoDark = logo;
  const icon = trimmed(avatarImg);
  const iconWhite = trimmed(avatarImg, '#FFFFFF');
  const logoH = (w) => Math.round((w * logo.height) / logo.width);

  const files = {
    'assets/media/app/default-logo.png': png(draw(logo, 1000, logoH(1000))),
    'assets/media/app/default-logo-dark.png': png(draw(logoDark, 1000, logoH(1000))),
    'assets/media/app/mini-logo.png': png(draw(icon, 320, 320)),
    'assets/media/app/favicon-16x16.png': png(draw(icon, 16, 16)),
    'assets/media/app/favicon-32x32.png': png(draw(icon, 32, 32)),
    'assets/media/app/apple-touch-icon.png': png(draw(icon, 180, 180, { pad: 14, bg: '#fff' })),
    'assets/media/app/pwa/icon-192x192.png': png(draw(icon, 192, 192, { pad: 14, bg: '#fff' })),
    'assets/media/app/pwa/icon-512x512.png': png(draw(icon, 512, 512, { pad: 36, bg: '#fff' })),
    'assets/media/app/pwa/icon-maskable-512x512.png': png(draw(iconWhite, 512, 512, { pad: 100, bg: BRAND })),
  };

  // Open Graph 1200x630: logo centralizado + tagline
  const og = draw(logo, 1200, 630, { pad: 0, bg: '#fff' });
  const ox = og.getContext('2d');
  ox.fillStyle = '#fff'; ox.fillRect(0, 0, 1200, 630);
  ox.imageSmoothingQuality = 'high';
  const ow = 900, oh = logoH(ow);
  ox.drawImage(logo, (1200 - ow) / 2, 250 - oh / 2, ow, oh);
  ox.fillStyle = BRAND; ox.font = '30px system-ui, sans-serif'; ox.textAlign = 'center';
  ox.fillText(TAGLINE, 600, 250 + oh / 2 + 70);
  files['assets/media/app/og-image.png'] = png(og);

  return files;
}, { texto: b64('assets/logo/cestazen-texto.png'), avatar: b64('assets/logo/cestazen-avatar.png'), BRAND, LOGO_TINT, TAGLINE });

await browser.close();

for (const [rel, data] of Object.entries(out)) write(rel, Buffer.from(data, 'base64'));

const ico16 = Buffer.from(out['assets/media/app/favicon-16x16.png'], 'base64');
const ico32 = Buffer.from(out['assets/media/app/favicon-32x32.png'], 'base64');
const icoBuf = ico([{ size: 16, buf: ico16 }, { size: 32, buf: ico32 }]);
write('assets/media/app/favicon.ico', icoBuf);
write('favicon.ico', icoBuf);
