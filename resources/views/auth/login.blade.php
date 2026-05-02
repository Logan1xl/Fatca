<x-guest-layout>
    <div class="auth-header text-center">
        <img src="{{ asset('images/logo.jpg') }}" alt="CBC Logo" class="mb-3 rounded shadow-sm" style="max-height: 80px;">
        <h4 class="fw-bold text-dark mb-1">CBC BANK</h4>
        <p class="text-muted small mb-0">Compliance FATCA Portal</p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Session Status -->
        @if(session('status'))
            <div class="alert alert-info border-0 small mb-4">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Address -->
            <div class="mb-3">
                <label for="email" class="form-label small fw-bold text-muted text-uppercase">Identifiant (Email)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                    <input id="email" type="email" name="email" class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                @error('email')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <!-- Password -->
            <div class="mb-3">
                <div class="d-flex justify-content-between">
                    <label for="password" class="form-label small fw-bold text-muted text-uppercase">Mot de passe</label>
                    @if (Route::has('password.request'))
                        <a class="small text-decoration-none" href="{{ route('password.request') }}">
                            Oublié ?
                        </a>
                    @endif
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                    <input id="password" type="password" name="password" class="form-control border-start-0 ps-0 @error('password') is-invalid @enderror" required autocomplete="current-password">
                </div>
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                    <label class="form-check-label small text-muted" for="remember_me">
                        Rester connecté
                    </label>
                </div>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary py-3 fw-bold rounded-3 shadow-sm">
                    CONNEXION SÉCURISÉE <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>
        </form>
    </div>
    
    <div class="p-4 bg-light text-center border-top">
        <small class="text-muted">Usage réservé au personnel de la CBC.</small>
    </div>
</x-guest-layout>
