@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div>
                <h1 class="text-xl font-medium leading-none text-mono">Importar NFC-e</h1>
                <p class="text-sm text-secondary-foreground mt-1">Selecione um arquivo XML para importar a nota fiscal.</p>
            </div>
            <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-outline">
                <i class="ki-filled ki-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="kt-card max-w-lg">
            <div class="kt-card-content pt-6">
                <form action="{{ route('my-purchases.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="xml" class="block text-sm font-medium mb-2">Arquivo XML da NFC-e</label>
                        <input type="file" id="xml" name="xml" accept=".xml,text/xml" class="kt-input w-full" required>
                        @error('xml')
                            <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full">
                        <i class="ki-filled ki-cloud-add"></i> Importar
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
