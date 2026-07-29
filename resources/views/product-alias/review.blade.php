@extends('layout.main')
@section('page-module', 'product-alias')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Revisar Nomes de Produto</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Varre todos os produtos já importados e sugere quais parecem ser o mesmo item comprado em lojas diferentes
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- SUGESTÕES DA COMUNIDADE --}}
            <div class="kt-card" id="communitySuggestionsCard" style="display:none;">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-people text-primary me-1"></i> Sugestões da Comunidade
                    </h3>
                    <span id="communitySuggestionsCount" class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm"></span>
                </div>
                <div class="kt-card-content pb-2">
                    <p class="text-xs text-secondary-foreground mb-2">
                        Produtos que outros usuários do app já nomearam com a mesma descrição. Gratuito e instantâneo.
                    </p>
                    <div id="communitySuggestionsList" class="divide-y divide-border"></div>
                </div>
            </div>

            {{-- SUGESTÕES DE UNIFICAÇÃO --}}
            <div class="kt-card" id="suggestionsCard" data-show-empty-state="true" style="display:none;">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-abstract-26 text-primary me-1"></i> Sugestões de Unificação
                    </h3>
                    <span id="suggestionsCount" class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm"></span>
                </div>
                <div class="kt-card-content pb-2">
                    <p class="text-xs text-secondary-foreground mb-2">
                        Ajuste o nome e unifique, ou ignore se forem produtos diferentes. Nada é unificado automaticamente.
                    </p>
                    <div id="suggestionsList" class="divide-y divide-border"></div>
                </div>
            </div>

        </div>
    </div>

    @include('product-alias._alias-modal')

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        productAliasStoreUrl: '{{ route("product-aliases.store") }}',
        productAliasMergeUrl: '{{ route("product-aliases.merge") }}',
        productAliasDismissUrl: '{{ route("product-aliases.dismiss") }}',
        productAliasSuggestionsUrl: '{{ route("product-aliases.suggestions-all") }}',
        productAliasAiSuggestUrl: '{{ route("product-aliases.ai-suggest-name") }}',
        productAliasCommunitySuggestionsUrl: '{{ route("product-aliases.community-suggestions") }}',
    });
</script>
@endpush
