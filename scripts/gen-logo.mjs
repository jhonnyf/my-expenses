// Gera os logos oficiais CestaZen (SVG) e rasteriza PNG/JPG/ICO via Chromium (Playwright).
// Uso: node scripts/gen-logo.mjs
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = new URL('../public/', import.meta.url).pathname;
const BRAND = '#4B7672';
const BRAND_LIGHT = '#CFE0DE';

const f = (n) => Number(n.toFixed(2));

// ---------- ÍCONE (viewBox 0 0 320 320, centro 160,160) ----------
function petal(cx, cy, angleDeg, len, width) {
  // pétala fechada: base em (cx,cy), ponta a `len` na direção `angleDeg`, bojo `width`
  const a = (angleDeg * Math.PI) / 180;
  const ux = Math.cos(a), uy = Math.sin(a);
  const px = -uy, py = ux; // perpendicular
  const tx = cx + ux * len, ty = cy + uy * len;
  const mx = cx + ux * len * 0.5, my = cy + uy * len * 0.5;
  const c1 = [mx + px * width, my + py * width];
  const c2 = [mx - px * width, my - py * width];
  return `M${f(cx)} ${f(cy)} Q${f(c1[0])} ${f(c1[1])} ${f(tx)} ${f(ty)} Q${f(c2[0])} ${f(c2[1])} ${f(cx)} ${f(cy)}Z`;
}

function wavyRing(cx, cy, r, amp, waves, phase, steps = 360) {
  const pts = [];
  for (let i = 0; i < steps; i++) {
    const t = (i / steps) * Math.PI * 2;
    const rr = r + amp * Math.sin(waves * t + phase);
    pts.push(`${f(cx + rr * Math.cos(t))} ${f(cy + rr * Math.sin(t))}`);
  }
  return `M${pts.join('L')}Z`;
}

function iconPaths({ ring = true, simple = false, sw = 9 } = {}) {
  const cx = 160, cy = 160;
  const g = [];
  const stroke = `fill="none" stroke="currentColor" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round"`;

  if (ring) {
    g.push(`<circle cx="${cx}" cy="${cy}" r="112" ${stroke}/>`);
    g.push(`<path d="${wavyRing(cx, cy, 134, 9, 9, 0)}" ${stroke}/>`);
    g.push(`<path d="${wavyRing(cx, cy, 134, 9, 9, Math.PI)}" ${stroke}/>`);
  }

  const inner = [];
  // lótus: 5 pétalas cheias + 2 folhas baixas, base escondida atrás do rim do cesto
  const bx = 160, by = 216; // base abaixo do rim: pétalas 'nascem' do cesto
  const petals = simple
    ? [[-90, 122, 56], [-128, 100, 48], [-52, 100, 48]]
    : [
      [-90, 122, 54],
      [-116, 106, 48], [-64, 106, 48],
      [-146, 86, 42], [-34, 86, 42],
      [-172, 74, 26], [-8, 74, 26],
    ];
  inner.push(`<clipPath id="above-rim"><rect x="0" y="0" width="320" height="208"/></clipPath>`);
  inner.push(`<g clip-path="url(#above-rim)">${petals.map(([ang, len, w]) => `<path d="${petal(bx, by, ang, len, w)}" ${stroke}/>`).join('')}</g>`);

  // cesto: bojo + rim + ondas da trama (clipadas no bojo)
  const bowl = `M96 208 Q160 290 224 208Z`;
  inner.push(`<clipPath id="bowl"><path d="${bowl}"/></clipPath>`);
  inner.push(`<path d="${bowl}" fill="#fff" fill-opacity="0" ${stroke}/>`);
  inner.push(`<path d="M90 208 H230" ${stroke}/>`);
  const weave = [];
  for (const y of [226, 244, 262]) {
    const pts = [];
    for (let x = 96; x <= 224; x += 4) pts.push(`${x} ${f(y + 3.5 * Math.sin(((x - 96) / 14) * Math.PI))}`);
    weave.push(`M${pts.join('L')}`);
  }
  if (!simple) inner.push(`<path d="${weave.join(' ')}" ${stroke} stroke-width="${sw * 0.7}" clip-path="url(#bowl)"/>`);

  // sem anel (favicon): amplia lótus+cesto para preencher a caixa
  const wrap = ring ? '' : ' transform="translate(160 160) scale(1.55) translate(-160 -176)"';
  g.push(`<g${wrap}>${inner.join('\n    ')}</g>`);
  return g.join('\n    ');
}

