@extends('layout.main')
@section('page-module', 'account')

@section('content')

{{-- ===== HERO ===== --}}
<style>
  .hero-bg {
    background-image: url('{{ asset('assets/media/images/2600x1200/bg-1.png') }}');
  }
  .dark .hero-bg {
    background-image: url('{{ asset('assets/media/images/2600x1200/bg-1-dark.png') }}');
  }
</style>
<div class="bg-center bg-cover bg-no-repeat hero-bg mb-0">
  <div class="kt-container-fixed">
    <div class="flex flex-col items-center gap-2 lg:gap-3.5 py-4 lg:pt-5 lg:pb-10">
      <div class="rounded-full border-4 border-green-500 size-[100px] shrink-0 flex items-center justify-center bg-primary text-primary-foreground shadow-lg overflow-hidden">
        @if($user->avatar)
          <img src="{{ $user->avatar->url() }}" alt="{{ $user->name }}" class="size-full object-cover" />
        @else
          <span class="text-3xl font-bold leading-none select-none">{{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}</span>
        @endif
      </div>
      <div class="flex items-center gap-1.5">
        <div class="text-lg leading-5 font-semibold text-mono">{{ $user->name }}</div>
        <span class="kt-badge kt-badge-sm {{ $user->isPro() ? 'kt-badge-success' : 'kt-badge-secondary' }}">
          {{ $user->isPro() ? 'Pro' : 'Grátis' }}
        </span>
      </div>
      <div class="flex flex-wrap justify-center gap-1 lg:gap-4.5 text-sm">
        <div class="flex gap-1.25 items-center">
          <i class="ki-filled ki-sms text-muted-foreground text-sm"></i>
          <span class="text-secondary-foreground font-medium">{{ $user->email }}</span>
        </div>
        <div class="flex gap-1.25 items-center">
          <i class="ki-filled ki-calendar text-muted-foreground text-sm"></i>
          <span class="text-secondary-foreground font-medium">
            Membro desde {{ $user->created_at->translatedFormat('M/Y') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===== TAB NAV BAR ===== --}}
<div class="kt-container-fixed px-4 lg:px-6">
  <div class="flex items-center flex-wrap md:flex-nowrap lg:items-end justify-between border-b border-b-border gap-3 lg:gap-6 mb-5 lg:mb-7.5">
    <div class="kt-scrollable-x-auto">
      <div class="flex items-center gap-0" data-kt-tabs="true">
        <button
          class="kt-tab-toggle active border-b-2 border-b-transparent kt-tab-active:border-b-primary pb-3 lg:pb-4 px-3 text-sm font-medium text-secondary-foreground kt-tab-active:text-primary hover:text-primary transition-colors whitespace-nowrap"
          data-kt-tab-toggle="#tab_overview"
        >
          Visão Geral
        </button>
        <button
          class="kt-tab-toggle border-b-2 border-b-transparent kt-tab-active:border-b-primary pb-3 lg:pb-4 px-3 text-sm font-medium text-secondary-foreground kt-tab-active:text-primary hover:text-primary transition-colors whitespace-nowrap"
          data-kt-tab-toggle="#tab_settings"
        >
          <i class="ki-filled ki-profile-circle me-1 text-sm"></i>
          Configurações
        </button>
        <button
          class="kt-tab-toggle border-b-2 border-b-transparent kt-tab-active:border-b-primary pb-3 lg:pb-4 px-3 text-sm font-medium text-secondary-foreground kt-tab-active:text-primary hover:text-primary transition-colors whitespace-nowrap"
          data-kt-tab-toggle="#tab_security"
        >
          <i class="ki-filled ki-lock me-1 text-sm"></i>
          Segurança
        </button>
      </div>
    </div>
    <div class="flex items-center lg:pb-4 gap-2.5 mb-3 lg:mb-0 shrink-0">
      <button class="kt-btn kt-btn-primary kt-btn-sm" id="btn_edit_profile">
        <i class="ki-filled ki-pencil text-sm"></i>
        Editar Perfil
      </button>
    </div>
  </div>
</div>

{{-- ===== MAIN GRID ===== --}}
<div class="kt-container-fixed pb-10 px-4 lg:px-6">
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 lg:gap-7.5">

    {{-- === COLUNA ESQUERDA === --}}
    <div class="col-span-1">
      <div class="grid gap-5 lg:gap-7.5">

        {{-- Card: Dados Pessoais --}}
        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Dados Pessoais</h3>
          </div>
          <div class="kt-card-content pt-4 pb-3">
            <table class="kt-table-auto w-full">
              <tbody>
                <tr>
                  <td class="text-sm text-secondary-foreground pb-3.5 pe-3 whitespace-nowrap">Nome:</td>
                  <td class="text-sm text-mono pb-3.5">{{ $user->name }}</td>
                </tr>
                <tr>
                  <td class="text-sm text-secondary-foreground pb-3.5 pe-3 whitespace-nowrap">Email:</td>
                  <td class="text-sm text-mono pb-3.5 break-all">{{ $user->email }}</td>
                </tr>
                @if($user->profile?->maskedCpf())
                <tr>
                  <td class="text-sm text-secondary-foreground pb-3.5 pe-3 whitespace-nowrap">CPF:</td>
                  <td class="text-sm text-mono pb-3.5">{{ $user->profile->maskedCpf() }}</td>
                </tr>
                @endif
                @if($user->profile?->cidade)
                <tr>
                  <td class="text-sm text-secondary-foreground pb-3.5 pe-3 whitespace-nowrap">Cidade/Estado:</td>
                  <td class="text-sm text-mono pb-3.5">{{ $user->profile->cidade }}/{{ $user->profile->estado }}</td>
                </tr>
                @endif
                <tr>
                  @php $since = $user->created_at; @endphp
                  <td class="text-sm text-secondary-foreground pb-3.5 pe-3 whitespace-nowrap">Membro desde:</td>
                  <td class="text-sm text-mono pb-3.5">{{ $since->format('d/m/Y') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        {{-- Card: Estatísticas --}}
        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Estatísticas</h3>
          </div>
          <div class="kt-card-content pb-7.5">
            <div class="grid gap-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 shrink-0">
                    <i class="ki-filled ki-document text-primary text-sm"></i>
                  </div>
                  <span class="text-sm text-secondary-foreground">Notas Fiscais</span>
                </div>
                <span class="text-sm font-bold text-mono tabular-nums">{{ number_format($stats['total_invoices']) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="flex items-center justify-center size-9 rounded-lg bg-violet-500/10 shrink-0">
                    <i class="ki-filled ki-basket text-violet-600 text-sm"></i>
                  </div>
                  <span class="text-sm text-secondary-foreground">Itens Comprados</span>
                </div>
                <span class="text-sm font-bold text-mono tabular-nums">{{ number_format($stats['total_items']) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="flex items-center justify-center size-9 rounded-lg bg-green-500/10 shrink-0">
                    <i class="ki-filled ki-dollar text-green-600 text-sm"></i>
                  </div>
                  <span class="text-sm text-secondary-foreground">Total Gasto</span>
                </div>
                <span class="text-sm font-bold text-mono tabular-nums">R$ {{ number_format($stats['total_spent'], 2, ',', '.') }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- === COLUNA DIREITA (2 colunas) === --}}
    <div class="col-span-2">
      @if(session('success'))
        <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 mb-5 lg:mb-7.5" role="status">
          <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
          <span class="text-sm text-green-600 font-medium">{{ session('success') }}</span>
        </div>
      @endif

      {{-- TAB: Visão Geral --}}
      <div id="tab_overview">

        @if($locationSuggestion)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 mb-5 lg:mb-7.5">
          <div class="flex items-center gap-3">
            <i class="ki-filled ki-geolocation text-primary text-lg shrink-0"></i>
            <span class="text-sm text-foreground">
              Notamos que você compra frequentemente em
              <strong>{{ $locationSuggestion['city'] }}/{{ $locationSuggestion['state'] }}</strong>.
              Deseja atualizar sua localização cadastrada?
            </span>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <form method="POST" action="{{ route('account.update') }}">
              @csrf
              @method('PATCH')
              <input type="hidden" name="name" value="{{ $user->name }}">
              <input type="hidden" name="email" value="{{ $user->email }}">
              <input type="hidden" name="cidade" value="{{ $locationSuggestion['city'] }}">
              <input type="hidden" name="estado" value="{{ $locationSuggestion['state'] }}">
              <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">Atualizar</button>
            </form>
            <form method="POST" action="{{ route('account.location-suggestion.dismiss') }}">
              @csrf
              <button type="submit" class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon" title="Dispensar">
                <i class="ki-filled ki-cross text-sm"></i>
              </button>
            </form>
          </div>
        </div>
        @endif

        {{-- Card: Plano --}}
        @php $subscription = $user->subscription; @endphp
        <div class="kt-card mb-5 lg:mb-7.5">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Meu plano</h3>
          </div>
          <div class="kt-card-content p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
              <div class="flex flex-col gap-1">
                <span class="text-sm font-medium text-foreground">
                  {{ $user->isPro() ? 'Plano Pro' : 'Plano Grátis' }}
                </span>
                <span class="text-xs text-secondary-foreground">
                  @if($user->isPro() && $subscription?->expires_at)
                    Ativo até {{ $subscription->expires_at->format('d/m/Y') }}.
                  @elseif($user->isPro())
                    Ativo, sem data de expiração.
                  @else
                    Recursos Pro: comparativo de preços, compras recorrentes, relatórios agendados e categorização com IA.
                  @endif
                </span>
              </div>
              @unless($user->isPro())
                <a href="{{ route('subscription.upgrade') }}" class="kt-btn kt-btn-primary kt-btn-sm">
                  <i class="ki-filled ki-crown"></i> Conhecer o Pro
                </a>
              @endunless
            </div>
          </div>
        </div>

        {{-- Card: Privacidade --}}
        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Privacidade</h3>
          </div>
          <div class="kt-card-content p-6 flex flex-col gap-3">
            <p class="text-sm text-secondary-foreground">
              @if($user->terms_accepted_at)
                Você aceitou os Termos de Uso e a Política de Privacidade (versão {{ $user->terms_version }}) em {{ $user->terms_accepted_at->format('d/m/Y') }}.
              @else
                Você ainda não aceitou a versão atual dos Termos de Uso.
              @endif
            </p>
            <div class="flex flex-wrap gap-2">
              <a href="{{ route('legal.terms') }}" class="kt-btn kt-btn-outline kt-btn-sm" target="_blank" rel="noopener">Termos de Uso</a>
              <a href="{{ route('legal.privacy') }}" class="kt-btn kt-btn-outline kt-btn-sm" target="_blank" rel="noopener">Política de Privacidade</a>
              <button type="button" class="kt-btn kt-btn-ghost kt-btn-sm" data-open-tab="security">Exportar ou excluir meus dados</button>
            </div>
          </div>
        </div>
      </div>

      {{-- TAB: Configurações --}}
      <div id="tab_settings" class="hidden">

        {{-- Card: Foto de Perfil --}}
        <div class="kt-card mb-5 lg:mb-7.5">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Foto de Perfil</h3>
          </div>
          <div class="kt-card-content p-6">
            <form method="POST" action="{{ route('account.avatar') }}" enctype="multipart/form-data" class="kt-form max-w-lg">
              @csrf
              <div class="flex items-center gap-5">
                <div class="rounded-full size-16 shrink-0 flex items-center justify-center bg-primary text-primary-foreground overflow-hidden border border-border" id="avatar_preview_frame">
                  @if($user->avatar)
                    <img src="{{ $user->avatar->url() }}" alt="{{ $user->name }}" class="size-full object-cover" id="avatar_preview_img" />
                  @else
                    <span class="text-lg font-bold select-none" id="avatar_preview_initials">{{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}</span>
                  @endif
                </div>
                <div class="flex flex-col gap-2 grow">
                  <input
                    type="file"
                    id="avatar"
                    name="avatar"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="kt-input @error('avatar') border-destructive @enderror"
                  />
                  <span class="text-xs text-secondary-foreground">JPG, PNG ou WEBP. Máximo 2MB.</span>
                  @error('avatar')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div class="pt-4">
                <button type="submit" class="kt-btn kt-btn-primary">
                  <i class="ki-filled ki-cloud-add text-base"></i>
                  Enviar foto
                </button>
              </div>
            </form>
          </div>
        </div>

        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Informações Pessoais</h3>
          </div>
          <div class="kt-card-content p-6">
            <form method="POST" action="{{ route('account.update') }}" class="kt-form max-w-lg">
              @csrf
              @method('PATCH')
              <input type="hidden" name="_form" value="account">
              <div class="space-y-4">
                <div class="kt-form-item">
                  <label class="kt-form-label" for="name">Nome completo</label>
                  <div class="kt-form-control">
                    <input
                      type="text"
                      id="name"
                      name="name"
                      class="kt-input @error('name') border-destructive @enderror"
                      value="{{ old('name', $user->name) }}"
                      placeholder="Seu nome completo"
                      autocomplete="name"
                    />
                  </div>
                  @error('name')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
                </div>
                <div class="kt-form-item">
                  <label class="kt-form-label" for="email">E-mail</label>
                  <div class="kt-form-control">
                    <input
                      type="email"
                      id="email"
                      name="email"
                      class="kt-input @error('email') border-destructive @enderror"
                      value="{{ old('email', $user->email) }}"
                      data-original="{{ $user->email }}"
                      placeholder="seu@email.com"
                      autocomplete="email"
                    />
                  </div>
                  @error('email')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
                </div>
                @if($user->password !== null)
                  <div class="kt-form-item {{ old('_form') === 'account' && $errors->has('current_password') ? '' : 'hidden' }}" id="email_password_field">
                    <label class="kt-form-label" for="email_current_password">Senha atual</label>
                    <div class="kt-form-control">
                      <input type="password" id="email_current_password" name="current_password"
                             class="kt-input @if(old('_form') === 'account' && $errors->has('current_password')) border-destructive @endif"
                             placeholder="Necessária para trocar o e-mail" autocomplete="current-password" />
                    </div>
                    @if(old('_form') === 'account')
                      @error('current_password')
                        <div class="kt-form-message text-destructive">{{ $message }}</div>
                      @enderror
                    @endif
                    <p class="text-xs text-secondary-foreground mt-1">Ao trocar o e-mail, você vai precisar confirmá-lo de novo.</p>
                  </div>
                @endif
                <div class="flex gap-3">
                  <div class="kt-form-item grow">
                    <label class="kt-form-label" for="cidade">Cidade <span class="text-muted-foreground font-normal">(opcional)</span></label>
                    <div class="kt-form-control">
                      <input
                        type="text"
                        id="cidade"
                        name="cidade"
                        class="kt-input @error('cidade') border-destructive @enderror"
                        value="{{ old('cidade', $user->profile?->cidade) }}"
                        placeholder="Sua cidade"
                      />
                    </div>
                    @error('cidade')
                      <div class="kt-form-message text-destructive">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="kt-form-item w-32 shrink-0">
                    <label class="kt-form-label" for="estado">Estado</label>
                    <div class="kt-form-control">
                      <select id="estado" name="estado" class="kt-select w-full @error('estado') border-destructive @enderror" data-kt-select="true" data-kt-select-placeholder="UF">
                        <option value="">UF</option>
                        @include('partials._uf-options', ['selectedUf' => old('estado', $user->profile?->estado)])
                      </select>
                    </div>
                    @error('estado')
                      <div class="kt-form-message text-destructive">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
                <p class="text-xs text-secondary-foreground">
                  <i class="ki-filled ki-information-2 text-primary me-0.5"></i>
                  Usada na Lista de Compras para mostrar produtos num raio de 15km perto de você.
                </p>
                <div class="flex items-center gap-2.5">
                  <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-action="use-my-location">
                    <i class="ki-filled ki-geolocation"></i>
                    Usar minha localização
                  </button>
                  <span id="locationCaptureStatus" class="text-xs text-green-600 hidden">
                    <i class="ki-filled ki-check-circle me-0.5"></i>
                    Localização salva
                  </span>
                </div>
                <div class="pt-2">
                  <button type="submit" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-check text-base"></i>
                    Salvar alterações
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

      </div>

      {{-- TAB: Segurança --}}
      <div id="tab_security" class="hidden">

        <div class="kt-card mb-5 lg:mb-7.5">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Meus Dados</h3>
          </div>
          <div class="kt-card-content p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
              <div>
                <p class="text-sm font-medium text-foreground">Exportar meus dados</p>
                <p class="text-xs text-secondary-foreground mt-0.5">
                  Baixe uma cópia dos seus dados pessoais e histórico de compras (portabilidade, conforme a LGPD). Você receberá um link por e-mail.
                </p>
              </div>
              <form method="POST" action="{{ route('account.export') }}" class="shrink-0">
                @csrf
                <button type="submit" class="kt-btn kt-btn-outline kt-btn-sm">
                  <i class="ki-filled ki-exit-down"></i>
                  Exportar dados
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">{{ $user->password === null ? 'Definir senha' : 'Alterar senha' }}</h3>
          </div>
          <div class="kt-card-content p-6">
            <p class="text-xs text-secondary-foreground mb-5">
              @if($user->password === null)
                Você entrou com uma conta social e ainda não tem senha. Defina uma para também entrar com e-mail e senha.
              @else
                Use uma senha forte com no mínimo 8 caracteres. Ao trocar, os outros dispositivos serão desconectados.
              @endif
            </p>
            <form method="POST" action="{{ route('account.password') }}" class="kt-form max-w-lg">
              @csrf
              @method('PATCH')
              <input type="hidden" name="_form" value="password">
              <div class="space-y-4">
                @if($user->password !== null)
                <div class="kt-form-item">
                  <label class="kt-form-label" for="current_password">Senha atual</label>
                  <div class="kt-form-control">
                    <input
                      type="password"
                      id="current_password"
                      name="current_password"
                      class="kt-input @if(old('_form') === 'password') @error('current_password') border-destructive @enderror @endif"
                      placeholder="Digite sua senha atual"
                      autocomplete="current-password"
                    />
                  </div>
                  @if(old('_form') === 'password')
                  @error('current_password')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
                  @endif
                </div>
                @endif
                <div class="kt-form-item">
                  <label class="kt-form-label" for="password">Nova senha</label>
                  <div class="kt-form-control">
                    <input
                      type="password"
                      id="password"
                      name="password"
                      class="kt-input @error('password') border-destructive @enderror"
                      placeholder="Mínimo 8 caracteres"
                      autocomplete="new-password"
                    />
                  </div>
                  @error('password')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
                </div>
                <div class="kt-form-item">
                  <label class="kt-form-label" for="password_confirmation">Confirmar nova senha</label>
                  <div class="kt-form-control">
                    <input
                      type="password"
                      id="password_confirmation"
                      name="password_confirmation"
                      class="kt-input"
                      placeholder="Repita a nova senha"
                      autocomplete="new-password"
                    />
                  </div>
                </div>
                <div class="pt-2">
                  <button type="submit" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-lock text-base"></i>
                    Alterar senha
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        {{-- Card: Sessões --}}
        <div class="kt-card mt-5 lg:mt-7.5">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Dispositivos conectados</h3>
          </div>
          <div class="kt-card-content p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
              <p class="text-sm text-secondary-foreground max-w-md">
                Desconecte todos os outros navegadores e o aplicativo do celular. Esta sessão continua ativa.
              </p>
              <form method="POST" action="{{ route('account.sessions.revoke-others') }}" class="shrink-0">
                @csrf
                <button type="submit" class="kt-btn kt-btn-outline kt-btn-sm">
                  <i class="ki-filled ki-exit-right"></i> Sair dos outros dispositivos
                </button>
              </form>
            </div>
          </div>
        </div>

        {{-- Card: Zona de Perigo --}}
        <div class="kt-card border-destructive/30 mt-5 lg:mt-7.5">
          <div class="kt-card-header">
            <h3 class="kt-card-title text-destructive">Zona de Perigo</h3>
          </div>
          <div class="kt-card-content p-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
              <div>
                <p class="text-sm font-medium text-foreground">Excluir minha conta</p>
                <p class="text-xs text-secondary-foreground mt-0.5">
                  Remove seus dados pessoais permanentemente. Notas fiscais já importadas são mantidas de forma anônima, sem vínculo com você, para preservar o histórico de preços da comunidade.
                </p>
              </div>
              <button type="button" class="kt-btn kt-btn-destructive kt-btn-sm shrink-0" data-kt-modal-toggle="#deleteAccountModal">
                <i class="ki-filled ki-trash"></i>
                Excluir conta
              </button>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<div class="kt-modal" data-kt-modal="true" id="deleteAccountModal">
  <div class="kt-modal-content max-w-[440px] top-[15%]">
    <div class="kt-modal-header">
      <h3 class="kt-modal-title">Excluir sua conta</h3>
      <button class="kt-modal-close" data-kt-modal-dismiss="#deleteAccountModal" aria-label="Fechar">
        <i class="ki-filled ki-cross"></i>
      </button>
    </div>
    <form method="POST" action="{{ route('account.destroy') }}">
      @csrf
      @method('DELETE')
      <input type="hidden" name="_form" value="delete">
      <div class="kt-modal-body flex flex-col gap-3">
        <p class="text-sm text-secondary-foreground">
          Esta ação é permanente. Seu perfil, foto, categorias, listas e assinatura serão apagados.
          @if($user->password !== null) Confirme sua senha atual para continuar. @endif
        </p>
        @if($user->password !== null)
        <div class="kt-form-item">
          <label class="kt-form-label" for="delete_current_password">Senha atual</label>
          <div class="kt-form-control">
            <input
              type="password"
              id="delete_current_password"
              name="current_password"
              class="kt-input @if(old('_form') === 'delete') @error('current_password') border-destructive @enderror @endif"
              placeholder="Digite sua senha atual"
              autocomplete="current-password"
            />
          </div>
          @if(old('_form') === 'delete')
          @error('current_password')
            <div class="kt-form-message text-destructive">{{ $message }}</div>
          @enderror
          @endif
        </div>
        @endif
      </div>
      <div class="kt-modal-footer">
        <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#deleteAccountModal">Cancelar</button>
        <button type="submit" class="kt-btn kt-btn-destructive">Excluir permanentemente</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
@php
    $initialTab = request('tab')
        ?? (in_array(old('_form'), ['password', 'delete'], true) ? 'security' : (old('_form') === 'account' || $errors->has('avatar') ? 'settings' : null));
@endphp
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        openTab: @json($initialTab),
        openDeleteAccountModal: @json(old('_form') === 'delete' && $errors->has('current_password')),
    });
</script>
@endpush
