---
name: visual-check
description: Tira screenshots reais da aplicação my-expenses (Laravel + Blade + KTUI) rodando localmente, usando um Chromium headless já instalado via Playwright. Use esta skill sempre que precisar ver com os próprios olhos como uma página, componente ou correção de CSS/layout está renderizando de verdade no navegador — não apenas ler o código-fonte — especialmente quando o usuário pedir explicitamente um print, um teste visual, ou disser que algo "está estranho", "feio", "desalinhado" ou "quebrado" visualmente. Também use para confirmar que uma correção de bug visual (ex: CSS, Tailwind, Blade, JS de página) realmente resolveu o problema antes de reportar como concluído. Não use para rodar a suíte de testes automatizados (isso é `docker exec my-expenses php artisan test`) nem para tarefas que não envolvem renderização visual.
---

# Visual Check — my-expenses

Este projeto já tem toda a infraestrutura de teste visual pronta e configurada.
Não reinstale nada do zero — use o que já existe.

## Por que isso existe

Rodar um navegador headless para tirar screenshot costuma ser pesado (baixar
Chromium, criar usuário de teste, descobrir seletores). Nesse projeto esse
custo já foi pago uma vez: o Playwright está instalado como devDependency,
o binário do Chromium já está em cache no host, e existe um usuário de login
fixo e um script pronto. Use-os em vez de reconstruir o fluxo.

## Pré-requisitos (checar antes de rodar)

1. **Containers Docker rodando.** A app é servida via nginx em
   `http://localhost:8000`. Confirme com:
   ```bash
   docker ps --format '{{.Names}}\t{{.Status}}'
   ```
   Espera-se `nginx-my-expenses`, `my-expenses` (PHP) e `mysql-my-expenses`.
   Se não estiverem rodando, suba com `docker compose up -d` (ou pergunte ao
   usuário, já que subir containers é uma ação com efeito colateral).

2. **Assets front-end atualizados.** Se você editou Blade, CSS ou JS de
   página, rode `npm run build` antes de tirar o screenshot — não existe
   dev server do Vite rodando por padrão (verifique se `public/hot` existe;
   se não existir, os assets vêm de `public/build`, que só reflete mudanças
   depois de um rebuild).

3. **Usuário de login fixo criado.** O script faz login com um usuário
   seedado localmente. Se for a primeira vez neste ambiente (banco vazio ou
   recém-recriado), rode:
   ```bash
   docker exec my-expenses php artisan db:seed --class=VisualCheckUserSeeder
   ```
   Esse seeder só roda em `app()->isLocal()` — é seguro rodar em dev.

## Como tirar um screenshot

Use o script `scripts/visual-check.mjs`, exposto via npm script:

```bash
npm run visual-check -- <path> [--tab "<selector>"] [--wait-for "<selector>"] [--element "<selector>"] [--out <arquivo.png>]
```

O script faz login automaticamente (usuário `visual-check@local.test`, senha
`visual-check-password` — sobrescrevíveis via `VISUAL_CHECK_EMAIL` /
`VISUAL_CHECK_PASSWORD` / `VISUAL_CHECK_BASE_URL`), navega até `<path>`, e
salva o PNG em `storage/app/visual-checks/` (pasta gitignored — não precisa
limpar manualmente, mas pode apagar o arquivo depois de olhar).

**Argumentos:**
- `<path>` (obrigatório): rota a visitar, ex `/account`, `/dashboard`.
- `--tab`: seletor de um toggle de aba KTUI para clicar antes do screenshot
  (ver gotcha abaixo).
- `--wait-for`: seletor a esperar aparecer antes de tirar o print — use
  sempre que a página tiver conteúdo carregado via JS/AJAX.
- `--element`: em vez de screenshot da página inteira, recorta só este
  elemento (útil quando o bug é num componente específico, não na página
  toda).
- `--out`: nome do arquivo de saída (padrão: derivado do path).

**Exemplo real** (usado para depurar o card "Foto de Perfil" da página de
conta, que fica dentro de uma aba escondida por padrão):
```bash
npm run visual-check -- /account \
  --tab '[data-kt-tab-toggle="#tab_settings"]' \
  --wait-for 'text=Foto de Perfil' \
  --element '#avatar_preview_frame'
```

Depois de rodar, **use Read na imagem gerada** para olhar o resultado —
tirar o print sem olhar não prova nada.

## Gotcha: abas KTUI escondem conteúdo

Várias páginas (ex: `/account`) usam abas do KTUI onde o conteúdo real fica
com `class="hidden"` até o usuário clicar num `[data-kt-tab-toggle="#tab_x"]`.
Se o `--wait-for` nunca aparecer, é provável que o elemento exista mas esteja
escondido atrás de uma aba — inspecione o Blade para achar o toggle certo
antes de assumir que há um bug de renderização.

## Depurando problemas de CSS/layout que não fazem sentido

Se um elemento não respeita uma classe Tailwind (ex: uma largura fixa que
"não pega"), pode haver uma regra CSS global conflitante em
`public/assets/css/styles.css` (tema Metronic/KTUI, carregado antes do
`app.css` do Vite). Um caso já identificado: `div[id$="_wrapper"]` é forçado
a `width:100%!important` nesse arquivo (convenção de DataTables) — qualquer
elemento com id terminando em `_wrapper` herda isso sem querer. Para
investigar esse tipo de problema, rode um script Playwright ad-hoc que
percorra `document.styleSheets` procurando regras que casem com o elemento
(`el.matches(rule.selectorText)`), em vez de só inspecionar `getComputedStyle`
— isso revela qual regra está de fato vencendo a cascata.

## O que esta skill não substitui

- Testes automatizados: `docker exec my-expenses php artisan test`.
- Lint/formatação: `./vendor/bin/pint`.

Visual check é um complemento para quando a dúvida é sobre renderização real
(CSS, layout, um componente parecendo "estranho"), não um substituto para a
suíte de testes.
