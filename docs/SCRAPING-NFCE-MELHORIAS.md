# Scraping de NFC-e — plano de melhorias

Origem: análise do fluxo de importação por QR Code (`NFCeService`, `QrCodeImportStrategy`, `ImportInvoiceAction`, `ReconcilePendingInvoices`), feita por leitura de código em 2026-09-25. **Nada aqui foi validado contra portais reais**: as fixtures em `tests/fixtures/` são HTML escrito à mão.

Como usar: cada item tem ID, arquivos, o que fazer e critério de aceite. Trabalhar na ordem da tabela; marcar `[x]` ao concluir. Toda mudança segue as regras do projeto: teste junto (`docker exec CestaZen php artisan test`), Pint, e o que toque consulta de nota/preço mantém verdes os testes de `tests/Feature/Security/*`.

## Fluxo atual (resumo)

```
QR (câmera/URL) → ImportByQrCodeRequest → MyPurchaseController::executeImport  [throttle 10/min]
  → QrCodeImportStrategy::resolve
      chave = extrairChaveDeUrl
      com certificado A1: downloadXml (webservice) → NfceXmlImporter   (qualquer falha → scraping, sem log)
      NFCeService::consultarPorQRCode(url, chave)
          chave válida (DV) + host da UF + GET (redirects revalidados, teto 2 MB)
          página-casca com iframe? → 2º GET + decodifica script `new DanfeNFCe(...)`
          parseHtmlPortal → emitente/itens/totais/pagamento/metadados/chave
          com itens: chave e CNPJ do HTML == da URL
      sem itens: contingência → nota Pending; senão erro
      normalizarDadosPortal → mesmo formato do XML
  → checa duplicidade (user_id + access_key) → ImportInvoiceAction → InvoiceImported
Notas Pending: `invoices:reconcile-pending` (a cada 30 min, sequencial).
```

## Backlog

| ID | Prioridade | Item | Esforço |
|---|---|---|---|
| S1 | Alta | Parar de gravar o HTML bruto do portal em `raw_xml` (dado pessoal, sem uso) | P |
| S2 | Alta | Validar consistência dos dados lidos (totais, itens) antes de gravar | M |
| S3 | Alta | Validar UF em vez de truncar (regressão da sessão de segurança) | P |
| S4 | Alta | Medir cobertura por UF e diferenciar "UF não suportada" de "falha temporária" | M |
| S5 | Média | Checar duplicidade antes de chamar o portal | P |
| S6 | Média | Capturar violação do `unique(user_id, access_key)` (duplo clique → 409, não 500) | P |
| S7 | Média | Timeouts e retry curto no portal; decidir sobre importação assíncrona | M/G |
| S8 | Média | Padronizar fuso de `issued_at` (scraping × XML) | M |
| S9 | Média | Ler `tpAmb` da URL em vez de fixar `producao` | P |
| S10 | Média | Trocar `mb_convert_encoding(..., 'HTML-ENTITIES')` e tratar charset da resposta | M |
| S11 | Média | Iframe: resolver URL relativa corretamente e aceitar escapes JS não-JSON | M |
| S12 | Média | Logar o fallback do XML e não tentar webservice de UF diferente da configurada | P |
| S13 | Baixa | Exigir `https` nos portais | P |
| S14 | Baixa | Preencher CEP/NCM/CFOP/ICMS quando o portal informar; ler desconto por item | M |
| S15 | Baixa | Endereço: não assumir só o formato de SP | M |
| S16 | Contínua | Fixtures com capturas reais e sanitizadas de cada UF atendida | M |

Esforço: P = horas, M = ~1 dia, G = vários dias.

---

## S1 — Não gravar o HTML do portal em `raw_xml`

- **Problema:** `ImportInvoiceAction:93` chama `NfceXmlRedactor::redact($rawContent)`, que só entende XML. Com HTML, `loadXML` falha e devolve o texto original. O DANFE traz o CPF do consumidor quando informado (a confirmar em captura real). `raw_xml` não é lido em nenhum lugar (só o comando `invoices:redact-raw-xml`), então é dado pessoal retido sem finalidade (LGPD: minimização).
- **Fazer:**
  1. Em `QrCodeImportStrategy`, para o caminho de scraping, devolver `rawContent: ''` (o campo é nullable; conferir a coluna).
  2. Criar migração/comando para zerar `raw_xml` das notas já importadas por scraping (as que não começam com `<?xml`/`<nfeProc`), com `--dry-run` como o comando existente.
  3. Manter o XML redigido do caminho do webservice/upload.
