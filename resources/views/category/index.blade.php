@extends('layout.main')
@section('page-module', 'category')

@section('content')

    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Categorias</h1>
                <p class="text-sm font-normal text-secondary-foreground">Organize e gerencie as categorias dos seus produtos</p>
            </div>
            <div class="flex items-center gap-2.5">
                <button data-action="auto-categorize" id="btnAuto" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-setting-2"></i> Auto-categorizar
                </button>
                <button data-action="show-new-form" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-plus"></i> Nova Categoria
                </button>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- STAT CARDS --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                        <i class="ki-filled ki-category text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ $categories->count() }}</span>
                        <span class="text-xs font-normal text-secondary-foreground">Categorias</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-success/10 shrink-0">
                        <i class="ki-filled ki-dollar text-success text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($totalSpent, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">Total Categorizado</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-info/10 shrink-0">
                        <i class="ki-filled ki-medal-star text-info text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono truncate">
                            {{ $topCategory->name ?? '—' }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">Maior Gasto</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-warning/10 shrink-0">
                        <i class="ki-filled ki-question text-warning text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ number_format($uncategorizedCount) }}</span>
                        <span class="text-xs font-normal text-secondary-foreground">Itens Sem Categoria</span>
                    </div>
                </div>

            </div>

            <div class="kt-card hidden" id="newCategoryForm">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Nova Categoria</h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="grid lg:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Nome</label>
                            <input type="text" id="newName" class="kt-input w-full" placeholder="Nome da categoria" />
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Cor</label>
                            <input type="color" id="newColor" class="w-full h-9 rounded border border-border cursor-pointer" value="#3B82F6" />
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Palavras-chave</label>
                            <input type="text" id="newKeywords" class="kt-input w-full" placeholder="PALAVRA1, PALAVRA2" />
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button data-action="save-category" class="kt-btn kt-btn-primary kt-btn-sm">Salvar</button>
                        <button data-action="hide-new-form" class="kt-btn kt-btn-outline kt-btn-sm">Cancelar</button>
                    </div>
                </div>
            </div>

            <div class="kt-card hidden" id="editCategoryForm">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Editar Categoria</h3>
                </div>
                <div class="kt-card-content pb-5">
                    <input type="hidden" id="editId" />
                    <div class="grid lg:grid-cols-3 gap-4">
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Nome</label>
                            <input type="text" id="editName" class="kt-input w-full" />
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Cor</label>
                            <input type="color" id="editColor" class="w-full h-9 rounded border border-border cursor-pointer" />
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground mb-1 block">Palavras-chave</label>
                            <input type="text" id="editKeywords" class="kt-input w-full" />
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button data-action="update-category" class="kt-btn kt-btn-primary kt-btn-sm">Atualizar</button>
                        <button data-action="hide-edit-form" class="kt-btn kt-btn-outline kt-btn-sm">Cancelar</button>
                    </div>
                </div>
            </div>

            @if($categories->isNotEmpty())
                @php $categoriesTotal = $totalSpent ?: 1; @endphp
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" id="categoriesGrid">
                    @foreach($categories as $category)
                        @php $share = ($category->total_spent / $categoriesTotal) * 100; @endphp
                        <div class="kt-card transition-shadow hover:shadow-md"
                             style="box-shadow: inset 0 3px 0 0 {{ $category->color ?? '#94A3B8' }}"
                             id="category-{{ $category->id }}">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title gap-2">
                                    <span class="size-3 rounded-full shrink-0" data-color-dot style="background-color: {{ $category->color ?? '#94A3B8' }}"></span>
                                    <span data-category-name>{{ $category->name }}</span>
                                </h3>
                                @if($category->user_id)
                                    <div class="kt-card-toolbar gap-1">
                                        <button data-action="edit-category"
                                                data-category-id="{{ $category->id }}"
                                                data-category-name="{{ $category->name }}"
                                                data-category-color="{{ $category->color ?? '#94A3B8' }}"
                                                data-category-keywords="{{ implode(', ', $category->keywords ?? []) }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm transition-transform hover:scale-110" title="Editar">
                                            <i class="ki-filled ki-pencil text-muted-foreground"></i>
                                        </button>
                                        <button data-action="delete-category" data-category-id="{{ $category->id }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm transition-transform hover:scale-110" title="Excluir">
                                            <i class="ki-filled ki-trash text-muted-foreground"></i>
                                        </button>
                                    </div>
                                @endif
                            </div>
                            <div class="kt-card-content pb-5">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-sm text-secondary-foreground">Itens</span>
                                        <span class="text-sm font-medium text-foreground">{{ $category->items_count }}</span>
                                    </div>
                                    <div class="flex justify-between items-baseline">
                                        <span class="text-sm text-secondary-foreground">Total gasto</span>
                                        <span class="text-sm font-semibold font-mono text-primary tabular-nums">R$ {{ number_format($category->total_spent, 2, ',', '.') }}</span>
                                    </div>
                                    @if($category->total_spent > 0)
                                        <div>
                                            <div class="kt-progress h-1">
                                                <div class="kt-progress-indicator" style="width: {{ min($share, 100) }}%; background-color: {{ $category->color ?? '#94A3B8' }}"></div>
                                            </div>
                                            <p class="text-xs text-secondary-foreground mt-1">{{ number_format($share, 1) }}% do total categorizado</p>
                                        </div>
                                    @endif
                                    <div data-keywords-section>
                                        @if($category->keywords && count($category->keywords) > 0)
                                            <div>
                                                <p class="text-xs text-secondary-foreground mb-1.5">Palavras-chave</p>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach(array_slice($category->keywords, 0, 8) as $kw)
                                                        <span class="text-xs bg-accent px-1.5 py-0.5 rounded">{{ $kw }}</span>
                                                    @endforeach
                                                    @if(count($category->keywords) > 8)
                                                        <span class="text-xs text-secondary-foreground">+{{ count($category->keywords) - 8 }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    @if(!$category->user_id)
                                        <div>
                                            <span class="kt-badge kt-badge-secondary kt-badge-sm">Sistema</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="kt-card" id="categoriesGrid">
                    <div class="kt-card-content">
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <i class="ki-filled ki-category text-5xl text-secondary-foreground/30 mb-4"></i>
                            <p class="text-sm font-medium text-foreground mb-1">Nenhuma categoria encontrada.</p>
                            <p class="text-sm text-secondary-foreground">Crie uma nova categoria usando o botão acima.</p>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        baseUrl: '{{ url("categories") }}',
    });
</script>
@endpush
