# my-expenses

Aplicação Laravel 12 para controle de gastos pessoais a partir da importação de NFC-e (Nota Fiscal de Consumidor Eletrônica). Permite importar notas via upload de XML, QR Code ou chave de acesso (consulta SEFAZ), categorizar itens automaticamente, acompanhar orçamentos, histórico de preços, listas de compras e gerar relatórios.

## Stack

- **Laravel 12** (PHP `^8.2`, runtime real via Docker: PHP 8.4)
- **Laravel Sanctum** — autenticação de API (v1)
- **Laravel Socialite** — login social (Google, Facebook, Apple)
- **Scramble** — documentação de API automática (disponível apenas em `local`/`staging`)
- **barryvdh/laravel-dompdf** — exportação de relatórios em PDF
- **nfephp-org/sped-nfe** — integração com SEFAZ para consulta de NFC-e por certificado digital
- **Vite + Tailwind CSS 4** — build de assets front-end

## Funcionalidades

- Importação de NFC-e por upload de XML, QR Code ou chave de acesso
- Categorização automática de itens (via listener disparado após importação)
- Orçamentos por categoria ou gerais
- Histórico de preços por produto/emitente
- Listas de compras e compras recorrentes
- Busca global (notas, emitentes, produtos)
- Relatórios com exportação em PDF e CSV
- API REST versionada (`/api/v1`) autenticada via Sanctum, com throttling dedicado

## Requisitos

- Docker e Docker Compose (ambiente recomendado)
- PHP 8.4, Composer e Node.js 18+ (caso rode fora do Docker)

## Instalação

Suba os containers:

```bash
docker compose up -d
```

Configuração inicial (instala dependências PHP/JS, gera `.env`, `APP_KEY` e roda migrations):

```bash
docker exec my-expenses composer setup
```

Ou manualmente, fora do container:

```bash
composer setup
```

## Desenvolvimento

Sobe servidor, worker de fila, logs (Pail) e Vite em paralelo:

```bash
composer dev
```

## Testes

Os testes **sempre** devem rodar dentro do container Docker — o PHP do host não possui a extensão `gd`, o que gera falsos positivos em testes de upload de avatar/imagem:

```bash
docker exec my-expenses php artisan test
```

Rodar um único teste:

```bash
docker exec my-expenses php artisan test --filter NomeDoTeste
```

## Lint

Formatação de código via Laravel Pint (preset padrão):

```bash
./vendor/bin/pint
```

## Configuração de integração com SEFAZ

Para usar a consulta de NFC-e por chave de acesso/QR Code via SEFAZ (`NFCeService`), configure as seguintes variáveis de ambiente (não presentes em `.env.example`):

```
NFE_CNPJ, NFE_RAZAO_SOCIAL, NFE_UF, NFE_AMBIENTE
NFE_CERTIFICADO_PATH, NFE_CERTIFICADO_SENHA
NFE_CSC, NFE_CSC_ID
```

O certificado digital `.pfx` deve estar em `storage/app/private/certificado.pfx` por padrão. O upload de XML local funciona sem essas variáveis.

## Documentação de API

Gerada automaticamente via Scramble, disponível apenas nos ambientes `local` e `staging`.

## Licença

Projeto pessoal, sem licença de código aberto definida.
