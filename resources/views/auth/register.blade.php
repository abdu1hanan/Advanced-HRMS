<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label small text-white opacity-75">Full Name</label>
            <input type="text" class="form-control bg-dark border-secondary text-white @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="email" class="form-label small text-white opacity-75">Email</label>
            <input type="email" class="form-control bg-dark border-secondary text-white @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="department_id" class="form-label small text-white opacity-75">Department</label>
            <select class="form-select bg-dark border-secondary text-white @error('department_id') is-invalid @enderror" id="department_id" name="department_id" required>
                <option value="">Select department</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label small text-white opacity-75">Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white @error('password') is-invalid @enderror" id="password" name="password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label small text-white opacity-75">Confirm Password</label>
            <input type="password" class="form-control bg-dark border-secondary text-white" id="password_confirmation" name="password_confirmation" required>
        </div>
        <div class="mt-4 pt-2 border-top border-secondary border-opacity-25">
            <button type="submit" class="row-btn-blue w-100 justify-content-center" style="height: 40px !important;">Register</button>
        </div>
        <p class="text-center mt-3 mb-0 small">
            <a href="{{ route('login') }}" class="text-white opacity-50 text-decoration-none">Already registered? Log in</a>
        </p>
    </form>
</x-guest-layout>
