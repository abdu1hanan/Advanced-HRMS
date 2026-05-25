<x-app-layout>
    <x-slot name="header">Per-Employee Leave Overrides</x-slot>

    <div class="card shadow-sm mb-4">
        <div class="card-header">Add / update override</div>
        <div class="card-body">
            <form method="POST" action="{{ route('super-admin.leave-settings.overrides.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Employee</label>
                    <select name="user_id" class="form-select" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Leave Type</label>
                    <select name="leave_type_id" class="form-select" required>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <input type="number" name="year" class="form-control" value="{{ $year }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Entitled Days</label>
                    <input type="number" step="0.5" name="entitled" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">Existing overrides ({{ $year }})</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>Employee</th><th>Leave type</th><th>Year</th><th>Entitled</th></tr>
                </thead>
                <tbody>
                    @forelse($overrides as $override)
                        <tr>
                            <td>{{ $override->user->name }}</td>
                            <td>{{ $override->leaveType->name }}</td>
                            <td>{{ $override->year }}</td>
                            <td>{{ $override->entitled }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No overrides for this year.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $overrides->links() }}</div>
    </div>

    <a href="{{ route('super-admin.leave-settings.index') }}" class="btn btn-link">← Back to Leave Settings</a>
</x-app-layout>
