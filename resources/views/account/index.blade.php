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
          <span class="text-3xl font-bold leading-none select-none">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
        @endif
      </div>
      <div class="flex items-center gap-1.5">
        <div class="text-lg leading-5 font-semibold text-mono">{{ $user->name }}</div>
        <span class="kt-badge kt-badge-success kt-badge-sm">
          <span class="size-1.5 rounded-full bg-current inline-block me-1"></span>
          Ativo
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
            Membro desde {{ ($memberSince ? \Carbon\Carbon::parse($memberSince) : $user->created_at)->translatedFormat('M/Y') }}
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
                @if($user->profile?->cpf)
                <tr>
                  <td class="text-sm text-secondary-foreground pb-3.5 pe-3 whitespace-nowrap">CPF:</td>
                  <td class="text-sm text-mono pb-3.5">{{ $user->profile->cpf }}</td>
                </tr>
                @endif
                <tr>
                  @php $since = $memberSince ? \Carbon\Carbon::parse($memberSince) : $user->created_at; @endphp
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
                <span class="text-sm font-bold text-mono tabular-nums">{{ number_format($totalInvoices) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="flex items-center justify-center size-9 rounded-lg bg-violet-500/10 shrink-0">
                    <i class="ki-filled ki-basket text-violet-600 text-sm"></i>
                  </div>
                  <span class="text-sm text-secondary-foreground">Itens Comprados</span>
                </div>
                <span class="text-sm font-bold text-mono tabular-nums">{{ number_format($totalItems) }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="flex items-center justify-center size-9 rounded-lg bg-green-500/10 shrink-0">
                    <i class="ki-filled ki-dollar text-green-600 text-sm"></i>
                  </div>
                  <span class="text-sm text-secondary-foreground">Total Gasto</span>
                </div>
                <span class="text-sm font-bold text-mono tabular-nums">R$ {{ number_format($totalSpent, 2, ',', '.') }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    {{-- === COLUNA DIREITA (2 colunas) === --}}
    <div class="col-span-2">

      {{-- TAB: Visão Geral --}}
      <div id="tab_overview">

        {{-- Card boas-vindas --}}
        <div class="kt-card mb-5 lg:mb-7.5">
          <div class="kt-card-content px-8 py-7.5">
            <div class="flex flex-wrap md:flex-nowrap items-center gap-6">
              <div class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold text-mono">Bem-vindo, {{ $user->name }}!</h2>
                <p class="text-sm text-secondary-foreground leading-5.5">
                  Gerencie suas informações pessoais, segurança e visualize o resumo das suas compras.
                </p>
              </div>
            </div>
          </div>
        </div>

        {{-- Últimas compras --}}
        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Últimas Compras</h3>
            <div class="kt-card-toolbar">
              <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-secondary kt-btn-sm">Ver todas</a>
            </div>
          </div>
          <div class="kt-card-content">
            @if($recentInvoices->isNotEmpty())
              <div class="kt-scrollable-x-auto">
                <table class="kt-table table-auto kt-table-border w-full">
                  <thead>
                    <tr>
                      <th class="text-start text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Data</th>
                      <th class="text-start text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Emissor</th>
                      <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Valor</th>
                      <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide"></th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($recentInvoices as $invoice)
                    <tr>
                      <td>
                        <div class="flex flex-col gap-0.5">
                          <span class="text-sm font-medium text-foreground tabular-nums">{{ $invoice->issued_at->format('d/m/Y') }}</span>
                          <span class="text-xs text-secondary-foreground tabular-nums">{{ $invoice->issued_at->format('H:i') }}</span>
                        </div>
                      </td>
                      <td>
                        <div class="flex items-center gap-2.5 min-w-0">
                          <div class="flex items-center justify-center size-8 rounded-lg bg-primary/10 shrink-0">
                            <i class="ki-filled ki-shop text-primary text-sm"></i>
                          </div>
                          <span class="text-sm text-foreground truncate max-w-[180px]">{{ $invoice->issuer?->name ?? '—' }}</span>
                        </div>
                      </td>
                      <td class="text-end">
                        <span class="text-sm font-semibold text-foreground tabular-nums">R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</span>
                      </td>
                      <td class="text-end">
                        <a href="{{ route('my-purchases.detail', ['invoice' => $invoice->id]) }}" class="kt-btn kt-btn-icon kt-btn-ghost kt-btn-sm" title="Ver detalhes">
                          <i class="ki-filled ki-eye text-sm"></i>
                        </a>
                      </td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="flex items-center justify-center size-14 rounded-xl bg-secondary/50 mb-4">
                  <i class="ki-filled ki-document text-secondary-foreground text-2xl"></i>
                </div>
                <p class="text-sm font-medium text-foreground mb-1">Nenhuma compra encontrada</p>
                <p class="text-xs text-secondary-foreground mb-4">Importe sua primeira NF-e para começar.</p>
                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm">
                  <i class="ki-filled ki-file-up text-sm"></i>
                  Importar NF-e
                </a>
              </div>
            @endif
          </div>
        </div>

      </div>

      {{-- TAB: Configurações --}}
      <div id="tab_settings" class="hidden">

        @if(session('success'))
          <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 mb-5">
            <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
            <span class="text-sm text-green-600 font-medium">{{ session('success') }}</span>
          </div>
        @endif

        @if(session('success_avatar'))
          <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 mb-5">
            <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
            <span class="text-sm text-green-600 font-medium">{{ session('success_avatar') }}</span>
          </div>
        @endif

        {{-- Card: Foto de Perfil --}}
        <div class="kt-card mb-5 lg:mb-7.5">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Foto de Perfil</h3>
          </div>
          <div class="kt-card-content p-6">
            <form method="POST" action="{{ route('account.avatar') }}" enctype="multipart/form-data" class="kt-form max-w-lg">
              @csrf
              <div class="flex items-center gap-5">
                <div class="rounded-full size-16 shrink-0 flex items-center justify-center bg-primary text-primary-foreground overflow-hidden border border-border" id="avatar_preview_wrapper">
                  @if($user->avatar)
                    <img src="{{ $user->avatar->url() }}" alt="{{ $user->name }}" class="size-full object-cover" id="avatar_preview_img" />
                  @else
                    <span class="text-lg font-bold select-none" id="avatar_preview_initials">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
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
                      placeholder="seu@email.com"
                      autocomplete="email"
                    />
                  </div>
                  @error('email')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
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

        @if(session('success_password'))
          <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3 mb-5">
            <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
            <span class="text-sm text-green-600 font-medium">{{ session('success_password') }}</span>
          </div>
        @endif

        <div class="kt-card">
          <div class="kt-card-header">
            <h3 class="kt-card-title">Alterar Senha</h3>
          </div>
          <div class="kt-card-content p-6">
            <p class="text-xs text-secondary-foreground mb-5">Use uma senha forte com no mínimo 8 caracteres.</p>
            <form method="POST" action="{{ route('account.password') }}" class="kt-form max-w-lg">
              @csrf
              @method('PATCH')
              <div class="space-y-4">
                <div class="kt-form-item">
                  <label class="kt-form-label" for="current_password">Senha atual</label>
                  <div class="kt-form-control">
                    <input
                      type="password"
                      id="current_password"
                      name="current_password"
                      class="kt-input @error('current_password') border-destructive @enderror"
                      placeholder="Digite sua senha atual"
                      autocomplete="current-password"
                    />
                  </div>
                  @error('current_password')
                    <div class="kt-form-message text-destructive">{{ $message }}</div>
                  @enderror
                </div>
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

      </div>

    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        openTab: @if($errors->has('current_password') || $errors->has('password') || session('success_password')) 'security'
                 @elseif($errors->has('name') || $errors->has('email') || $errors->has('avatar') || session('success') || session('success_avatar')) 'settings'
                 @else null
                 @endif,
    });
</script>
@endpush
