@extends('layouts.app')

@section('title', 'Analyse du Rapport')
@section('page_title', 'Détails de l\'Analyse Compliance')

@section('content')
<div class="row g-4 mb-4 animate__animated animate__fadeIn">
    <!-- Analysis Summary Card -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 1.5rem;">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-md-4 bg-primary bg-gradient text-white p-4 d-flex flex-column justify-content-center align-items-center">
                        <div class="compliance-gauge position-relative" style="height: 140px; width: 140px;">
                            <canvas id="complianceGauge"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle mt-2 text-center">
                                <h2 class="fw-bold mb-0">{{ $report->compliance_rate }}%</h2>
                                <small class="text-white-50 d-block" style="font-size: 0.6rem; letter-spacing: 1px;">SCORE</small>
                            </div>
                        </div>
                        <div class="mt-3 text-white-50 small text-uppercase fw-bold">Conformité Globale</div>
                    </div>
                    <div class="col-md-8 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h4 class="fw-bold text-dark mb-1">{{ $report->original_filename }}</h4>
                                <div class="d-flex align-items-center text-muted small">
                                    <span class="badge bg-light text-dark border me-2"><i class="fas fa-calendar me-1 text-primary"></i> {{ $report->reporting_period ? $report->reporting_period->format('Y') : '2024' }}</span>
                                    <span class="badge bg-light text-dark border"><i class="fas fa-clock me-1 text-primary"></i> {{ $report->created_at->format('d M H:i') }}</span>
                                </div>
                            </div>
                            <div class="scale-up animate__animated animate__pulse animate__infinite">
                                {!! $report->status_badge !!}
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="p-3 rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-10 text-center h-100">
                                    <h4 class="fw-bold mb-0 text-danger">{{ $report->total_errors }}</h4>
                                    <small class="text-danger small fw-medium">Critiques</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-10 text-center h-100">
                                    <h4 class="fw-bold mb-0 text-warning">{{ $report->total_warnings }}</h4>
                                    <small class="text-warning small fw-medium">Alertes</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-10 text-center h-100">
                                    <h4 class="fw-bold mb-0 text-primary">{{ $report->total_records }}</h4>
                                    <small class="text-primary small fw-medium">Lignes</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-lg h-100 overflow-hidden" style="border-radius: 1.5rem;">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h6 class="fw-bold mb-0 d-flex align-items-center">
                    <span class="p-2 bg-primary bg-opacity-10 rounded-3 me-2">
                        <i class="fas fa-bolt text-primary"></i>
                    </span>
                    Actions de Remédiation
                </h6>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="d-grid gap-3">
                    @if($report->total_errors > 0)
                        <a href="{{ route('reports.download_corrected', $report->id) }}" class="btn btn-primary py-3 rounded-4 shadow-lg border-0 d-flex align-items-center justify-content-center hover-lift">
                            <i class="fas fa-magic me-3 fs-5"></i>
                            <div class="text-start">
                                <div class="fw-bold">Corriger le Fichier</div>
                                <div class="small opacity-75">Auto-correction intelligente</div>
                            </div>
                        </a>
                    @else
                        <a href="{{ route('reports.download_xml', $report->id) }}" class="btn btn-success py-3 rounded-4 shadow-lg border-0 text-white d-flex align-items-center justify-content-center hover-lift">
                            <i class="fas fa-file-export me-3 fs-5 text-white"></i>
                            <div class="text-start">
                                <div class="fw-bold text-white">Télécharger XML</div>
                                <div class="small opacity-75 text-white">Prêt pour soumission IRS</div>
                            </div>
                        </a>
                    @endif

                    <a href="{{ route('reports.download_pdf', $report->id) }}" class="btn btn-light py-3 rounded-4 border-0 d-flex align-items-center justify-content-center hover-lift">
                        <i class="fas fa-file-pdf me-3 fs-5 text-danger"></i>
                        <div class="text-start">
                            <div class="fw-bold">Rapport Audit PDF</div>
                            <div class="small text-muted">Synthèse institutionnelle</div>
                        </div>
                    </a>

                    <form action="{{ route('reports.encrypt', $report->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-4 border-0 d-flex align-items-center justify-content-center hover-lift">
                            <i class="fas fa-key me-3 fs-5 text-warning"></i>
                            <div class="text-start">
                                <div class="fw-bold">Chiffrer RSA-2048</div>
                                <div class="small opacity-75">Sécurisation réglementaire</div>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Errors Table -->