- **Aceite:** nota importada por QR (scraping) fica com `raw_xml = null`; comando de limpeza conta e zera as antigas; teste garante que nenhum HTML é persistido.
- **Arquivos:** `app/Import/Strategies/QrCodeImportStrategy.php`, `app/Actions/ImportInvoiceAction.php`, `app/Console/Commands/RedactInvoicesRawXml.php` (ou novo comando), testes de importação.

## S2 — Validação de consistência antes de gravar

- **Problema:** seletor que não casa vira `0.0`/`''` e a nota é gravada assim. Casos: sem "Valor a pagar" → `valor_nota = 0` (nota entra com total R$ 0); `valor_tributos` usa o primeiro `span.txtObs` com `parseBrDecimal`, que não remove "R$" nem texto (`(float)` de texto dá 0, "Impostos" do dashboard subestimado); nunca se compara soma dos itens × total nem a "Qtd. total de itens" do portal.
- **Fazer:**
  1. `parseBrDecimal`: remover tudo que não for dígito, `,`, `.` e `-` antes de converter (cobre "R$ 0,89", nbsp).
  2. Fallback: se `valor_nota == 0` e há `valor_produtos`, usar `valor_produtos - valor_desconto`.
  3. Novo método (ex.: `garantirTotaisCoerentes`) chamado junto de `garantirNotaDaChave`, com tolerância (ex.: R$ 0,05):
     - soma dos `valor_total` dos itens ≈ `valor_produtos`;
     - `quantidade × valor_unitario ≈ valor_total` por item (tolerância por arredondamento);
     - nº de itens lidos == "Qtd. total de itens" quando o portal informar.
  4. Divergência → `InvalidArgumentException` com mensagem clara ("não foi possível ler esta nota com segurança") e log com UF e motivo (sem dado pessoal).
- **Aceite:** testes com fixtures adulteradas (total 0, item sem valor, soma divergente, "R$" no tributo) falham na importação; fixtures válidas continuam passando.
- **Arquivos:** `app/Services/NFCeService.php` (`parseBrDecimal`, `extrairTotaisDoHtml`, `consultarPorQRCode`), `tests/Unit/Services/NFCeServiceTest.php`.

## S3 — Validar UF em vez de truncar

- **Problema (regressão minha):** `ImportInvoiceAction::findOrCreateIssuer` usa `Str::limit($uf, 2, '')`. Se `parsearEndereco` deslocar os campos, grava lixo (`"SA"` de "SAO PAULO") em silêncio e isso alimenta o filtro por cidade/estado da Lista de Compras e do Preços. Antes, o `string(2)` derrubava a importação.
- **Fazer:** validar `uf` contra `array_keys(config('brazilian-states'))` (maiúscula); se não bater, gravar `''` (ou usar a UF da chave, `extrairUF`, que é confiável). Aplicar também a `zip_code` (só dígitos, 8).
- **Aceite:** emitente com `uf = 'SAO PAULO'` grava a UF da chave; teste cobre endereço deslocado.
- **Arquivos:** `app/Actions/ImportInvoiceAction.php`, `app/Services/NFCeService.php` (`parsearEndereco`).

## S4 — Cobertura por UF e mensagens de erro úteis

- **Problema:** os seletores (`tabResult`, `txtTit`, `RCod`, `totalNota`, `linhaTotal`, `chave`...) são de um template só. UF com HTML diferente, captcha ou conteúdo por JS cai em "O portal da SEFAZ não retornou os dados", e o usuário não sabe se é temporário ou não suportado.
- **Fazer:**
  1. Consulta/relatório sobre `qrcode_reads` (o log redigido guarda os 20 primeiros dígitos da chave: dígitos 1–2 = UF) com taxa de sucesso por UF e por `error_message`. Pode ser um comando `php artisan qrcode:coverage`.
  2. Distinguir as causas no erro: portal fora (HTTP 5xx/timeout) × página sem itens numa emissão normal ("este portal ainda não é suportado, use outro meio") × chave divergente.
  3. Lista de UFs suportadas em `config/nfe.php` (a preencher com o que for validado em S16) e aviso claro para as demais.
