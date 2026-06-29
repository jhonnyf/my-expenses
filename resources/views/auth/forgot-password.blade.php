@extends('layout.main-login')

@section('content')

<div class="grid lg:grid-cols-2 grow">
    <div class="flex justify-center items-center p-8 lg:p-10 order-2 lg:order-1">
        <div class="kt-card max-w-[370px] w-full">
            <div class="kt-card-content flex flex-col gap-5 p-10">
                <div class="text-center mb-2.5">
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Forgot Password?</h3>
                    <div class="flex items-center justify-center font-medium">
                        <span class="text-sm text-secondary-foreground me-1.5">Remembered it?</span>
                        <a class="text-sm link" href="{{ route('login.index') }}">Sign in</a>
                    </div>
                </div>

                @if (session('status'))
                    <div class="kt-alert kt-alert-success flex items-center gap-2 p-3 rounded-md bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800">
                        <i class="ki-filled ki-shield-tick text-green-600 dark:text-green-400 text-base"></i>
                        <span class="text-sm text-green-700 dark:text-green-300">{{ session('status') }}</span>
                    </div>
                @endif

                <form action="{{ route('password.email') }}" class="flex flex-col gap-5" method="post">
                    @csrf
                    <p class="text-sm text-secondary-foreground text-center">
                        Enter your email address and we'll send you a link to reset your password.
                    </p>
                    <div class="flex flex-col gap-1">
                        <label class="kt-form-label font-normal text-mono">Email</label>
                        <input type="email" name="email" class="kt-input @error('email') border-destructive @enderror" placeholder="email@email.com" value="{{ old('email') }}" />
                        @error('email')
                            <span class="text-xs text-destructive">{{ $message }}</span>
                        @enderror
                    </div>
                    <button class="kt-btn kt-btn-primary flex justify-center grow">Send Reset Link</button>
                </form>
            </div>
        </div>
    </div>
    <div class="lg:rounded-xl lg:border lg:border-border lg:m-5 order-1 lg:order-2 bg-top xxl:bg-center xl:bg-cover bg-no-repeat branded-bg">
        <div class="flex flex-col p-8 lg:p-16 gap-4">
            <a href="{{ route('login.index') }}">
                <img class="h-[28px] max-w-none" src="assets/media/app/mini-logo.svg" />
            </a>
            <div class="flex flex-col gap-3">
                <h3 class="text-2xl font-semibold text-mono">Password Recovery</h3>
                <div class="text-base font-medium text-secondary-foreground">
                    No worries — enter your e-mail and we'll <br />
                    send you a
                    <span class="text-mono font-semibold">secure reset link</span>
                    to get <br />
                    back into your account.
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
