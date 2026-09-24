<div class="kt-modal" data-kt-modal="true" id="deleteInvoiceModal">
    <div class="kt-modal-content max-w-[440px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Excluir nota fiscal</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#deleteInvoiceModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('my-purchases.destroy', $invoice) }}">
            @csrf
            @method('DELETE')
            <div class="kt-modal-body flex flex-col gap-3">
                <p class="text-sm text-foreground">
                    Excluir a NFC-e nº {{ $invoice->number }} / série {{ $invoice->series }}?
                </p>
                <p class="text-xs text-secondary-foreground">
                    A nota, seus itens e pagamentos saem do seu histórico e dos seus totais. Esta ação não pode ser desfeita;
                    para recuperar os dados, é preciso importar a nota novamente.
                </p>
            </div>
            <div class="kt-modal-footer">
                <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#deleteInvoiceModal">Cancelar</button>
                <button type="submit" class="kt-btn kt-btn-destructive">Excluir nota</button>
            </div>
        </form>
    </div>
</div>
