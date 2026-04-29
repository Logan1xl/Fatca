<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBC FATCA Compliance Reporter</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
    <style>
        body {
            background: linear-gradient(135deg, #0d3b66 0%, #05192d 100%);
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
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 3rem;
            max-width: 600px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="hero-card animate__animated animate__zoomIn">
        <div class="mb-4">
            <i class="fas fa-shield-halved fa-4x text-info"></i>
        </div>
        <h1 class="fw-bold mb-3">CBC BANK</h1>
        <h3 class="fw-light mb-4 text-info">FATCA Compliance Reporter</h3>
        <p class="opacity-75 mb-5">
            Portail institutionnel pour la conversion, la validation et la sécurisation des rapports FATCA selon la Publication 5124.
        </p>
        
        @if (Route::has('login'))
            <div>
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-info btn-lg px-5 rounded-pill fw-bold shadow">
                        <i class="fas fa-desktop me-2"></i> ACCÉDER AU DASHBOARD
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-info btn-lg px-5 rounded-pill fw-bold shadow">
                        <i class="fas fa-lock me-2"></i> SE CONNECTER
                    </a>
                @endauth
            </div>
        @endif
        
        <div class="mt-5 small opacity-50">
            © {{ date('Y') }} CBC Bank - Direction de la Conformité
        </div>
    </div>
</body>
</html>
