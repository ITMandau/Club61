<x-guest-layout theme="terracotta">
    <div class="mb-4 text-sm text-gray-600">
        {{ __('account.confirm.intro') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('account.confirm.password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                {{ __('account.confirm.submit') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
