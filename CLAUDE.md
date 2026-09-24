# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Comandos Principais

```bash
# Configuração inicial
composer setup

# Desenvolvimento (sobe servidor, queue, logs e vite em paralelo)
composer dev

# Testes — SEMPRE dentro do container Docker (o PHP do host não tem a extensão gd,
# causando falsos positivos em testes de upload de avatar/imagem)
docker exec CestaZen php artisan test

# Rodar um único teste
docker exec CestaZen php artisan test --filter NomeDoTeste

# Lint / formatação (Laravel Pint, preset padrão — sem pint.json customizado)
./vendor/bin/pint

# Migrações
php artisan migrate
```

Nota: `composer.json` exige PHP `^8.2`, mas o `Dockerfile` usa `php:8.4-fpm-alpine` — considerar 8.4 como a versão real de runtime.

## Arquitetura

Aplicação Laravel 12 para controle de gastos pessoais via importação de NFC-e (Nota Fiscal de Consumidor Eletrônica). Stack: Sanctum (API v1), Socialite (login social Google/Facebook/Apple), Scramble (docs de API automáticas, só em `local`/`staging`), dompdf (export de relatórios em PDF).

Padrão geral: Controllers finos delegam para **Services** (regra de negócio: `BudgetService`, `CategoryService`, `DashboardService`, `InvoiceService`, `IssuerService`, `PriceHistoryService`, `RecurringPurchaseService`, `ReportService`, `SearchService`) e **Actions** (operação pontual e transacional: `ImportInvoiceAction`, `FindOrCreateSocialUser`, `UpdateUserAvatarAction`, `DeleteInvoiceAction`, `MergeCategoriesAction`, `NotifyBudgetThresholdsAction`). `IssuerService` concentra todo acesso a emissor (web e API): o emissor só é listado/aberto/favoritado/apelidado se o usuário tiver ao menos uma nota dele (senão 404), e a lista só mostra emissores com nota autorizada. `PriceComparisonService`/`PriceHistoryService` (página Preços): o comparativo vale o preço ATUAL — a compra mais recente de cada produto em cada mercado, com `is_stale` acima de `FRESH_DAYS` (90) e os atuais primeiro do menor ao maior (`min_price`/`avg_price` são só histórico); produto vendido em unidades diferentes não se compara (`unit`); preço zero não conta; o produto é resolvido por `ProductAliasService::descriptionsFor()` (igualdade indexada) e a linha do tempo devolve as compras mais RECENTES. O alerta de queda de preço (`cheapestOffer`) só olha ofertas atuais. Notificações (sino do topo, `notifications.js`; sem página própria): `NotificationPresenter` traduz a notificação de banco em `kind` (`price_drop`/`budget_warning`/`budget_exceeded`/`generic`), `level`, `message` em texto puro e `url` (tela relacionada) — web e API usam `NotificationResource`, que mantém `type`/`data` por compatibilidade; o front só exibe `message` (tipo novo aparece sem mexer no JS/app); `unread-count` leve no carregamento das páginas, lista só ao abrir o sino, `read-all`, e `notifications:prune` diário apaga as de mais de 90 dias. Planos: `config/plans.php` é a fonte única dos recursos Grátis x Pro (página `subscription/upgrade`, `GET /api/v1/subscription/plans` e mensagens de paywall); rotas Pro usam `pro:<recurso>` (a chave tem de existir no config — há teste) e o bloqueio devolve mensagem do recurso, destaca a linha na página e, na API, `feature` no 402; Pro que abre a página vê "Você já é Pro". `AccountService` (estatísticas da Conta em 2 consultas; "membro desde" é `users.created_at`). Conta: `UpdateAccountAction` (trocar e-mail exige a senha atual, zera `email_verified_at` e reenvia a verificação; cidade/estado enviados vazios APAGAM a localização, campos ausentes não mexem), `RevokeUserSessionsAction` (sessões `database`, `remember_token` e tokens Sanctum — usada ao trocar a senha, em "sair dos outros dispositivos" e na exclusão, que também apaga `notifications`); conta sem senha (login social) define a senha sem "senha atual" e exclui a conta sem senha; o avatar é regravado sem EXIF quando há GD; `throttle:5,1` em senha/exclusão/sessões. `RecurringPurchaseService` (Compras Recorrentes, Pro): só notas autorizadas dos últimos 24 meses, preço > 0 e ≥ 3 DIAS de compra distintos; intervalo = mediana, frequência numa janela recente, unidades separadas (`unit`), situação `late/due/soon/ok/inactive` (parado = sem comprar por >3 intervalos, 30–180 dias), melhor mercado = menor preço ATUAL (`is_stale` acima de 90 dias); o usuário oculta produtos (`RecurringDismissal`), adiciona a uma lista (sem `shopping_list_id` cria lista nova) e gera a lista de reposição do que está na hora. `ShoppingListService` concentra a lista de compras (web e API): a busca cruza os itens de todos os usuários e devolve, para cada produto em cada mercado, só a compra mais recente (preço atual), ordenada do menor ao maior preço — preços de mais de `FRESH_DAYS` (90) vêm depois, com `is_stale`; adicionar item repetido (mesmo produto e mercado, ainda pendente) soma a quantidade; também atualiza preços, sugere economia, duplica e marca tudo como comprado. `ReportService` monta o relatório: sem filtro de categoria/produto o total é o das notas (líquido de desconto, igual às demais telas), com filtro é a soma dos itens que casam (`summary->is_partial`); itens vão paginados para tela e API, o CSV é lido em blocos (`streamCsv`) e neutraliza fórmulas de planilha, o PDF leva no máximo `PDF_MAX_ITEMS`. O e-mail do relatório (`SendReportByEmailJob`) pode ser recorrente: `ReportSchedule` + comando diário `reports:send-scheduled` (às 6h, plano Pro; `ReportFrequency` define período e dia de envio). `BudgetService` calcula os orçamentos (limites mensais) de um mês navegável: gasto por categoria (soma dos itens) ou Geral (soma das notas, líquido de desconto), comparação com o mês anterior, projeção do mês e resumo — com orçamento Geral, os totais usam só ele (o Geral já inclui as categorias). Após `InvoiceImported`, `CheckBudgetThresholdsListener` avisa (notificação no banco) ao chegar a 80% e a 100%, uma vez por patamar e mês (`budgets.alerted_level`/`alerted_month`). Excluir uma categoria apaga o orçamento dela (`Category::booted`). `CategoryService` também dá o detalhe, os itens sem categoria, a prévia de palavras-chave e a reversão da auto-categorização; comparação com o período anterior usa `Support\Period`. `InvoiceService` concentra a lista de notas (web e API): busca, filtros, ordenação e totais — a lista mostra também notas pendentes/não confirmadas, os totais só contam as autorizadas. Duas famílias de Strategy pattern via interface + DI:
- `Import/Strategies/` (`ImportStrategyInterface`) — `XmlFileImportStrategy`, `QrCodeImportStrategy`, `AccessKeyImportStrategy`, usadas por `Api\V1\InvoiceController`.
- `Search/Strategies/` (`SearchStrategyInterface`) — `InvoiceSearchStrategy`, `IssuerSearchStrategy`, `ProductSearchStrategy`, usadas por `SearchService`/`SearchController` (busca global).

