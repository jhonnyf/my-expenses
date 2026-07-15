import { createHash } from 'crypto';
import { readFileSync, readdirSync, statSync, writeFileSync } from 'fs';
import { join } from 'path';

const CACHED_ROOTS = ['public/assets', 'public/build'];
const swPath = 'public/sw.js';

function walk(dir) {
    let files = [];
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const full = join(dir, entry.name);
        files = entry.isDirectory() ? files.concat(walk(full)) : files.concat(full);
    }
    return files;
}

const files = CACHED_ROOTS.flatMap(walk).sort();

const hash = createHash('sha256');
for (const file of files) {
    const stat = statSync(file);
    hash.update(`${file}:${stat.size}:${stat.mtimeMs}`);
}

const digest = hash.digest('hex').slice(0, 10);

const sw = readFileSync(swPath, 'utf8');
const stamped = sw.replace(
    /const CACHE_NAME = '[^']*';/,
    `const CACHE_NAME = 'my-expenses-static-${digest}';`
);

writeFileSync(swPath, stamped);
console.log(`sw.js CACHE_NAME atualizado para my-expenses-static-${digest}`);
