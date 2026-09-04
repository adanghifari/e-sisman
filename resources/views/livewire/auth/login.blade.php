<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Masuk E-SISMAN')" :description="__('Gunakan NIK dan password akun perusahaan Anda')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Employee ID -->
            <flux:input
                name="nik"
                :label="__('NIK')"
                :value="old('nik')"
                type="text"
                required
                autofocus
                autocomplete="username"
                inputmode="numeric"
                placeholder="Masukkan NIK"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Lupa password?') }}
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Masuk') }}
                </flux:button>
            </div>
        </form>

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Akses pengguna dikelola melalui akun perusahaan.') }}
        </p>
    </div>
</x-layouts::auth>