- **Aceite:** comando lista sucesso/erro por UF; mensagens diferentes para os três casos, com teste.

## S5 — Duplicidade antes do scraping

- **Problema:** `MyPurchaseController:143` (e o equivalente na API) checa `user_id + access_key` depois do `resolve()`. Reimportar a mesma nota espera o portal inteiro (até ~30 s) para responder 409, embora a chave já esteja na URL.
- **Fazer:** extrair a chave da URL/campo e checar antes de chamar a estratégia, com o escopo padrão (só notas autorizadas) para não impedir que uma nota `Pending` se complete (há teste: `reimporting a pending note after authorization completes it`).
- **Aceite:** com nota autorizada já existente, nenhuma requisição HTTP é feita (`Http::assertNothingSent`) e a resposta é 409; o caso pendente continua funcionando.
- **Arquivos:** `app/Http/Controllers/MyPurchaseController.php`, `app/Http/Controllers/Api/V1/InvoiceController.php`.

## S6 — Corrida em duplo clique

- **Problema:** existe `unique(user_id, access_key)`, mas o `updateOrCreate` não é atômico. A 2ª requisição simultânea levanta `UniqueConstraintViolationException`, não capturada → 500.
- **Fazer:** capturar a exceção no `executeImport` (web e API) e responder como duplicidade (409 / mesmo erro de "já importada").
- **Aceite:** teste simula a violação (mock/duas chamadas) e espera 409.

## S7 — Timeouts, retry e importação assíncrona

- **Problema:** dois GETs sequenciais com `timeout(15)`, sem `connectTimeout`, sem retry, sem circuito por host. Um portal lento ocupa workers do PHP-FPM por até ~30 s (o CLAUDE.md pede trabalho externo em fila).
- **Fazer (em etapas):**
  1. Imediato: `connectTimeout(5)` e `retry(2, 300, fn ($e) => 5xx ou ConnectionException, throw: false)`.
  2. Circuito simples por host (cache: N falhas seguidas → pausa curta) para não empilhar workers.
  3. Decisão de produto: importação assíncrona (a nota nasce `Pending`, um job consulta o portal e a tela mostra o andamento; a reconciliação já existe). Só se a medição de S4 mostrar latência ruim.
- **Aceite (etapa 1):** teste com `Http::fakeSequence` (1ª resposta 503, 2ª 200) importa; portal fora não segura a requisição além do teto.

## S8 — Fuso horário de `issued_at`

- **Problema:** `app.timezone = UTC`. Scraping grava `"2026-06-01T10:00:00"` (hora local lida como UTC → 10:00); XML traz `dhEmi` com `-03:00` (convertido para UTC → 13:00). A mesma compra aparece com 3 h de diferença conforme o caminho, e notas noturnas caem no dia/mês seguinte nos filtros.
- **Fazer:** decidir o fuso da aplicação (`America/Sao_Paulo`) **ou** normalizar as duas entradas (scraping: interpretar em `America/Sao_Paulo` e converter; XML: já converte). Avaliar o impacto em dados existentes (uma migração de ajuste só se necessário) e nos filtros "este mês"/orçamento.
- **Aceite:** mesma nota por XML e por scraping resulta no mesmo `issued_at`; teste de fronteira de mês (23:30 local do último dia).
- **Atenção:** mudança transversal; fazer isolada e com teste dos períodos (`Support\Period`, `BudgetService`).

## S9 — `tpAmb` da URL

- **Problema:** `normalizarDadosPortal` fixa `'ambiente' => 'producao'` (`NFCeService:766`), mas o QR traz `tpAmb` em `p=chave|versão|tpAmb|...`. Nota de homologação aparece como produção.
- **Fazer:** ler o campo (posição 2 do `p=`; `1` = produção, `2` = homologação) e repassar; padrão `producao` se ausente.
- **Aceite:** teste com `p=...|2|2|...` gera `environment = staging`.

## S10 — `HTML-ENTITIES` e charset

