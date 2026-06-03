<x-app-layout>
    <x-slot name="header">Profile</x-slot>

    <div class="row g-4 align-items-stretch" style="margin-bottom: 2rem;">
        <div class="col-md-6">
            <div class="spotify-card h-100 mb-0">
                <div class="card-header-area px-4 pt-4">
                    <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Profile Information</h6>
                    <div class="grid-separator"></div>
                </div>
                <div class="px-4 pb-4 pt-2 h-100">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="spotify-card h-100 mb-0">
                <div class="card-header-area px-4 pt-4">
                    <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Update Password</h6>
                    <div class="grid-separator"></div>
                </div>
                <div class="px-4 pb-4 pt-2 h-100">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        @if(!auth()->user()->isSuperAdmin())
            <div class="col-12">
                <div class="spotify-card border border-danger border-opacity-25 mb-4">
                    <div class="card-header-area px-4 pt-4">
                        <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Delete Account</h6>
                        <div class="grid-separator border-danger border-opacity-25"></div>
                    </div>
                    <div class="px-4 pb-4 pt-2">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
