<div class="kt-card">
    <div class="kt-card-content py-3.5 px-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 text-sm" id="dashboardLocationDefault">
                @if($profileCity)
                    <i class="ki-filled ki-geolocation text-primary"></i>
                    <span class="text-secondary-foreground">
                        Sua localização: <span class="font-semibold text-foreground">{{ $profileCity }}/{{ $profileState }}</span>
                    </span>
                @else
                    <i class="ki-filled ki-information-2 text-primary"></i>
                    <span class="text-secondary-foreground">
                        Compartilhe sua localização pra ver produtos perto de você e alertas de queda de preço.
                    </span>
                @endif
            </div>
            <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-action="use-my-location">
                <i class="ki-filled ki-geolocation"></i>
                Usar minha localização
            </button>
        </div>
    </div>
</div>
