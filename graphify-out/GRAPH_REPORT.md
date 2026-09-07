# Graph Report - my-expenses  (2026-09-07)

## Corpus Check
- 288 files · ~64,068 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1254 nodes · 2913 edges · 123 communities (38 shown, 27 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 42 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Gestão de Emitentes (Issuer): Favoritar e Apelido
- Sugestão de Keywords de Categoria via IA
- Estratégias de Importação de NFC-e (QR/XML/Chave)
- Módulos JS de Páginas do Frontend
- Captura e Geocodificação de Localização do Usuário
- Índices de API: Budget, Dashboard e Invoice
- CRUD de Categorias (Web + API)
- Produto Favorito e Alerta de Queda de Preço
- Conta do Usuário (Web + API)
- Validação de Login, Registro e Importação
- Notificações, Reset de Senha e Comparação de Preço (API)
- Admin de Assinatura e Budget/Dashboard (Web)
- Upload de Avatar e Model File
- Serviços de Alias de Produto e Comparação de Preço
- Factories de Teste: Budget/Category/Invoice/Issuer
- Reset de Senha, Preços e Busca (Web)
- Login, Upload de Compra Legado e Auth Social
- Serviços de Budget e Histórico de Preço
- Serviço de Estatísticas do Dashboard
- Model User, Policies e Config de Auth
- Requests de Sugestão via IA (Categoria/Produto)
- Controller de Alias de Produto (API)
- Estratégias de Busca Global (Search)
- Controller de Relatórios (Export PDF/CSV)
- Importação e Persistência de Invoice
- Sugestão de Nome de Produto via IA
- Requests de Itens da Lista de Compras
- Controller e Model de Lista de Compras
- Enums e Factories de Plano de Assinatura
- Middleware Pro Plan e Exceções
- Registro e Auth Controller (API)
- Controller de Compras Recorrentes
- Relacionamentos do Model User
- Observer de User e Seeders do Banco
- Controller de Lista de Compras (API, CRUD completo)
- Serviço de Sugestão/Unificação de Alias de Produto
- Migrations: Users, Sessions e Files
- Migrations: Cache, Índices de Invoice e Coordenadas de Issuer
- Migrations: Issuers, Shopping Lists e Favorite Products
- Criação de Usuário Social e Categorias Padrão
- Filtro de Localização da Lista de Compras e Cálculo de Distância
- Controller de Alias de Produto (Web)
- Controller Base de API e Trait de Resposta
- Value Object AccessKey (Chave de Acesso NFC-e)
- Configuração de Logging (Monolog)
- Middleware de Sanctum e CSRF
- Estados da Factory de User
- Auth Social Controller (tratamento de 404)
- Migration da Tabela Subscriptions
- Console Commands e Scheduler
- Segurança da Documentação Scramble
- Layout Principal (Sidebar/Footer)
- View do Dashboard e Filtro de Período
- View de Detalhe de Compra e Modais
- View de Lista de Compras e Modais
- Upload de Compra e Modal de Scanner QR
- View de Conta e Partial de UF
- View de Categoria e Filtro de Período
- View de Detalhe de Emitente e Modal de Apelido
- View de Índice de Emitentes e Modal de Apelido
- View de Índice de Compras e Filtro de Período
- View de Preços e Modal de Alias
- View de Revisão de Alias de Produto
- View de Registro e Partial de UF
- View de Relatórios e Modal de Alias

## God Nodes (most connected - your core abstractions)
1. `User` - 76 edges
2. `InvoiceItem` - 46 edges
3. `Invoice` - 39 edges
4. `Issuer` - 39 edges
5. `ProductAliasService` - 38 edges
6. `ShoppingList` - 36 edges
7. `NFCeService` - 36 edges
8. `Category` - 32 edges
9. `Controller` - 27 edges
10. `NfceXmlImporter` - 25 edges

## Surprising Connections (you probably didn't know these)
- `InvoiceController` --references--> `ImportInvoiceAction`  [EXTRACTED]
  app/Http/Controllers/Api/V1/InvoiceController.php → app/Actions/ImportInvoiceAction.php
- `MyPurchaseController` --references--> `ImportInvoiceAction`  [EXTRACTED]
  app/Http/Controllers/MyPurchaseController.php → app/Actions/ImportInvoiceAction.php
- `MyPurchaseController` --references--> `LogQrCodeReadAction`  [EXTRACTED]
  app/Http/Controllers/MyPurchaseController.php → app/Actions/LogQrCodeReadAction.php
- `AccountController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/AccountController.php → app/Http/Controllers/Controller.php
- `Controller` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Api/Controller.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (123 total, 27 thin omitted)

### Community 0 - "Gestão de Emitentes (Issuer): Favoritar e Apelido"
Cohesion: 0.06
Nodes (16): IssuerController, IssuerController, UpdateIssuerNicknameRequest, Budget, Issuer, IssuerNickname, ProductAliasSuggestionDismissal, QrCodeRead (+8 more)

### Community 1 - "Sugestão de Keywords de Categoria via IA"
Cohesion: 0.06
Nodes (16): CategoryKeywordsAiSuggestion, self, CategoryKeywordsAiSuggestionService, NFCeService, ProductNameNormalizer, CookieJar, DOMElement, DOMXPath (+8 more)

### Community 2 - "Estratégias de Importação de NFC-e (QR/XML/Chave)"
Cohesion: 0.07
Nodes (12): LogQrCodeReadAction, ImportStrategyInterface, ImportPayload, InvoiceController, NfceImportController, ImportByQrCodeRequest, AccessKeyImportStrategy, QrCodeImportStrategy (+4 more)

### Community 3 - "Módulos JS de Páginas do Frontend"
Cohesion: 0.07
Nodes (35): pages, Account, TAB_TOGGLE_SELECTORS, Budget, Category, Dashboard, InvoiceDetail, IssuerDetail (+27 more)

### Community 4 - "Captura e Geocodificação de Localização do Usuário"
Cohesion: 0.06
Nodes (19): CaptureUserLocationFromBrowserAction, GeocodeExistingIssuers, CaptureLocationRequest, GeocodeIssuerJob, DateTimeInterface, GeocodeUserProfileJob, DateTimeInterface, FavoriteProductPriceDropped (+11 more)

### Community 5 - "Índices de API: Budget, Dashboard e Invoice"
Cohesion: 0.07
Nodes (11): BudgetController, DashboardController, StoreBudgetRequest, BudgetResource, CategoryResource, InvoiceItemResource, InvoicePaymentResource, InvoiceResource (+3 more)

### Community 6 - "CRUD de Categorias (Web + API)"
Cohesion: 0.10
Nodes (7): CategoryController, CategoryController, AssignCategoryItemRequest, SaveCategoryRequest, AutoCategorizeListener, Category, CategoryService

### Community 7 - "Produto Favorito e Alerta de Queda de Preço"
Cohesion: 0.10
Nodes (7): CheckFavoriteProductPriceDrops, FavoriteProductController, FavoriteProductController, StoreFavoriteProductRequest, FavoriteProduct, FavoriteProductPolicy, PriceComparisonService

### Community 8 - "Conta do Usuário (Web + API)"
Cohesion: 0.09
Nodes (7): AccountController, AccountController, UpdateAccountRequest, UpdateAvatarRequest, UpdatePasswordRequest, UserResource, LocationSuggestionService

### Community 9 - "Validação de Login, Registro e Importação"
Cohesion: 0.07
Nodes (8): LoginRequest, ImportByAccessKeyRequest, RegisterRequest, UpdateSubscriptionRequest, UploadXmlRequest, Illuminate\Foundation\Http\FormRequest, Illuminate\Validation\Rule, Illuminate\Validation\Rules\Password

### Community 10 - "Notificações, Reset de Senha e Comparação de Preço (API)"
Cohesion: 0.10
Nodes (10): Controller, NotificationController, PasswordResetController, PriceComparisonController, PriceHistoryController, SearchController, SocialAuthController, NotificationController (+2 more)

### Community 11 - "Admin de Assinatura e Budget/Dashboard (Web)"
Cohesion: 0.11
Nodes (8): SubscriptionController, BudgetController, Controller, DashboardController, ForgotPasswordController, SubscriptionController, Illuminate\Foundation\Auth\Access\AuthorizesRequests, Illuminate\View\View

### Community 12 - "Upload de Avatar e Model File"
Cohesion: 0.10
Nodes (14): UpdateUserAvatarAction, File, AppServiceProvider, Dedoc\Scramble\Scramble, Illuminate\Auth\Notifications\ResetPassword, Illuminate\Cache\RateLimiting\Limit, Illuminate\Database\Eloquent\Relations\MorphTo, Illuminate\Http\UploadedFile (+6 more)

### Community 13 - "Serviços de Alias de Produto e Comparação de Preço"
Cohesion: 0.11
Nodes (6): ProductAlias, Closure, ProductAliasService, FullTextQuery, Illuminate\Database\Eloquent\Builder, Illuminate\Database\Query\Builder

### Community 14 - "Factories de Teste: Budget/Category/Invoice/Issuer"
Cohesion: 0.10
Nodes (10): BudgetFactory, CategoryFactory, InvoiceItemFactory, IssuerFactory, IssuerNicknameFactory, QrCodeReadFactory, ShoppingListFactory, static (+2 more)

### Community 15 - "Reset de Senha, Preços e Busca (Web)"
Cohesion: 0.12
Nodes (9): PricesController, ResetPasswordController, SearchController, Authenticate, SubscriptionResource, Illuminate\Auth\Middleware\Authenticate, Illuminate\Http\Request, Illuminate\Support\Facades\Auth (+1 more)

### Community 16 - "Login, Upload de Compra Legado e Auth Social"
Cohesion: 0.19
Nodes (6): LoginController, MyPurchaseController, SocialAuthController, VerificationController, Illuminate\Foundation\Auth\EmailVerificationRequest, Illuminate\Http\RedirectResponse

### Community 17 - "Serviços de Budget e Histórico de Preço"
Cohesion: 0.15
Nodes (6): InvoiceItem, BudgetService, Carbon, PriceHistoryService, Carbon\Carbon, Illuminate\Support\Facades\DB

### Community 18 - "Serviço de Estatísticas do Dashboard"
Cohesion: 0.17
Nodes (4): OverallStats, self, DashboardService, Carbon

### Community 19 - "Model User, Policies e Config de Auth"
Cohesion: 0.12
Nodes (8): User, BudgetPolicy, CategoryPolicy, ShoppingListPolicy, FavoriteProductFactory, InvoiceFactory, Illuminate\Contracts\Auth\MustVerifyEmail, Illuminate\Foundation\Auth\User

### Community 20 - "Requests de Sugestão via IA (Categoria/Produto)"
Cohesion: 0.12
Nodes (4): AiSuggestCategoryKeywordsRequest, DismissProductAliasSuggestionRequest, Illuminate\Contracts\Validation\Validator, Illuminate\Http\Exceptions\HttpResponseException

### Community 21 - "Controller de Alias de Produto (API)"
Cohesion: 0.12
Nodes (4): ProductAliasController, AiSuggestProductNameRequest, MergeProductAliasRequest, StoreProductAliasRequest

### Community 22 - "Estratégias de Busca Global (Search)"
Cohesion: 0.21
Nodes (6): SearchStrategyInterface, InvoiceSearchStrategy, IssuerSearchStrategy, ProductSearchStrategy, SearchService, Illuminate\Support\Collection

### Community 23 - "Controller de Relatórios (Export PDF/CSV)"
Cohesion: 0.15
Nodes (7): StreamedResponse, ReportController, StreamedResponse, ReportController, ReportService, Barryvdh\DomPDF\Facade\Pdf, Symfony\Component\HttpFoundation\StreamedResponse

### Community 24 - "Importação e Persistência de Invoice"
Cohesion: 0.20
Nodes (6): ImportInvoiceAction, InvoiceImported, Invoice, InvoicePayment, Illuminate\Foundation\Events\Dispatchable, Illuminate\Support\Arr

### Community 25 - "Sugestão de Nome de Produto via IA"
Cohesion: 0.18
Nodes (3): self, ProductNameAiSuggestion, ProductNameAiSuggestionService

### Community 26 - "Requests de Itens da Lista de Compras"
Cohesion: 0.13
Nodes (4): AddShoppingListItemRequest, UpdateShoppingListItemRequest, UpdateShoppingListRequest, ShoppingListService

### Community 27 - "Controller e Model de Lista de Compras"
Cohesion: 0.20
Nodes (4): ShoppingListController, ShoppingList, ShoppingListItem, ShoppingListItemFactory

### Community 28 - "Enums e Factories de Plano de Assinatura"
Cohesion: 0.13
Nodes (7): SubscriptionPlan, SubscriptionStatus, static, SubscriptionFactory, Illuminate\Support\Facades\Hash, Illuminate\Support\Str, Pdo\Mysql

### Community 29 - "Middleware Pro Plan e Exceções"
Cohesion: 0.19
Nodes (7): StoreBudgetAction, ProFeatureRequiredException, EnsureIsSuperAdmin, EnsureUserHasProPlan, Closure, Exception, Symfony\Component\HttpFoundation\Response

### Community 30 - "Registro e Auth Controller (API)"
Cohesion: 0.15
Nodes (5): UpdateUserLocationAction, AuthController, RegisterController, RegisterRequest, Laravel\Sanctum\PersonalAccessToken

### Community 31 - "Controller de Compras Recorrentes"
Cohesion: 0.18
Nodes (4): RecurringPurchaseController, RecurringPurchaseController, AddToShoppingListRequest, RecurringPurchaseService

### Community 32 - "Relacionamentos do Model User"
Cohesion: 0.13
Nodes (7): Illuminate\Auth\MustVerifyEmail, Illuminate\Database\Eloquent\Relations\BelongsToMany, Illuminate\Database\Eloquent\Relations\HasOne, Illuminate\Database\Eloquent\Relations\MorphMany, Illuminate\Database\Eloquent\Relations\MorphOne, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens

### Community 33 - "Observer de User e Seeders do Banco"
Cohesion: 0.23
Nodes (6): CreateFreeSubscriptionAction, UserObserver, DatabaseSeeder, VisualCheckUserSeeder, Illuminate\Database\Console\Seeds\WithoutModelEvents, Illuminate\Database\Seeder

### Community 39 - "Criação de Usuário Social e Categorias Padrão"
Cohesion: 0.29
Nodes (3): CreateDefaultCategoriesAction, FindOrCreateSocialUser, Laravel\Socialite\Contracts\User

### Community 44 - "Configuração de Logging (Monolog)"
Cohesion: 0.40
Nodes (4): Monolog\Handler\NullHandler, Monolog\Handler\StreamHandler, Monolog\Handler\SyslogUdpHandler, Monolog\Processor\PsrLogMessageProcessor

### Community 45 - "Middleware de Sanctum e CSRF"
Cohesion: 0.40
Nodes (4): Illuminate\Cookie\Middleware\EncryptCookies, Illuminate\Foundation\Http\Middleware\ValidateCsrfToken, Laravel\Sanctum\Http\Middleware\AuthenticateSession, Laravel\Sanctum\Sanctum

### Community 49 - "Console Commands e Scheduler"
Cohesion: 0.50
Nodes (3): Illuminate\Foundation\Inspiring, Illuminate\Support\Facades\Artisan, Illuminate\Support\Facades\Schedule

## Knowledge Gaps
- **32 isolated node(s):** `pages`, `TAB_TOGGLE_SELECTORS`, `DETAIL_FAVORITE_CLASSES`, `DETAIL_OUTLINE_CLASSES`, `AVATAR_FAVORITE_CLASSES` (+27 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 306 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **27 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `Model User, Policies e Config de Auth` to `Gestão de Emitentes (Issuer): Favoritar e Apelido`, `Relacionamentos do Model User`, `Observer de User e Seeders do Banco`, `Estratégias de Importação de NFC-e (QR/XML/Chave)`, `Captura e Geocodificação de Localização do Usuário`, `Criação de Usuário Social e Categorias Padrão`, `Produto Favorito e Alerta de Queda de Preço`, `Conta do Usuário (Web + API)`, `Admin de Assinatura e Budget/Dashboard (Web)`, `Upload de Avatar e Model File`, `Factories de Teste: Budget/Category/Invoice/Issuer`, `Enums e Factories de Plano de Assinatura`, `Middleware Pro Plan e Exceções`, `Registro e Auth Controller (API)`?**
  _High betweenness centrality (0.102) - this node is a cross-community bridge._
- **Why does `NFCeService` connect `Sugestão de Keywords de Categoria via IA` to `Estratégias de Importação de NFC-e (QR/XML/Chave)`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Why does `InvoiceItem` connect `Serviços de Budget e Histórico de Preço` to `Gestão de Emitentes (Issuer): Favoritar e Apelido`, `Serviço de Sugestão/Unificação de Alias de Produto`, `CRUD de Categorias (Web + API)`, `Produto Favorito e Alerta de Queda de Preço`, `Conta do Usuário (Web + API)`, `Filtro de Localização da Lista de Compras e Cálculo de Distância`, `Serviços de Alias de Produto e Comparação de Preço`, `Factories de Teste: Budget/Category/Invoice/Issuer`, `Serviço de Estatísticas do Dashboard`, `Estratégias de Busca Global (Search)`, `Importação e Persistência de Invoice`?**
  _High betweenness centrality (0.044) - this node is a cross-community bridge._
- **What connects `pages`, `TAB_TOGGLE_SELECTORS`, `DETAIL_FAVORITE_CLASSES` to the rest of the system?**
  _32 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Gestão de Emitentes (Issuer): Favoritar e Apelido` be split into smaller, more focused modules?**
  _Cohesion score 0.05555555555555555 - nodes in this community are weakly interconnected._
- **Should `Sugestão de Keywords de Categoria via IA` be split into smaller, more focused modules?**
  _Cohesion score 0.055944055944055944 - nodes in this community are weakly interconnected._
- **Should `Estratégias de Importação de NFC-e (QR/XML/Chave)` be split into smaller, more focused modules?**
  _Cohesion score 0.07175141242937853 - nodes in this community are weakly interconnected._