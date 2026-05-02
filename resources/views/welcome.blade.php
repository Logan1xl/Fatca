<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBC FATCA Compliance Reporter</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-family: 'Inter', sans-serif;
        }
        .hero-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 24px;
            padding: 4rem 3rem;
            max-width: 600px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="hero-card animate__animated animate__zoomIn">
        <div class="mb-4">
            <img src="{{ asset('images/logo.jpg') }}" alt="CBC Logo" class="img-fluid rounded mb-4" style="max-height: 100px;">
        </div>
        <h1 class="fw-bold mb-2">CBC BANK</h1>
        <h4 class="fw-light mb-4" style="color: #d4af37; letter-spacing: 2px;">FATCA COMPLIANCE REPORTER</h4>
        <p class="opacity-75 mb-5">
            Plateforme institutionnelle sécurisée dédiée à la conversion, la validation et la conformité des rapports FATCA (IRS Publication 5124).
        </p>
        
        @if (Route::has('login'))
            <div class="d-grid gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-lg px-5 rounded-pill fw-bold shadow" style="background: #d4af37; color: #1a1a1a;">
                        <i class="fas fa-desktop me-2"></i> ACCÉDER AU SYSTÈME
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-lg px-5 rounded-pill fw-bold shadow" style="background: #d4af37; color: #1a1a1a;">
                        <i class="fas fa-sign-in-alt me-2"></i> CONNEXION SÉCURISÉE
                    </a>
                @endauth
            </div>
        @endif
        
        <div class="mt-5 small opacity-50">
            © {{ date('Y') }} CBC Bank - Direction de la Conformité & Risques
        </div>
    </div>
</body>
</html>
