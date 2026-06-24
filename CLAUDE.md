# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Comandos Principais

```bash
# Configuração inicial
composer setup

# Desenvolvimento (sobe servidor, queue, logs e vite em paralelo)
composer dev

# Testes
composer test

# Rodar um único teste
php artisan test --filter NomeDoTeste

# Lint / formatação (Laravel Pint)
./vendor/bin/pint

# Migrações
php artisan migrate
```

## Arquitetura

Aplicação Laravel 12 para controle de gastos pessoais via importação de NFC-e (Nota Fiscal de Consumidor Eletrônica).

### Fluxo principal

1. O usuário faz upload de um XML de NFC-e via `POST /my-purchases/upload`
2. `MyPurchaseController` recebe o arquivo e delega para `NfceXmlImporter`
3. `NfceXmlImporter` parseia o XML usando SimpleXML com namespace `http://www.portalfiscal.inf.br/nfe`
4. Os dados retornados são persistidos em transação: cria/atualiza `Issuer` (emitente), `Invoice` (nota) e `InvoiceItem` (itens)
5. O XML bruto é salvo no campo `raw_xml` da tabela `invoices`

### Serviços

- **`NfceXmlImporter`** — parseia XML de NFC-e. Retorna array estruturado com `chave`, `emitente`, `destinatario`, `itens`, `total` e `pagamento`.
- **`InvoiceService`** — alternativa mais completa que também cria `User`/`UserProfile` a partir do destinatário. Usado pelo `NfceImportController` (endpoint de API legado em `routes/api.php`).
- **`NFCeService`** — integração com SEFAZ via certificado digital (biblioteca `nfephp-org/sped-nfe`). Consulta NFC-e por chave de acesso ou QR Code. Requer configuração via variáveis `NFE_*`.

### Modelos e banco

| Tabela | Model | Descrição |
|---|---|---|
| `issuers` | `Issuer` | Emitentes (lojas) identificados por CNPJ |
| `invoices` | `Invoice` | Notas fiscais; `access_key` (44 chars) é unique |
| `invoices_items` | `InvoiceItem` | Itens das notas; `item_number` + `invoice_id` |
| `invoice_payments` | `InvoicePayment` | Pagamentos das notas |
| `users` | `User` | Destinatários das notas |
| `users_profiles` | `UserProfile` | CPF/CNPJ do usuário |

### Rotas web (autenticadas via middleware `auth`)

- `GET /dashboard` — estatísticas gerais
- `GET /issuers` e `GET /issuers/detail/{id}` — listagem e detalhe de emitentes
- `GET /my-purchases` — listagem de notas (paginada)
- `GET /my-purchases/detail` — detalhe de nota
- `POST /my-purchases/upload` — upload de XML de NFC-e

### Rotas de API (sem autenticação)

- `GET|POST /api/nfce/importar` — importa XML de `public/import/nfc-e.xml` (legado/dev)
- `POST /api/nfce/upload` — upload de XML via API

### Views

Blade com layouts em `resources/views/layout/`. Layout principal: `layout.main` (autenticado) e `layout.main-login` (público).

### Configuração NFC-e (config/nfe.php)

Requer variáveis de ambiente para integração com SEFAZ via certificado digital:
```
NFE_CNPJ, NFE_RAZAO_SOCIAL, NFE_UF, NFE_AMBIENTE
NFE_CERTIFICADO_PATH, NFE_CERTIFICADO_SENHA
NFE_CSC, NFE_CSC_ID
```
O certificado `.pfx` deve estar em `storage/app/private/certificado.pfx` por padrão. Estas variáveis só são necessárias se usar `NFCeService` (consulta SEFAZ); o upload de XML local funciona sem elas.