- **Problema:** `mb_convert_encoding(..., 'HTML-ENTITIES', 'UTF-8')` (linhas 366 e 477) é obsoleto desde o PHP 8.2 e sai no PHP 9. O charset da resposta é ignorado: portal em ISO-8859-1 seria lido como UTF-8 e estragaria acentos.
- **Fazer:** detectar o charset (header `Content-Type` ou `<meta>`), converter para UTF-8 e carregar com `loadHTML('<?xml encoding="UTF-8">'.$html)` (ou `Dom\HTMLDocument` no PHP 8.4, o runtime real).
- **Aceite:** teste com corpo ISO-8859-1 mantém acentos; sem aviso de depreciação.

## S11 — Iframe

- **Problema:** (a) `resolverUrlAbsoluta` trata `src` relativo como se fosse da raiz do host; (b) `json_decode('"'.$js.'"')` recusa escapes que só existem em JS (`\x3C`, `\v`, `\0`) e devolve "formato inesperado" com a página correta.
- **Fazer:** (a) resolver com `GuzzleHttp\Psr7\UriResolver::resolve(new Uri($base), new Uri($src))` e revalidar com `validarUrlSefaz`; (b) converter `\xNN` e `\0`/`\v` antes do `json_decode`.
- **Aceite:** testes com `src` relativo (`render/abc` e `../render/abc`) e com `\x3C` no script.

## S12 — Fallback do XML

- **Problema:** `QrCodeImportStrategy` engole qualquer `\Throwable` do webservice e cai no scraping sem registro. O certificado é de uma UF fixa (`nfe.uf`), então notas de outros estados provavelmente falham sempre, com latência extra a cada importação.
- **Fazer:** logar (nível `info`, só UF e classe da exceção) e não tentar o webservice quando `extrairUF($chave) !== config('nfe.uf')`, salvo autorizador compartilhado.
- **Aceite:** teste com certificado configurado e chave de outra UF não chama `downloadXml`.

## S13 — Só `https`

- **Fazer:** `protocols => ['https']` e `validarUrlSefaz` recusar `http`. **Antes:** conferir nos logs de `qrcode_reads` se algum QR real usa `http://`.

## S14 — Campos que o scraping não preenche

- CEP (`''`), NCM, CFOP, base de ICMS/ICMS e desconto por item não são lidos. Ler o que o DANFE mostrar (ao menos CEP e desconto por item) e documentar o que continua vazio.

## S15 — Endereço

- `parsearEndereco` assume "RUA, NÚMERO, COMPL, BAIRRO, CIDADE, UF". Se faltar campo, cidade e UF deslocam. Usar a UF da chave como âncora e validar a cidade (ex.: bater com os valores já vistos daquela UF) antes de aceitar.

## S16 — Fixtures reais (contínuo)

- Coletar capturas reais e sanitizadas (sem CPF, sem chave real) de cada UF atendida e adicioná-las a `tests/fixtures/` com um teste por UF. É o que valida o parser de verdade e alimenta a lista de UFs suportadas (S4).

---

## Ordem sugerida de trabalho

1. **S1 + S3** (LGPD e dado corrompido) — pequenos, sem dependência.
2. **S2** — maior ganho de qualidade dos dados.
3. **S5 + S6** — pequenos e melhoram a experiência.
4. **S4** — dá a medição que orienta S7, S13 e S16.
5. **S7 (etapa 1), S9, S12, S13** — ajustes rápidos.
6. **S10, S11** — robustez do parser.
7. **S8** — transversal; isolar e testar bem.
8. **S14, S15, S16** — conforme a cobertura por UF mostrar necessidade.

## Perguntas em aberto

- Quais UFs você atende hoje? (define a prioridade de S4/S16)
- O DANFE real que você usa traz CPF do consumidor? (confirma a gravidade de S1; a limpeza vale de qualquer forma)
- Há certificado A1 em produção (`NFE_CERTIFICADO_PATH`)? Se não, S12 se resume a documentar.
- Importação assíncrona (S7, etapa 3) é desejada ou o tempo de espera atual é aceitável?

## Fora deste plano

- Importação por XML sem verificação de assinatura (decidido deixar de lado na análise de segurança; o escape no front cobre a exibição).