Validação via Form Requests (nas rotas **web**, falha de validação de uma requisição JSON vira redirect 302, não 422 — Form Requests chamados via AJAX devem usar `Requests/Concerns/FailsAsJson`), serialização de API via API Resources (`Http/Resources/Api/V1/`), autorização via Policies (`BudgetPolicy`, `CategoryPolicy`, `InvoicePolicy`, `ShoppingListPolicy`; `BudgetPolicy`, `InvoicePolicy`, `ShoppingListPolicy` e `CategoryPolicy::view` respondem 404, não 403, para dado de outro usuário). Após importar uma nota, o evento `InvoiceImported` é disparado e o `AutoCategorizeListener` tenta categorizar os itens automaticamente (registrado em `AppServiceProvider`).

### Fluxo principal de importação (caminho atual, API v1)

1. O usuário autenticado (Sanctum) importa uma NFC-e por 3 vias possíveis: upload de XML (`POST /api/v1/invoices/import/xml`), QR Code (`.../import/qrcode`) ou chave de acesso via SEFAZ (`.../import/key`)
2. `Api\V1\InvoiceController` resolve a estratégia correspondente (`ImportStrategyInterface::resolve()`), que devolve um `ImportPayload` (dados parseados + XML bruto)
3. `ImportInvoiceAction::execute()` persiste em transação: `Issuer::firstOrCreate` (emitente, por CNPJ — nome não é atualizado em imports subsequentes), `Invoice::updateOrCreate` (vinculada ao `user_id` autenticado, não a um destinatário do XML) e sincroniza `InvoiceItem`/`InvoicePayment`
4. O XML bruto é salvo no campo `raw_xml` da tabela `invoices`; duplicidade é checada por `user_id` + `access_key`

`MyPurchaseController` (rotas `my-purchases.*` e `POST /api/nfce/upload`) usa o mesmo `ImportInvoiceAction` + `ImportStrategyInterface` do fluxo atual (API v1), vinculado ao usuário autenticado via `Auth::id()`.