// ---------- WORDMARK monoline (cap height 100) ----------
const H = 92;
const arc = (cx, cy, rx, ry, a0, a1, large, sweep) => {
  const P = (a) => `${f(cx + rx * Math.cos((a * Math.PI) / 180))} ${f(cy + ry * Math.sin((a * Math.PI) / 180))}`;
  return `M${P(a0)} A${rx} ${ry} 0 ${large} ${sweep} ${P(a1)}`;
};
const LETTERS = {
  C: { w: 78, d: arc(39, H / 2, 39, H / 2, -48, 48, 1, 0) },
  E: { w: 50, d: `M48 0 H0 V${H} H48 M0 ${H / 2} H40` },
  S: { w: 66, d: `${arc(33, H / 4, 30, H / 4, -25, 90, 1, 0)} A30 ${H / 4} 0 1 1 ${f(33 + 30 * Math.cos((155 * Math.PI) / 180))} ${f((3 * H) / 4 + (H / 4) * Math.sin((155 * Math.PI) / 180))}` },
  T: { w: 66, d: `M0 0 H66 M33 0 V${H}` },
  A: { w: 74, d: `M0 ${H} L37 0 L74 ${H} M12 ${f(H * 0.66)} H62` },
  Z: { w: 62, d: `M0 0 H62 L0 ${H} H62` },
  N: { w: 66, d: `M0 ${H} V0 L66 ${H} V0` },
};

function wordmarkPaths(text, x0, y0, gap = 16, sw = 9.5) {
  let x = x0;
  const out = [];
  for (const ch of text) {
    const L = LETTERS[ch];
    out.push(`<path transform="translate(${f(x)} ${y0})" d="${L.d}" fill="none" stroke="currentColor" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round"/>`);
    x += L.w + gap;
  }
  return { svg: out.join('\n    '), width: x - gap - x0 };
}

// ---------- montagem dos SVGs ----------
function iconSvg(color, opts) {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 320" width="320" height="320" role="img" aria-label="CestaZen">
  <g color="${color}">
    ${iconPaths(opts)}
  </g>
</svg>`;
}

function horizontalSvg(color) {
  const wm = wordmarkPaths('CESTAZEN', 368, (330 - H) / 2);
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 330" width="1000" height="330" role="img" aria-label="CestaZen">
  <g color="${color}">
    <g transform="translate(5 5)">
    ${iconPaths()}
    </g>
    ${wm.svg}
  </g>
</svg>`;
}

// ---------- raster ----------
async function render(page, svg, { w, h, bg = null, pad = 0, type = 'png', quality }) {
  await page.setViewportSize({ width: w, height: h });
  await page.setContent(`<!doctype html><html><body style="margin:0;width:${w}px;height:${h}px;background:${bg ?? 'transparent'};display:flex;align-items:center;justify-content:center;padding:${pad}px;box-sizing:border-box">
    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center">${svg.replace(/width="\d+" height="\d+"/, 'style="width:100%;height:100%"')}</div>
  </body></html>`);
  return page.screenshot({ type, quality, omitBackground: bg === null, fullPage: false });
}

