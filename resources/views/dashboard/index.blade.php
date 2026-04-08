@extends('layout.main')

@section('content')

<style>
    .channel-stats-bg {
        background-image: url('assets/media/images/2600x1600/bg-3.png');
    }

    .dark .channel-stats-bg {
        background-image: url('assets/media/images/2600x1600/bg-3-dark.png');
    }

    .entry-callout-bg {
        background-image: url('assets/media/images/2600x1600/2.png');
    }

    .dark .entry-callout-bg {
        background-image: url('assets/media/images/2600x1600/2-dark.png');
    }
</style>

<div class="kt-container-fixed" id="contentContainer"></div>

<div class="kt-container-fixed">
    <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
        <div class="flex flex-col justify-center gap-2">
            <h1 class="text-xl font-medium leading-none text-mono">Dashboard</h1>
            <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                Central Hub for Personal Customization
            </div>
        </div>
    </div>
</div>

<div class="kt-container-fixed">
    <div class="grid gap-5 lg:gap-7.5">
        <!-- begin: grid -->
        <div class="grid lg:grid-cols-3 gap-y-5 lg:gap-7.5 items-stretch">
            <div class="lg:col-span-1">
                <div class="grid grid-cols-2 gap-5 lg:gap-7.5 h-full items-stretch">

                    <div class="kt-card flex-col justify-between gap-6 h-full bg-cover rtl:bg-[left_top_-1.7rem] bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <img alt="" class="w-7 mt-4 ms-5" src="{{ asset('assets/media/brand-logos/linkedin-2.svg') }}" />
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-3xl font-semibold text-mono">
                                R$ {{ number_format($totalExpenses, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">
                                Total de gastos
                            </span>
                        </div>
                    </div>
                    <div class="kt-card flex-col justify-between gap-6 h-full bg-cover rtl:bg-[left_top_-1.7rem] bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <img alt="" class="w-7 mt-4 ms-5" src="{{ asset('assets/media/brand-logos/youtube-2.svg') }}" />
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-3xl font-semibold text-mono">
                                R$ {{ number_format($totalTaxes, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">
                                Total de impostos
                            </span>
                        </div>
                    </div>
                    <div class="kt-card flex-col justify-between gap-6 h-full bg-cover rtl:bg-[left_top_-1.7rem] bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <img alt="" class="w-7 mt-4 ms-5" src="{{ asset('assets/media/brand-logos/instagram-03.svg') }}" />
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-3xl font-semibold text-mono">
                                {{ $totalPurchases }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">
                                Total de compras
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-2">
                
            </div>
        </div>
        <!-- end: grid -->
    </div>
</div>
@endsection