- **`NfceXmlImporter`** — parseia XML de NFC-e (SimpleXML, namespace `http://www.portalfiscal.inf.br/nfe`). Retorna array com `chave`, `emitente`, `destinatario`, `itens`, `total`, `pagamento`. Usado pelas `Import/Strategies/`.
- **`NFCeService`** — integração com SEFAZ via certificado digital (`nfephp-org/sped-nfe`). Consulta NFC-e por chave de acesso ou QR Code. Requer variáveis `NFE_*`.

> O antigo fluxo de debug (`NfceImportController`/`GET|POST /api/nfce/importar` + `InvoiceService`) foi removido: criava um `User`/`UserProfile` a partir do CPF/CNPJ do destinatário do XML, sem consentimento da pessoa titular desses dados — incompatível com a LGPD. Não recriar esse padrão (criação de conta a partir de dado de terceiro sem interação do titular).

### Modelos e banco

| Tabela | Model | Descrição |
|---|---|---|
| `issuers` | `Issuer` | Emitentes (lojas) identificados por CNPJ; nome fixado no 1º import (ver `IssuerNickname` para apelido por usuário) |
| `issuer_nicknames` | `IssuerNickname` | Apelido de emitente por usuário, sobrepõe `Issuer.name` na exibição |
| `invoices` | `Invoice` | Notas fiscais; `access_key` (44 chars) único por `user_id` |
| `invoices_items` | `InvoiceItem` | Itens das notas; `item_number` + `invoice_id` |
| `invoice_payments` | `InvoicePayment` | Pagamentos das notas |
| `categories` | `Category` | Categorias de despesa (com keywords para auto-categorização) |
| `budgets` | `Budget` | Orçamentos por categoria (ou geral) |
| `shopping_lists` / `shopping_list_items` | `ShoppingList` / `ShoppingListItem` | Listas de compras |
| `users` | `User` | Usuários autenticados (via Sanctum) |
| `users_profiles` | `UserProfile` | CPF/CNPJ do usuário |

### Rotas web (autenticadas via middleware `auth`, exceto onde indicado)

Públicas: `register`, `login` (+ `login/social/{provider}` e callback), `forgot-password`, `reset-password` (todas com `throttle:5,1` ou `throttle:10,1` nos POSTs de auth social).

Autenticadas: `dashboard`, `issuers` (+ `favorite`, `nickname`), `my-purchases` (upload/import, exclusão de nota), `categories` (+ `assign-item`, `auto-categorize`), `price-history`, `search` (busca global), `budgets`, `reports` (+ export `pdf`/`csv`), `recurring-purchases` (+ `add-to-list`, `replenishment-list`, `dismiss`, `restore`), `shopping-list` (+ itens: add/update/remove/toggle-purchased), `account` (+ `password`, `avatar`, `sessions/revoke-others`, `export`).

### Rotas de API (`routes/api.php`)

- `POST /api/nfce/upload` (`web` + `auth`): mapeia para `MyPurchaseController::upload`, mesmo fluxo moderno (`ImportInvoiceAction`) das rotas `my-purchases.*`.
- **v1 pública** (prefixo `api/v1/auth`, `throttle:api-auth` 10/min por IP): `login`, `register`, `forgot-password`, `reset-password`, `social/{provider}`.
- **v1 protegida** (`auth:sanctum`, `throttle:api` 60/min por usuário): espelha os módulos web — `dashboard`, `search`, `invoices` (+ import xml/qrcode/key, exclusão), `issuers` (+ favorite/nickname), `categories`, `budgets`, `reports`, `price-history` (+ `timeline`), `recurring-purchases`, `shopping-lists` (+ itens), `account` (+ `avatar`).

Docs de API auto-geradas via Scramble, disponíveis só em `local`/`staging`.

### Views

Blade com layouts em `resources/views/layout/`. Layout principal: `layout.main` (autenticado) e `layout.main-login` (público).

### Configuração NFC-e (config/nfe.php)

Requer variáveis de ambiente para integração com SEFAZ via certificado digital:
```
NFE_CNPJ, NFE_RAZAO_SOCIAL, NFE_UF, NFE_AMBIENTE
NFE_CERTIFICADO_PATH, NFE_CERTIFICADO_SENHA
NFE_CSC, NFE_CSC_ID
```
O certificado `.pfx` deve estar em `storage/app/private/certificado.pfx` por padrão. Estas variáveis só são necessárias se usar `NFCeService` (consulta SEFAZ); o upload de XML local funciona sem elas. Elas não constam em `.env.example` — configurar manualmente se for usar consulta SEFAZ.

### Convenção de commits

Mensagens em inglês, estilo Conventional Commits (`feat:`, `fix:`, `refactor:`), imperativo — mesmo com conversas em pt-BR.
