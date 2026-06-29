# Como obter credenciais de login social

Este documento explica como configurar as credenciais para cada provider de login social suportado: Google, Facebook e Apple.

---

## Google

**Pré-requisito:** conta Google. Gratuito.

1. Acesse [console.cloud.google.com](https://console.cloud.google.com/) e crie ou selecione um projeto.
2. No menu lateral: **APIs e serviços → Credenciais**.
3. Se for a primeira vez, clique em **Configurar tela de consentimento OAuth**:
   - Tipo de usuário: **Externo**
   - Preencha: nome do app, e-mail de suporte, e-mail do desenvolvedor
   - Escopos: adicione `email`, `profile`, `openid`
   - Salve e continue até o fim
4. Volte em **Credenciais → Criar credenciais → ID do cliente OAuth**.
5. Tipo de aplicativo: **Aplicativo da Web**.
6. Em **URIs de redirecionamento autorizados**, adicione:
   ```
   http://localhost:8000/login/social/google/callback   ← desenvolvimento
   https://seudominio.com/login/social/google/callback  ← produção
   ```
7. Clique em **Criar**. Copie o **Client ID** e o **Client Secret**.

Variáveis de ambiente:
```
GOOGLE_CLIENT_ID=seu-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/login/social/google/callback
```

---

## Facebook

**Pré-requisito:** conta Facebook e conta de desenvolvedor Meta. Gratuito.

1. Acesse [developers.facebook.com](https://developers.facebook.com/) e faça login.
2. Clique em **Meus Apps → Criar App**.
3. Caso de uso: selecione **Autenticar e solicitar dados de usuários**.
4. Preencha nome do app e e-mail de contato. Clique em **Criar app**.
5. No painel do app, vá em **Configurações → Básicas**:
   - Anote o **ID do App** (client_id) e a **Chave Secreta do App** (client_secret — clique em "Mostrar").
   - Preencha **URL do site** com sua URL base (ex: `http://localhost:8000`).
6. No menu lateral, vá em **Casos de uso → Personalizar → Login com o Facebook → Ir para as configurações**.
7. Em **URIs de redirecionamento OAuth válidos**, adicione:
   ```
   http://localhost:8000/login/social/facebook/callback
   https://seudominio.com/login/social/facebook/callback
   ```
8. Salve as alterações.

> **Atenção:** em desenvolvimento o app fica em modo "Desenvolvimento" e só funciona para usuários administradores/testadores cadastrados no app. Para uso público, é necessário passar pela revisão da Meta e colocar o app em modo "Ao vivo".

Variáveis de ambiente:
```
FACEBOOK_CLIENT_ID=seu-app-id
FACEBOOK_CLIENT_SECRET=sua-chave-secreta
FACEBOOK_REDIRECT_URI=http://localhost:8000/login/social/facebook/callback
```

---

## Apple

**Pré-requisito:** conta no [Apple Developer Program](https://developer.apple.com/programs/) — **custa USD 99/ano**.

O Apple Sign In exige mais passos que os outros providers. Siga na ordem:

### Passo 1 — Criar um App ID

1. Acesse [developer.apple.com → Account → Certificates, IDs & Profiles](https://developer.apple.com/account/).
2. Vá em **Identifiers → App IDs → "+"**.
3. Tipo: **App**. Clique em Continue.
4. Descrição: nome do seu app. Bundle ID: `com.seuapp` (pode ser qualquer identificador reverso).
5. Em **Capabilities**, marque **Sign In with Apple**.
6. Clique em **Continue → Register**.

### Passo 2 — Criar um Services ID (usado como `client_id` na web)

1. Ainda em **Identifiers**, clique em **"+"** e selecione **Services IDs**. Continue.
2. Descrição: ex `My Expenses Web`. Identifier: ex `com.seuapp.web` — **este valor será o `APPLE_CLIENT_ID`**.
3. Registre e depois **clique no Services ID** que acabou de criar.
4. Marque **Sign In with Apple** e clique em **Configure**:
   - Primary App ID: selecione o App ID criado no Passo 1
   - Domains: `seudominio.com` (sem https://)
   - Return URLs:
     ```
     https://seudominio.com/login/social/apple/callback
     ```
   - Para desenvolvimento local, o Apple não aceita `localhost`. Use [ngrok](https://ngrok.com/) ou similar para expor uma URL pública temporária.
5. Salve e clique em **Continue → Save**.

### Passo 3 — Criar uma Key (gera o arquivo `.p8`)

1. Vá em **Keys → "+"**.
2. Nome: ex `My Expenses Sign In`. Marque **Sign In with Apple** e clique em **Configure**.
3. Selecione o Primary App ID do Passo 1. Clique em **Save → Continue → Register**.
4. **Baixe o arquivo `.p8`** — você só pode baixar uma vez. Guarde com segurança em `storage/app/private/`.
5. Anote o **Key ID** (ex: `ABC123DEFG`) — será o `APPLE_KEY_ID`.

### Passo 4 — Anotar o Team ID

No canto superior direito da conta de desenvolvedor, o **Team ID** aparece (ex: `ABCDE12345`) — será o `APPLE_TEAM_ID`.

### Passo 5 — Configurar as variáveis de ambiente

O `socialiteproviders/apple` usa `key_id`, `team_id` e o conteúdo do `.p8` para **gerar automaticamente o `client_secret`** (JWT de curta duração). Você não precisa gerá-lo manualmente — deixe `APPLE_CLIENT_SECRET` vazio.

```
APPLE_CLIENT_ID=com.seuapp.web
APPLE_CLIENT_SECRET=
APPLE_REDIRECT_URI=https://seudominio.com/login/social/apple/callback
APPLE_KEY_ID=ABC123DEFG
APPLE_TEAM_ID=ABCDE12345
APPLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIGH...\n-----END PRIVATE KEY-----"
```

O `APPLE_PRIVATE_KEY` é o conteúdo do arquivo `.p8` com `\n` no lugar das quebras de linha (em uma única string). Você pode obtê-lo com:

```bash
cat AuthKey_ABC123DEFG.p8 | awk 'NF {printf "%s\\n", $0}' | sed 's/\\n$//'
```

> **Importante:** o Apple Sign In só envia nome e e-mail do usuário na **primeira** autenticação. O sistema já trata isso — nas próximas vezes, o login é identificado apenas pelo `provider_id`.
