<x-app-layout>
    <x-slot name="header">Super Admin Dashboard</x-slot>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card text-bg-dark">
                <div class="card-body">
                    <h3>{{ $stats['users'] }}</h3>
                    <p class="mb-0">Active users</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-info">
                <div class="card-body">
                    <h3>{{ $stats['departments'] }}</h3>
                    <p class="mb-0">Departments</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-bg-warning">
                <div class="card-body">
                    <h3>{{ $stats['pending_leaves'] }}</h3>
                    <p class="mb-0">Pending leave requests</p>
                </div>
            </div>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-header">Quick links</div>
        <div class="card-body d-flex flex-wrap gap-2">
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-primary">User Management</a>
            <a href="{{ route('super-admin.departments.index') }}" class="btn btn-outline-primary">Departments</a>
            <a href="{{ route('super-admin.leave-requests.index', ['status' => 'pending']) }}" class="btn btn-outline-warning">Pending leaves</a>
        </div>
    </div>
</x-app-layout>
