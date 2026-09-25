# CestaZen — Funcionalidades por página

Documento gerado a partir da leitura de `routes/web.php`, `routes/api.php`, das views Blade (`resources/views`), dos módulos JS (`resources/js/pages`) e de `config/plans.php`. Descreve o que o **site (web)** oferece em cada tela; ao final há um resumo do que a **API v1** (usada pelo app mobile) espelha.

**Legenda de plano:** 🆓 Grátis · ⭐ Pro (bloqueado no Grátis com mensagem de paywall e destaque na página de planos).

---

## Índice

1. [Estrutura geral (layout)](#1-estrutura-geral-layout)
2. [Páginas públicas e de autenticação](#2-páginas-públicas-e-de-autenticação)
3. [Dashboard](#3-dashboard)
4. [Compras](#4-compras) — Emissores, Minhas Compras, Importar NFC-e, Detalhe da nota, Lista de Compras
5. [Planejamento](#5-planejamento) — Categorias, Orçamento, Relatórios
6. [Preços](#6-preços) — Preços, Compras Recorrentes, Revisar nomes de produto
7. [Conta e assinatura](#7-conta-e-assinatura)
8. [Administração](#8-administração)
9. [Funcionalidades transversais](#9-funcionalidades-transversais)
10. [Tarefas agendadas (background)](#10-tarefas-agendadas-background)
11. [API v1 (mobile)](#11-api-v1-mobile)
12. [Planos Grátis × Pro](#12-planos-grátis--pro)

---

## 1. Estrutura geral (layout)

Arquivos: `layout/main`, `layout/sidebar`, `layout/main-login`, `layout/main-public`.

**Sidebar (menu lateral, recolhível)**

| Grupo | Item | Rota |
|---|---|---|
| — | Dashboard | `dashboard.index` |
| Compras | Emissores | `issuers.index` |
| Compras | Minhas Compras | `my-purchases.index` |
| Compras | Lista de Compras | `shopping-list.index` |
| Planejamento | Categorias | `categories.index` |
| Planejamento | Orçamento | `budgets.index` |
| Planejamento | Relatórios | `reports.index` |
| Preços | Preços | `prices.index` |
| Preços | Compras Recorrentes | `recurring-purchases.index` |
| Administração (só super admin) | Assinaturas | `admin.subscriptions.index` |

- Usuário Grátis vê um cartão "Grátis — Desbloqueie recursos exclusivos com o plano Pro" com botão **Fazer upgrade**.

**Topbar (cabeçalho)**

- **Sino de notificações** com badge de não lidas (`9+` acima de 9), lista ao abrir, "Marcar todas como lidas", marcar individual como lida, link para a tela relacionada (ver [seção 9](#9-funcionalidades-transversais)).
- **Menu do usuário**: avatar (ou iniciais), nome, selo **Pro/Grátis**, e-mail (link p/ Minha Conta), "Meu perfil", alternância **Modo Escuro**, **Sair**.

**Outros**

- Tema claro/escuro persistido em `localStorage` (padrão claro).
- PWA: `manifest.webmanifest` (standalone, portrait, tema `#4B7672`), ícones e `pwa.js`.
- Meta tags `noindex` nas telas autenticadas; Open Graph/Twitter Card configurados.
- Layouts públicos: `main-login` (auth, painel lateral com mensagem de marca) e `main-public` (páginas legais).

---

## 2. Páginas públicas e de autenticação

### 2.1 Login — `/login`
- Login por **e-mail + senha**, com "Lembrar de mim".
- **Entrar com Google** (Socialite; callback em `login/social/{provider}/callback`).
- Links: "Esqueceu a senha?" e "Cadastre-se".
- Throttle: 5/min no POST; 10/min nas rotas sociais.

### 2.2 Cadastro — `/register`
- Campos: nome, e-mail, cidade (opcional), estado (UF), senha e confirmação.
- **Aceite obrigatório** de Termos de Uso e Política de Privacidade (versão registrada — LGPD).
- Cadastro/login via Google.
- Ao criar a conta: categorias padrão (`CreateDefaultCategoriesAction`) e assinatura Grátis (`CreateFreeSubscriptionAction`).

### 2.3 Esqueci a senha — `/forgot-password`
- Informa o e-mail e recebe um link de redefinição (throttle 5/min).

### 2.4 Redefinir senha — `/reset-password`
- Formulário com nova senha + confirmação (e-mail vem preenchido e somente leitura).
- `/reset-password/app`: página intermediária que tenta **abrir o app mobile** (deep link) e oferece "redefinir pelo site" como alternativa.

### 2.5 Confirmação de e-mail — `/email/verify`
- Aviso "Confirme seu e-mail", **reenviar e-mail de confirmação** (throttle 6/min) e **Sair**.
- O link assinado `email/verify/{id}/{hash}` valida a conta. Todo o app autenticado exige e-mail verificado (`verified`).

### 2.5.1 Aceite de termos atualizados — `/aceitar-termos`
- Exibida quando a versão dos Termos mudou; o usuário precisa aceitar (ou sair) para continuar (`terms.accepted`).

### 2.6 Páginas legais (públicas)
- **Termos de Uso** — `/termos-de-uso`
- **Política de Privacidade** — `/politica-de-privacidade`
- **Exclusão de conta** — `/excluir-conta`: passo a passo (app e site), exclusão sem acesso à conta (e-mail, prazo de 15 dias), exclusão parcial de dados, o que é excluído e o que é mantido de forma anonimizada. E-mail de contato e versão vêm de `config/legal`.

---

## 3. Dashboard — `/dashboard`

Visão geral dos gastos. Todos os números seguem o **filtro de período**.

- **Cabeçalho**: data por extenso e botão **Importar NF-e**.
- **Filtro de período** (componente compartilhado): Este Mês · Mês Passado · Este Ano · Tudo · Personalizado (datas início/fim + Aplicar).
- **Barra de localização**: mostra "Sua localização: Cidade/UF" ou convida a compartilhar a localização (botão **Usar minha localização**, via geolocalização do navegador) para ver produtos próximos e alertas de queda de preço.
- **4 cartões**: Total Gasto · Impostos · NF-e importadas · Ticket Médio.
- **Período selecionado**: total do período, **variação %** (▲/▼/Estável) e comparação com o **período anterior** (datas e valor).
- **Última compra**: emissor, data, valor e link "Ver detalhes".
- **Evolução de gastos**: gráfico (ApexCharts) dos últimos 12 meses; estado vazio com atalho para importar.
- **Formas de pagamento**: gráfico + top 5 com % e valor.
- **Gastos por categoria**: gráfico + top 5 com % e valor; atalho para gerenciar categorias.
- **Orçamento do mês** (se houver orçamentos): até 6, ordenados pelo maior % usado, com status *OK / Atenção (≥75%) / Estourado (≥100%)*, gasto/limite, restante e barra de progresso; link "Gerenciar".
- **Onde você mais gasta**: ranking de emissores (nº de compras e total) → "Ver todos".
- **Produtos mais comprados**: ranking com preço médio e frequência → "Ver histórico" (Preços).

---

## 4. Compras

### 4.1 Emissores — `/issuers`
Lista das lojas (emissores) em que o usuário tem ao menos uma nota autorizada.

- **Resumo**: nº de favoritos · total gasto · ticket médio · emissor mais visitado (com nº de compras).
- **Busca** por nome, apelido ou CNPJ; filtro por **cidade** (aparece se houver mais de uma); filtro **Só favoritos**; botão Limpar.
- **Ordenação**: Favoritos e nome · Maior gasto · Mais compras · Compra mais recente.
- **Tabela** (e cartões no mobile): estrela de **favoritar**, avatar com iniciais, nome/apelido (nome oficial no tooltip), bairro, CNPJ formatado, cidade/UF, nº de compras, total gasto + média, última compra.
- **Ações**: editar **apelido** (modal, por usuário, até 100 caracteres; em branco volta ao nome oficial) e **ver detalhes**.
- Paginação com "Exibindo X–Y de Z".
- Estados vazios distintos: sem emissores (atalho para importar) × filtros sem resultado.

### 4.2 Detalhe do emissor — `/issuers/detail/{id}`
- Breadcrumb, botão **Favoritar/Favoritado**, editar apelido, voltar.
- **Identidade**: avatar, apelido/nome oficial, CNPJ, cidade/UF.
- **Estatísticas**: total de notas, valor total, **ticket médio** (com tendência ▲/▼ das compras recentes vs. anteriores), **frequência** ("a cada ~N dias"), primeira e última compra.
- **Endereço** completo (logradouro, bairro, cidade/UF, CEP) + **mapa embutido** (Google Maps) e botão **Como chegar**.
- **Gasto por mês** (gráfico, 12 meses).
- **Produtos que você mais compra aqui**: nº de compras, preço médio, último preço, variação % e link **Comparar preços** (⭐ quando o usuário é Pro).
- **Gasto por categoria** no emissor (valor + %).
- **Notas fiscais do emissor**: busca por nº da nota, tabela (número/série, data, valor, itens) com link para a nota; paginação.

### 4.3 Minhas Compras — `/my-purchases`
Lista de notas importadas (inclui pendentes/não confirmadas, mas os **totais só contam as autorizadas**).

- Cabeçalho: nº de notas + aviso "N aguardando confirmação".
- **Filtro de período** (compartilhado).
- **4 indicadores**: Total gasto (com ▲/▼ vs. período anterior de mesma duração) · Gasto médio diário · Ticket médio · NFC-e importadas.
- **Busca** por emissor, apelido, CNPJ ou nº da nota; filtro por **emissor**; filtro por **situação** (`InvoiceStatus`: autorizada, pendente, expirada); limpar filtros.
- **Ordenação**: Mais recentes · Mais antigas · Maior valor · Menor valor.
- Tabela/cartões: emissor (com selo de situação e dica quando não autorizada), nº/série, data e hora, nº de itens, valor, link para detalhes.
- Paginação; estados vazios (sem notas × filtros sem resultado).

### 4.4 Importar NFC-e — `/my-purchases/upload`
- **Ler QR Code com a câmera** (modal com scanner ZXing): escolhe automaticamente a câmera traseira principal, foco macro/contínuo quando suportado, slider de foco manual, resolução elevada, **tirar foto para focar melhor**, **alternar câmera**, recorte da área do QR para melhor decodificação e dica de afastar o cupom após alguns segundos sem leitura.
- **Colar a URL do QR Code** (`qrcode_url`) e importar; os dados são buscados no portal da SEFAZ.
- Envio via `fetch`: erros (chave inválida, nota duplicada, SEFAZ fora do ar) aparecem no próprio formulário; sucesso redireciona.
- Após importar dispara `InvoiceImported` → **auto-categorização** dos itens, **alerta de orçamento** (80% e 100%) e log de leitura do QR (`LogQrCodeReadAction`).
- Duplicidade checada por `user_id + access_key`.
- Observação: no site a tela expõe apenas o QR Code (câmera/URL). Existem também rotas web/API para **XML** (`my-purchases.upload`, `POST /api/nfce/upload`, `invoices/import/xml`) e **chave de acesso** (`my-purchases.import-by-key`, `invoices/import/key`), sem campo na tela atual.

### 4.5 Detalhe da nota — `/my-purchases/detail/{invoice}`
- Título "NFC-e Nº X / Série Y", selo **Produção/Homologação**, selo de situação, data/hora, botões Importar outra e Voltar; caixa explicativa quando a nota não está autorizada.
- **Totais**: total da nota, valor dos produtos, tributos aproximados, nº de itens; detalhamento fiscal (base ICMS, ICMS, tributos).
- **Emissor**: nome/apelido, nome oficial, CNPJ, endereço, mapa embutido, links **Ver emissor**, **editar apelido** e **favoritar**.
- **Chave de acesso** formatada (blocos de 4).
- **Pagamentos**: forma (dinheiro, crédito, débito, Pix, vale-alimentação/refeição/presente/combustível, boleto, cheque, crédito loja, outros…) e valor, com total pago.
- **Itens** (tabela e cartões): nº, descrição (nome canônico + descrição original), código/NCM/CFOP, **categoria** (seletor inline que grava na hora e "aprende" para compras futuras), quantidade, unidade, valor unitário, total, **% do total da nota**.
  - **Editar nome do produto** (alias/nome canônico, com sugestão por IA ⭐).
  - **Favoritar produto** (coração).
  - ⭐ **Sugerir categoria por IA** (botão por item).
- **Excluir nota** (modal de confirmação): remove nota, itens e pagamentos do histórico e dos totais; irreversível (é preciso reimportar).

### 4.6 Lista de Compras — `/shopping-list`
Lista com **preços reais** vindos das suas próprias notas e das da comunidade.

- **Várias listas salvas** (cartão lateral): nome, nº de itens, total estimado, data e barra de progresso; abrir, **nova lista**, **excluir** (modal).
- **Nome da lista** editável; itens **salvos automaticamente**.
- **Buscar produto** (mín. 2 caracteres): para cada produto/mercado mostra só a **compra mais recente** (preço atual), do menor ao maior; preços com mais de 90 dias aparecem depois com selo **"antigo"**; opção de adicionar **item genérico** (lembrete sem preço). Item repetido (mesmo produto e mercado, ainda pendente) soma a quantidade.
- **Localização**: usa a cidade do perfil (raio de ~15 km), **Usar minha localização**, **Comparar outra cidade** (modal) e "Voltar pra minha cidade".
- **A Comprar / Comprados**: contadores, marcar item como comprado, alterar quantidade, remover; item mostra mercado, preço unitário e subtotal.
- **Total estimado** e **progresso** ("X de Y itens comprados", %).
- **Ações da lista**: **atualizar preços** (throttle 30/min), **mostrar economia** (sugere trocar itens de mercado; throttle 20/min — "Você pode economizar R$ X comprando N itens em outro mercado"), **compartilhar** (Web Share API ou copia texto agrupado por mercado com quantidades, preços e total), **duplicar** (todos voltam para "A comprar"), **marcar tudo como comprado**.
- **Como chegar** (modal com mapa/links) para o mercado de um item.

---

## 5. Planejamento

### 5.1 Categorias — `/categories`
- **Filtro de período**; indicadores: nº de categorias · total categorizado · maior gasto · **sem categoria** (valor e nº de itens, com link "Revisar").
- **Cartões por categoria**: cor, nome (link para o detalhe), nº de itens, total gasto, **variação vs. período anterior**, % do total, **orçamento do mês** (gasto/limite) quando existe, palavras-chave (até 8 + contador) e selo **Sistema** para categorias padrão.
- **Ações** (só nas categorias do usuário): **editar** (nome, cor, palavras-chave), **mesclar** em outra (move itens, regras aprendidas e palavras-chave; orçamento vai junto se o destino não tiver), **excluir** (remove regras aprendidas e o orçamento; sugere mesclar para manter histórico).
- **Nova categoria**: nome, cor e palavras-chave (separadas por vírgula) com **prévia** de quantos itens seriam atingidos; ⭐ **sugerir palavras-chave por IA**.
- ⭐ **Auto-categorizar** itens sem categoria (palavras-chave, regras aprendidas e IA) e **desfazer categorização automática** (só reverte o que foi automático; escolhas manuais permanecem).

### 5.2 Detalhe da categoria — `/categories/{category}`
- Breadcrumb, filtro de período, atalho **para o Relatório filtrado** por essa categoria.
- Total gasto (com ▲/▼), nº de itens comprados, **orçamento do mês** (ou "Definir orçamento").
- **Gasto por mês** (12 meses), **Produtos que mais pesam**, **Onde você gasta** (emissores com link) e **palavras-chave**.

### 5.3 Itens sem categoria — `/categories/uncategorized`
- Resumo (itens e valor) e lista paginada com descrição, emissor, data, quantidade × preço, link **Ver nota**.
- **Seletor de categoria por item** (o sistema aprende e aplica em compras futuras) e ⭐ **sugestão por IA** por item.

### 5.4 Orçamento — `/budgets`
- **Navegação de meses** (anterior/próximo/"Mês atual"); só o mês atual é editável (os limites valem para todos os meses).
- **Resumo**: total orçado (Geral ou por categoria — com orçamento Geral, os totais usam só ele), total gasto, restante, nº de orçamentos estourados.
- **Definir orçamento**: categoria (ou **Geral**) + limite mensal (R$ 0,01–99.999.999,99); editar/excluir (modal).
  - 🆓 Orçamento **Geral**. ⭐ Orçamentos **por categoria** (categorias aparecem com "(Pro)" e desabilitadas no Grátis).
- **Cartão por orçamento**: gasto × limite, %, barra colorida (verde <75%, amarelo <100%, vermelho ≥100%), restante, **projeção do mês** e data estimada de estouro, **disponível por dia** até o fim do mês, **vs. mês anterior**, mensagens contextuais ("estoura por volta de dd/mm", "mês fechado em R$…").
- **Alertas**: notificação ao atingir **80%** e **100%** (uma vez por patamar e mês) após cada importação.

### 5.5 Relatórios — `/reports`
- **Filtros**: período, busca de produto, emissor, categoria (inclui "Sem categoria"), ordenação dos itens (Mais recentes/antigos, Maior/Menor valor, Produto A–Z).
- **Resumo**: total gasto (▲/▼ vs. anterior), itens, notas, ticket médio. Sem filtro de categoria/produto o total é o das notas (líquido de desconto); com filtro é a soma dos itens (`is_partial`, rotulado "Total gasto (filtro)").
- **Gráficos**: gastos por categoria, **evolução mensal** (12 meses), **onde você gasta** (por emissor, com link).
- **Itens detalhados** paginados: data, emissor, produto (editar nome, favoritar), categoria (seletor inline, ⭐ IA), qtd, preço unitário, total. Aviso para atualizar totais após mudar categoria.
- ⭐ **Exportar PDF** (até `PDF_MAX_ITEMS` itens), ⭐ **Exportar CSV** (lido em blocos; neutraliza fórmulas de planilha), ⭐ **Enviar por e-mail** (PDF ou CSV, com os filtros da tela). Usuário Grátis vê os botões apontando para a página de planos.
- ⭐ **Envio recorrente**: semanal ou mensal, PDF/CSV, relatório de todos os gastos (sem filtros) do período anterior às 6h; mostra o agendamento atual e a data do próximo envio; **salvar** ou **cancelar**.

---

## 6. Preços

### 6.1 Preços — `/prices` ⭐ (`pro:price_comparison`)
Rotas antigas `/price-history` e `/price-comparison` redirecionam (301) para cá.

- **Buscar produto** (mín. 2 caracteres, resultado em lista), **Trocar produto**, **favoritar produto** (coração) e **editar nome do produto** (alias); pode abrir direto no produto vindo de outra tela (ex.: detalhe do emissor).
- **Unidade**: produtos vendidos em unidades diferentes (KG, UN) não se comparam — o usuário escolhe a unidade (a mais comum já vem marcada).
- **Meu Histórico**
  - Cartões: **último preço** (variação vs. compra anterior), **menor**, **maior**, **médio** (com mediana); variação **30/90 dias** e **amplitude** do histórico.
  - **Gráfico de evolução** com alternância **Por compra / Por mês** (padrão mensal quando há muito histórico), linha da mediana e marcação de menor/maior preço.
  - **Tabela**: data, emitente, preço unitário, quantidade, unidade (mais recentes primeiro).
- **Comparativo por Cidade**: gráfico de barras (preço atual; antigos em cinza) e tabela — cidade/UF, preço atual, mais barato em, menor histórico, amostras.
- **Comparativo por Mercado** (ao clicar numa cidade): mercado, preço atual, **distância**, preço médio, amostras; selo **"antigo"** (> 90 dias), "Voltar para cidades".
- **Adicionar à lista de compras** direto de uma linha do comparativo (modal para escolher a lista).
- Regras: o comparativo usa o **preço atual** (compra mais recente de cada produto em cada mercado), atuais primeiro do menor ao maior; preço zero não conta; o produto é resolvido por alias (`ProductAliasService`).

### 6.2 Compras Recorrentes — `/recurring-purchases` ⭐ (`pro:recurring_purchases`)
Detecta produtos que você compra com frequência (≥ 3 dias distintos nos últimos 24 meses, só notas autorizadas, preço > 0).

- **Resumo**: produtos recorrentes · na hora ou atrasados · gasto mensal estimado · economia potencial/mês.
- **Filtros**: busca; situação (Ativos, Atrasados, Na hora, Chegando, Em dia, Parados, Todos); ordenação (Mais urgentes, Mais frequentes, Maior gasto, Maior economia, Nome A–Z).
- **Por produto**: selo de situação e prazo ("próxima em ~N dias", "atrasado N dias"), intervalo típico (mediana), compras/mês, nº de compras, última compra, faixa **mínimo–média–máximo** de preço, **melhor mercado atual** (com aviso quando o preço tem mais de 90 dias) e **economia estimada/mês**.
- **Ações**: **adicionar à lista** (existente ou "Nova lista", com quantidade sugerida), **ocultar** produto e **voltar para a lista** (restaurar); tela de **Ocultados**.
- **Gerar lista de reposição** com tudo que está na hora/atrasado.
- Parado = sem comprar por mais de 3 intervalos (30–180 dias).

### 6.3 Revisar nomes de produto — `/product-aliases/review`
(Sem item no menu; acessível pelas telas de produto.)
- Varre todos os produtos importados e sugere quais parecem o **mesmo item em lojas diferentes**.
- **Sugestões da comunidade**: nomes que outros usuários já deram à mesma descrição (gratuito e instantâneo).
- **Sugestões de unificação**: ajustar o nome e unificar (`merge`) ou **ignorar** (`dismiss`); nada é unificado automaticamente.
- ⭐ **Sugerir nome por IA**. O nome canônico passa a ser exibido e usado para agrupar histórico e comparativos.

---

## 7. Conta e assinatura

### 7.1 Minha Conta — `/account`
Abas: **Visão Geral · Configurações · Segurança** (abre a aba certa após erro de formulário).

**Visão Geral**
- Cabeçalho com avatar, nome, selo de plano, e-mail e "Membro desde".
- **Dados pessoais** (nome, e-mail, CPF mascarado, cidade/UF), **estatísticas** (notas, itens comprados, total gasto).
- **Sugestão de localização**: "você compra frequentemente em Cidade/UF — atualizar?" (aceitar ou dispensar).
- **Meu plano**: Grátis/Pro, validade da assinatura, recursos Pro e botão de upgrade.
- **Privacidade**: versão/data dos termos aceitos, links para Termos e Política, atalho para exportar/excluir dados.

**Configurações**
- **Foto de perfil**: JPG/PNG/WEBP até 2 MB (regravada sem EXIF quando há GD).
- **Informações pessoais**: nome, e-mail (trocar exige a senha atual, zera a verificação e reenvia o e-mail), cidade e UF (enviados vazios apagam a localização), **usar minha localização** (geolocalização).

**Segurança**
- **Meus dados (LGPD)**: **exportar** (link assinado enviado por e-mail; throttle 5/hora).
- **Alterar/Definir senha** (contas sociais definem sem senha atual); ao trocar, desconecta os demais dispositivos.
- **Dispositivos conectados**: encerrar todas as outras sessões (navegadores, app e tokens Sanctum).
- **Zona de perigo — Excluir conta**: exige senha atual (dispensada em conta social); remove perfil, foto, categorias, orçamentos, listas, favoritos, notificações, assinatura, tokens e exportações; notas importadas ficam **anonimizadas** no histórico agregado de preços.

### 7.2 Planos — `/subscription/upgrade`
- Usuário Grátis: "Desbloqueie o plano Pro" (mostra a mensagem do paywall que o trouxe até aqui e **destaca a linha** do recurso bloqueado), tabela **Grátis × Pro** por recurso (`config/plans.php`) e botão **Assinar Pro (em breve)** desabilitado (pagamento online ainda não disponível).
- Usuário Pro: "Você já é Pro" (com data de início) e link de volta à conta.

---

## 8. Administração

### 8.1 Assinaturas — `/admin/subscriptions` (super admin)
Acesso restrito ao e-mail em `config('subscription.super_admin_email')` (middleware `super-admin`).
- Lista paginada de usuários com **busca** por nome/e-mail, plano atual e botão **Tornar Pro / Voltar para Grátis** (promoção manual).

---

## 9. Funcionalidades transversais

- **Notificações** (sino; sem página própria): `NotificationPresenter` classifica em `price_drop`, `budget_warning`, `budget_exceeded`, `generic` com nível, mensagem em texto puro e link. Contador leve a cada página, lista só ao abrir o sino, marcar uma/todas como lidas; apagadas após 90 dias.
- **Alerta de queda de preço** de produtos favoritos (considera só ofertas atuais).
- **Favoritos**: emissores (estrela) e produtos (coração) em várias telas.
- **Apelido de emissor** por usuário (o nome oficial fica fixo desde o 1º import).
- **Nome canônico de produto** (alias) com sugestão comunitária e por IA.
- **Auto-categorização**: palavras-chave, regras aprendidas com as escolhas do usuário e IA ⭐.
- **Filtro de período** e **barra de localização** reutilizáveis.
- **Busca global** (`GET /search`, JSON; estratégias de nota, emissor e produto) — sem tela própria no site; usada pela API.
- **Geolocalização** (`location/capture`) + geocodificação de emissores.
- **LGPD**: consentimento versionado, criptografia/hash de documentos, minimização, exportação e exclusão de dados.
- **Segurança**: throttles nas rotas sensíveis, autorização por Policies (404 para dado de outro usuário), sessões revogáveis.

---

## 10. Tarefas agendadas (background)

| Comando | Frequência | O que faz |
|---|---|---|
| `prices:check-favorite-drops` | diária | Notifica queda de preço de produtos favoritos |
| `invoices:reconcile-pending` | a cada 30 min | Reconcilia notas pendentes com a SEFAZ |
| `reports:send-scheduled` | diária, 06:00 | Envia relatórios recorrentes (Pro) |
| `notifications:prune` | diária | Apaga notificações com mais de 90 dias |
| `sanctum:prune-expired` | diária | Remove tokens expirados |
| `model:prune` | diária | Poda de modelos prunáveis |

Comandos manuais: `EncryptUserProfileDocuments`, `GeocodeExistingIssuers`, `RedactInvoicesRawXml`.

---

## 11. API v1 (mobile)

Prefixo `/api/v1`, Sanctum, `throttle:api` 60/min por usuário (rotas de auth: `throttle:api-auth` 10/min por IP). Espelha os módulos do site:

- **Auth**: login, registro, esqueci/redefinir senha, login social, `me`, logout, reenviar verificação, aceitar termos.
- **Dashboard**, **busca global**.
- **Invoices**: listar, detalhar, excluir e importar por **XML**, **QR Code** e **chave de acesso**.
- **Issuers**: listar, detalhar, favoritar, apelido.
- **Categories** (CRUD) + assign-item, auto-categorize, revert, preview-keywords, uncategorized, merge, ⭐ suggest-item-category / suggest-keywords.
- **Budgets**, **Reports** (JSON, ⭐ CSV, ⭐ e-mail, agendamento).
- ⭐ **Price history** e ⭐ **price comparison** (search-products, units, by-city, by-issuer).
- **Product aliases** (sugestões, comunidade, store, merge, dismiss, ⭐ ai-suggest-name).
- ⭐ **Recurring purchases**.
- **Shopping lists** (CRUD, itens, duplicate, purchase-all, refresh-prices, savings, search, cities).
- **Subscription plans**, **Account** (perfil, senha, sessões, avatar, localização, exportar, excluir), **Favorite products**, **Notifications**.

Docs automáticas (Scramble) em `local`/`staging`.

---

## 12. Planos Grátis × Pro

Fonte única: `config/plans.php`.

| Recurso | Grátis | Pro |
|---|:-:|:-:|
| Orçamento geral (sem categoria) | ✅ | ✅ |
| Múltiplos orçamentos por categoria | ❌ | ✅ |
| Exportação de relatórios em CSV | ❌ | ✅ |
| Exportação de relatórios em PDF | ❌ | ✅ |
| Envio de relatórios por e-mail e agendamento recorrente | ❌ | ✅ |
| Sugestões via IA (categorias e nomes de produto) | ❌ | ✅ |
| Categorização de itens por IA (automática e sob demanda) | ❌ | ✅ |
| Histórico de preços por produto | ❌ | ✅ |
| Comparação de preços entre lojas e cidades | ❌ | ✅ |
| Detecção de compras recorrentes | ❌ | ✅ |

Rotas Pro usam o middleware `pro:<recurso>`; o bloqueio devolve a mensagem do recurso, destaca a linha em `subscription/upgrade` e, na API, responde **402** com `feature`.
