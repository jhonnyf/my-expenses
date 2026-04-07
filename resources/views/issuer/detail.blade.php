@extends('layout.main')

@section('content')    
    <div class="kt-container-fixed" id="contentContainer"></div>
    
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">
                    App Roster
                </h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Central Hub for Personal Customization
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a class="kt-btn kt-btn-outline" href="#">
                    Import CSV
                </a>
                <a class="kt-btn kt-btn-primary" href="#">
                    Add Member
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">
            @php
                print_r($record);
            @endphp          
        </div>
    </div>
@endsection