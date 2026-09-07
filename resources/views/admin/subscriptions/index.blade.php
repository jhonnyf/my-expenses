@extends('layout.main')
@section('page-module', '')

@section('content')

    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Assinaturas</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    {{ $users->total() }} {{ $users->total() == 1 ? 'usuário' : 'usuários' }}
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            @if(session('status'))
                <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3">
                    <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
                    <span class="text-sm text-green-600 font-medium">{{ session('status') }}</span>
                </div>
            @endif

            <div class="kt-card kt-card-grid min-w-full">

                <div class="kt-card-header flex-wrap gap-3 py-3 lg:py-0">
                    <h3 class="kt-card-title">Usuários</h3>
                    <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="flex items-center gap-3 w-full lg:w-auto">
                        <label class="kt-input w-full lg:max-w-56">
                            <i class="ki-filled ki-magnifier"></i>
                            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por nome ou e-mail..." autocomplete="off" />
                        </label>
                    </form>
                </div>

                <div class="kt-card-table">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table kt-table-border table-auto">
                            <thead>
                                <tr>
                                    <th class="min-w-[220px]">Nome</th>
                                    <th class="min-w-[220px]">E-mail</th>
                                    <th class="min-w-[100px]">Plano</th>
                                    <th class="w-[140px] text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users->items() as $user)
                                    <tr>
                                        <td class="py-2.5 text-sm font-medium text-foreground">{{ $user->name }}</td>
                                        <td class="py-2.5 text-sm text-secondary-foreground">{{ $user->email }}</td>
                                        <td class="py-2.5">
                                            <span class="kt-badge kt-badge-sm {{ $user->isPro() ? 'kt-badge-success' : 'kt-badge-secondary' }}">
                                                {{ $user->isPro() ? 'Pro' : 'Grátis' }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 text-end">
                                            <form method="POST" action="{{ route('admin.subscriptions.update', $user) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="plan" value="{{ $user->isPro() ? 'free' : 'pro' }}" />
                                                <button type="submit" class="kt-btn kt-btn-sm {{ $user->isPro() ? 'kt-btn-outline' : 'kt-btn-primary' }}">
                                                    {{ $user->isPro() ? 'Voltar para Grátis' : 'Tornar Pro' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($users->hasPages())
                    <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-3 text-secondary-foreground text-sm font-medium">
                        <span class="order-2 md:order-1">
                            Exibindo {{ $users->firstItem() }}–{{ $users->lastItem() }} de {{ $users->total() }} usuários
                        </span>
                        <div class="flex items-center gap-2 order-1 md:order-2">
                            {{ $users->links() }}
                        </div>
                    </div>
                @endif

            </div>

        </div>
    </div>

@endsection
