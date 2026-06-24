@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Categorias</h1>
                <p class="text-sm text-secondary-foreground">Gerencie as categorias dos seus produtos</p>
            </div>
            <div class="flex items-center gap-2.5">
                <button onclick="autoCategorize()" class="kt-btn kt-btn-outline" id="btnAuto">
                    <i class="ki-filled ki-setting-2"></i> Auto-categorizar
                </button>
                <button onclick="showNewForm()" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-plus"></i> Nova Categoria
                </button>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Formulário nova categoria --}}
        <div class="kt-card" id="newCategoryForm" style="display:none;">
            <div class="kt-card-header">
                <h3 class="kt-card-title">Nova Categoria</h3>
            </div>
            <div class="kt-card-content pb-5">
                <div class="grid lg:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs text-secondary-foreground">Nome</label>
                        <input type="text" id="newName" class="kt-input mt-1 w-full" placeholder="Nome da categoria" />
                    </div>
                    <div>
                        <label class="text-xs text-secondary-foreground">Cor</label>
                        <input type="color" id="newColor" class="mt-1 w-full h-9 rounded border border-border cursor-pointer" value="#3B82F6" />
                    </div>
                    <div>
                        <label class="text-xs text-secondary-foreground">Keywords (separadas por vírgula)</label>
                        <input type="text" id="newKeywords" class="kt-input mt-1 w-full" placeholder="PALAVRA1, PALAVRA2, ..." />
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button onclick="saveCategory()" class="kt-btn kt-btn-sm kt-btn-primary">Salvar</button>
                    <button onclick="hideNewForm()" class="kt-btn kt-btn-sm kt-btn-outline">Cancelar</button>
                </div>
            </div>
        </div>

        {{-- Formulário editar categoria --}}
        <div class="kt-card" id="editCategoryForm" style="display:none;">
            <div class="kt-card-header">
                <h3 class="kt-card-title">Editar Categoria</h3>
            </div>
            <div class="kt-card-content pb-5">
                <input type="hidden" id="editId" />
                <div class="grid lg:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs text-secondary-foreground">Nome</label>
                        <input type="text" id="editName" class="kt-input mt-1 w-full" />
                    </div>
                    <div>
                        <label class="text-xs text-secondary-foreground">Cor</label>
                        <input type="color" id="editColor" class="mt-1 w-full h-9 rounded border border-border cursor-pointer" />
                    </div>
                    <div>
                        <label class="text-xs text-secondary-foreground">Keywords (separadas por vírgula)</label>
                        <input type="text" id="editKeywords" class="kt-input mt-1 w-full" />
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button onclick="updateCategory()" class="kt-btn kt-btn-sm kt-btn-primary">Atualizar</button>
                    <button onclick="hideEditForm()" class="kt-btn kt-btn-sm kt-btn-outline">Cancelar</button>
                </div>
            </div>
        </div>

        {{-- Grid de categorias --}}
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" id="categoriesGrid">
            @foreach($categories as $category)
                <div class="kt-card" id="category-{{ $category->id }}">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title flex items-center gap-2">
                            <span class="size-3 rounded-full shrink-0" style="background-color: {{ $category->color ?? '#94A3B8' }}"></span>
                            {{ $category->name }}
                        </h3>
                        <div class="flex items-center gap-2">
                            @if($category->user_id)
                                <button onclick="editCategory({{ $category->id }}, '{{ addslashes($category->name) }}', '{{ $category->color }}', '{{ implode(', ', $category->keywords ?? []) }}')"
                                        class="text-muted-foreground hover:text-primary transition-colors">
                                    <i class="ki-filled ki-pencil text-sm"></i>
                                </button>
                                <button onclick="deleteCategory({{ $category->id }})"
                                        class="text-muted-foreground hover:text-destructive transition-colors">
                                    <i class="ki-filled ki-trash text-sm"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="kt-card-content pb-5">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-secondary-foreground">Itens</span>
                                <span class="font-medium">{{ $category->items_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-secondary-foreground">Total gasto</span>
                                <span class="font-semibold font-mono text-primary">R$ {{ number_format($category->total_spent, 2, ',', '.') }}</span>
                            </div>
                            @if($category->keywords && count($category->keywords) > 0)
                                <div>
                                    <p class="text-xs text-secondary-foreground mb-1">Keywords</p>
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
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>window.pageConfig = { baseUrl: '{{ url("categories") }}', csrfToken: '{{ csrf_token() }}' };</script>
    @push('scripts') @vite('resources/js/pages/category.js') @endpush
@endsection
