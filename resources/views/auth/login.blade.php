<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo width="200pt" height="200pt" class="fill-current text-gray-500" />
            </a>
        </x-slot>

        <!-- Session Status -->
        <!-- To be used for ex. with, in your controller, return redirect('home')->with("success", "Your message"); -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="username" :value="__('Username')" />

                <input id="username" placeholder="{{__('Your username')}}"
                         class="block mt-1 w-full input input-bordered input-primary text-base-content" type="email" name="username" value="{{old('username')}}" required autofocus />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-label for="password" :value="__('Password')" />

                <input id="password" class="block mt-1 w-full input input-bordered input-secondary text-base-content"
                                type="password"
                                name="password"
                         placeholder="{{__('Your password')}}"
                                required autocomplete="current-password" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" class="checkbox" name="remember">
                    <span class="ml-2 text-sm text-base-content">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <button class="btn">
                    {{ __('Log in') }}
                </button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
