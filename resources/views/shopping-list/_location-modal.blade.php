<div class="kt-modal" data-kt-modal="true" id="locationModal">
    <div class="kt-modal-content max-w-[420px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Comparar preços de outra cidade</h3>
            <button class="kt-modal-close" data-kt-modal-dismiss="#locationModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-secondary-foreground">
                Escolha uma cidade/estado com produtos cadastrados para comparar os preços,
                sem limite de distância.
            </p>
            <div class="kt-form-item">
                <label class="kt-form-label" for="locationModalCitySelect">Cidade/Estado</label>
                <div class="kt-form-control">
                    <select id="locationModalCitySelect" class="kt-select w-full" data-kt-select="true"
                            data-kt-select-dropdown-strategy="fixed" data-kt-select-placeholder="Selecione...">
                        <option value="">Selecione...</option>
                        @foreach($cities as $c)
                            <option value="{{ $c->city }}|{{ $c->state }}">{{ $c->city }}/{{ $c->state }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="kt-modal-footer">
            <button class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#locationModal">Cancelar</button>
            <button class="kt-btn kt-btn-primary" id="btnApplyLocationOverride" data-kt-modal-dismiss="#locationModal">
                Aplicar
            </button>
        </div>
    </div>
</div>
