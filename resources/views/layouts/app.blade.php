<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title') - {{ config('app.name', 'CBC FATCA Compliance') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <div class="d-flex min-vh-100">
            <!-- Sidebar -->
            <nav class="sidebar d-none d-md-block flex-shrink-0" style="width: 280px;">
                    <div class="sidebar-header p-4 text-center">
                        <h4 class="fw-bold mb-0">CBC BANK</h4>
                        <small class="opacity-75">FATCA Compliance</small>
                    </div>
                    
                    <div class="nav flex-column mt-3">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="fas fa-file-invoice"></i> Rapports FATCA
                        </a>
                        <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                            <i class="fas fa-history"></i> Audit Logs
                        </a>
                        <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                            <i class="fas fa-shield-halved"></i> Paramètres
                        </a>
                        <div class="p-3">
                            <hr class="bg-light opacity-25">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </button>
                            </form>
                        </div>
                    </div>
                </nav>

                <!-- Main Content -->
                <main class="flex-grow-1 d-flex flex-column" style="min-width: 0;">
                    <nav class="navbar navbar-main px-4 py-3 sticky-top bg-white">
                        <div class="container-fluid">
                            <h5 class="mb-0 fw-bold">@yield('page_title', 'Dashboard')</h5>
                            <div class="d-flex align-items-center">
                                <span class="me-3 text-muted">
                                    <i class="fas fa-user-circle me-1"></i> {{ Auth::user()->name ?? 'Administrateur' }}
                                </span>
                            </div>
                        </div>
                    </nav>

                    <div class="p-4">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                                <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        @yield('content')
                    </div>

                    <footer class="mt-auto py-4 px-4 border-top bg-white text-center text-muted small">
                        <div class="container-fluid">
                            <p class="mb-0">&copy; {{ date('Y') }} CBC BANK - Département Conformité FATCA. Tous droits réservés.</p>
                            <div class="mt-1">
                                <span class="badge bg-light text-dark border">Version 1.2.0</span>
                                <span class="badge bg-light text-dark border ms-1">P5124 Compliant</span>
                            </div>
                        </div>
                    </footer>
                </main>
        </div>
        @yield('scripts')
    </body>
</html>
