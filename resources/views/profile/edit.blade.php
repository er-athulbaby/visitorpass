<x-sidebar-layout>
    <x-page-header :title="__('Profile')" :subtitle="__('Your name, email address and password.')" />

    <div class="grid max-w-3xl gap-6">
        <div class="card p-5 sm:p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card p-5 sm:p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card border-error/20 p-5 sm:p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-sidebar-layout>
