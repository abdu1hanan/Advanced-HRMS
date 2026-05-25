<x-app-layout>
    <x-slot name="header">Leave Settings</x-slot>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Global Defaults ({{ $year }})</div>
                <ul class="list-group list-group-flush">
                    @foreach($leaveTypes as $type)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $type->name }}</span>
                            <span class="badge bg-primary">{{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }} days</span>
                        </li>
                    @endforeach
                </ul>
                <div class="card-body">
                    <p class="small text-muted">Edit defaults per type on the Leave Types page.</p>
                    <form method="POST" action="{{ route('super-admin.leave-settings.apply-all') }}">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <button class="btn btn-warning" onclick="return confirm('Reset all employee balances for {{ $year }} to global defaults?')">Apply Defaults to All Employees</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5>Per-Employee Overrides</h5>
                    <p class="text-muted">Set custom leave entitlements for individual employees.</p>
                    <a href="{{ route('super-admin.leave-settings.overrides') }}" class="btn btn-outline-primary">Manage Overrides</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
