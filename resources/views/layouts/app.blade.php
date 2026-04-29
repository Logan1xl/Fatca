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
        <div class="container-fluid p-0">
            <div class="row g-0">
                <!-- Sidebar -->
                <nav class="col-md-3 col-lg-2 sidebar collapse d-md-block">
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
                        <div class="mt-auto p-3">
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
                <main class="col-md-9 col-lg-10 ms-sm-auto px-0">
                    <nav class="navbar navbar-main px-4 py-3 sticky-top">
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
                </main>
            </div>
        </div>
        @yield('scripts')
    </body>
</html>