function ico(pngs) {
  // ICO com entradas PNG (suportado por todos os browsers modernos)
  const header = Buffer.alloc(6);
  header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(pngs.length, 4);
  const entries = [];
  let offset = 6 + 16 * pngs.length;
  for (const { size, buf } of pngs) {
    const e = Buffer.alloc(16);
    e.writeUInt8(size === 256 ? 0 : size, 0); e.writeUInt8(size === 256 ? 0 : size, 1);
    e.writeUInt8(0, 2); e.writeUInt8(0, 3); e.writeUInt16LE(1, 4); e.writeUInt16LE(32, 6);
    e.writeUInt32LE(buf.length, 8); e.writeUInt32LE(offset, 12);
    entries.push(e); offset += buf.length;
  }
  return Buffer.concat([header, ...entries, ...pngs.map((p) => p.buf)]);
}

const write = (rel, data) => { fs.writeFileSync(path.join(ROOT, rel), data); console.log('wrote', rel); };

const logo = horizontalSvg(BRAND);
const logoDark = horizontalSvg(BRAND_LIGHT);
const icon = iconSvg(BRAND);
const iconSmall = iconSvg(BRAND, { ring: false, simple: true, sw: 18 });
const iconWhiteSmall = iconSvg('#FFFFFF', { ring: false, simple: true, sw: 18 });

write('assets/logo/cestazen.svg', logo);
write('assets/logo/cestazen-dark.svg', logoDark);
write('assets/logo/cestazen-icon.svg', icon);

const browser = await chromium.launch();
const page = await browser.newPage({ deviceScaleFactor: 1 });

// logos oficiais
write('assets/logo/cestazen-bg-transparente.png', await render(page, logo, { w: 2000, h: 660 }));
write('assets/logo/cestazen-bg-branco.jpg', await render(page, logo, { w: 2000, h: 660, bg: '#fff', type: 'jpeg', quality: 92 }));

// assets do layout
write('assets/media/app/default-logo.png', await render(page, logo, { w: 1000, h: 330 }));
write('assets/media/app/default-logo-dark.png', await render(page, logoDark, { w: 1000, h: 330 }));
write('assets/media/app/mini-logo.png', await render(page, icon, { w: 320, h: 320 }));

// favicons (versão simplificada, sem trança, traço grosso)
const fav16 = await render(page, iconSmall, { w: 16, h: 16 });
const fav32 = await render(page, iconSmall, { w: 32, h: 32 });
write('assets/media/app/favicon-16x16.png', fav16);
write('assets/media/app/favicon-32x32.png', fav32);
write('assets/media/app/favicon.ico', ico([{ size: 16, buf: fav16 }, { size: 32, buf: fav32 }]));
write('favicon.ico', ico([{ size: 16, buf: fav16 }, { size: 32, buf: fav32 }]));
write('assets/media/app/apple-touch-icon.png', await render(page, icon, { w: 180, h: 180, bg: '#fff', pad: 14 }));

// PWA
write('assets/media/app/pwa/icon-192x192.png', await render(page, icon, { w: 192, h: 192, bg: '#fff', pad: 14 }));
write('assets/media/app/pwa/icon-512x512.png', await render(page, icon, { w: 512, h: 512, bg: '#fff', pad: 36 }));
write('assets/media/app/pwa/icon-maskable-512x512.png', await render(page, iconWhiteSmall, { w: 512, h: 512, bg: BRAND, pad: 100 }));

// Open Graph 1200x630
await page.setViewportSize({ width: 1200, height: 630 });
await page.setContent(`<!doctype html><html><body style="margin:0;width:1200px;height:630px;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px;font-family:system-ui,sans-serif">
  <div style="width:900px">${logo.replace(/width="\d+" height="\d+"/, 'style="width:100%;height:auto"')}</div>
  <p style="margin:0;color:${BRAND};font-size:30px;letter-spacing:.02em">Controle de gastos pessoais via importação de NFC-e</p>
</body></html>`);
write('assets/media/app/og-image.png', await page.screenshot({ type: 'png' }));

await browser.close();