<div class="card border-0 shadow-lg mb-5 animate__animated animate__fadeInUp animate__delay-1s" style="border-radius: 1.5rem;">
    <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1">Détails des Non-Conformités</h5>
            <p class="text-muted small mb-0">Analysez chaque anomalie par rapport aux normes P5124</p>
        </div>
        <div class="d-flex gap-2">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-filter text-muted"></i></span>
                <select id="categoryFilter" class="form-select bg-light border-0 small">
                    <option value="">Toutes les catégories</option>
                    <option value="Format">Format (Technique)</option>
                    <option value="Données">Données Clients</option>
                    <option value="Statut">Statut FATCA</option>
                    <option value="Financiers">Comptes Financiers</option>
                    <option value="Cohérence">Cohérence Logique</option>
                </select>
            </div>
            <div class="input-group" style="width: 250px;">
                <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="errorSearch" class="form-control bg-light border-0" placeholder="Rechercher...">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="errorTable">
                <thead>
                    <tr class="bg-light text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">
                        <th class="px-4 py-3">Sévérité</th>
                        <th class="py-3">Élément / Catégorie</th>
                        <th class="py-3">Détails & Observation</th>
                        <th class="py-3">Remédiation Suggestion</th>
                        <th class="py-3 text-center">Auto-Fix</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report->validationErrors as $index => $error)
                    <tr class="error-row border-bottom" data-search="{{ strtolower($error->message) }} {{ strtolower($error->element) }}" data-category="{{ $error->category_label }}">
                        <td class="px-4">
                            <div class="scale-hover">{!! $error->severity_badge !!}</div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary mb-0">{{ $error->element }}</div>
                            <span class="badge bg-light text-muted fw-normal p-0">{{ $error->category_label }}</span>
                        </td>
                        <td>
                            <div class="text-dark fw-medium mb-1" style="max-width: 400px;">{{ $error->message }}</div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.7rem;">
                                    Obs: {{ Str::limit($error->actual_value, 20) }}
                                </span>
                                <i class="fas fa-arrow-right text-muted" style="font-size: 0.6rem;"></i>
                                <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.7rem;">
                                    Attendu: {{ Str::limit($error->expected_value, 20) }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-start text-primary small">
                                <i class="fas fa-lightbulb me-2 mt-1"></i>
                                <span>{{ $error->suggestion }}</span>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 0.65rem;">Ref: {{ $error->fatca_section }}</div>
                        </td>
                        <td class="text-center">
                            @if($error->auto_correctable)
                                <div class="text-success scale-hover" data-bs-toggle="tooltip" title="Réparable automatiquement">
                                    <i class="fas fa-check-double fs-5"></i>
                                </div>
                            @else
                                <div class="text-muted opacity-25" data-bs-toggle="tooltip" title="Nécessite intervention système source">
                                    <i class="fas fa-user-edit fs-5"></i>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="animate__animated animate__bounceIn">
                                <div class="p-4 bg-success bg-opacity-10 rounded-circle d-inline-block mb-3">
                                    <i class="fas fa-check fa-3x text-success"></i>
                                </div>
                                <h4 class="fw-bold">Fichier 100% Conforme</h4>
                                <p class="text-muted mx-auto" style="max-width: 400px;">Aucune anomalie détectée sur les {{ $report->total_records }} enregistrements analysés selon le standard P5124.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('errorSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const rows = document.querySelectorAll('.error-row');

        function filterErrors() {
            const query = searchInput.value.toLowerCase();
            const category = categoryFilter.value;

            rows.forEach(row => {
                const text = row.getAttribute('data-search');
                const rowCategory = row.getAttribute('data-category');
                
                const matchesSearch = text.includes(query);
                const matchesCategory = category === "" || rowCategory.includes(category);

                row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
            });
        }

        if (searchInput) searchInput.addEventListener('keyup', filterErrors);
        if (categoryFilter) categoryFilter.addEventListener('change', filterErrors);

        // Compliance Gauge Chart
        const ctx = document.getElementById('complianceGauge');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [{{ $report->compliance_rate }}, {{ 100 - $report->compliance_rate }}],
                        backgroundColor: ['#ffffff', 'rgba(255,255,255,0.2)'],
                        borderWidth: 0,
                        circumference: 240,
                        rotation: 240,
                        cutout: '80%',
                        borderRadius: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    animation: {
                        duration: 2000,
                        easing: 'easeOutBounce'
                    }
                }
            });
        }

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
<style>
    .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 1rem 3rem rgba(0,0,0,0.175) !important; }
    .scale-hover { transition: transform 0.2s ease; }
    .scale-hover:hover { transform: scale(1.1); }
    .compliance-badge { font-weight: 600; letter-spacing: 0.5px; border-radius: 8px; }
</style>
@endsection
