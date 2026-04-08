@extends('layout.main')

@section('content')    
    <div class="kt-container-fixed" id="contentContainer"></div>
    
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Emissores</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    &nbsp;
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('issuers.detail') }}" class="kt-btn kt-btn-primary">Novo</a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">
            <div class="kt-card kt-card-grid min-w-full">
                <div class="kt-card-header flex-wrap gap-2">
                    <h3 class="kt-card-title text-sm">Mostrando {{ $records->perPage() }} dos {{ $records->total() }} registros</h3>
                    <div class="flex flex-wrap gap-2 lg:gap-5">
                        <div class="flex">
                            <label class="kt-input">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="text" placeholder="Buscar" />
                            </label>
                        </div>
                    </div>
                </div>
                <div class="kt-card-content">
                    <div class="grid">
                        <div class="kt-scrollable-x-auto">
                            <table class="kt-table table-auto kt-table-border">
                                <thead>
                                    <tr>                                        
                                        <th class="min-w-[200px]">Documento</th>
                                        <th class="min-w-[165px]">Razão Social</th>
                                        <th class="min-w-[165px]">CEP</th>
                                        <th class="min-w-[165px]">Cidade</th>
                                        <th class="min-w-[225px]">Estado</th>
                                        <th class="w-[60px]"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($records->items() as $item)
                                        <tr>
                                            <td>{{ $item->cnpj }}</td>
                                            <td class="font-normal text-foreground">{{ $item->name }}</td>
                                            <td class="font-normal text-foreground">{{ $item->zip_code }}</td>
                                            <td class="font-normal text-foreground">{{ $item->city }}</td>
                                            <td class="font-normal text-foreground">{{ $item->state }}</td>                                            
                                            <td>
                                                <a href="{{ route('issuers.detail', ['id' => $item->id]) }}">Editar</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-5 text-secondary-foreground text-sm font-medium">
                            <div class="flex items-center gap-4 order-1 md:order-2">
                                {{ $records->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>            
        </div>
    </div>
@endsection