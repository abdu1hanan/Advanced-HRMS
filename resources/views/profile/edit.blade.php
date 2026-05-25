<x-app-layout>
    <x-slot name="header">Profile</x-slot>

    <div class="row g-4 mx-auto" style="max-width: 1200px;">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        @if(!auth()->user()->isSuperAdmin())
            <div class="col-12">
                <div class="card shadow-sm border-0 border-danger">
                    <div class="card-body p-4">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
