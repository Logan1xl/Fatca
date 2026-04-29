@extends('layouts.app')

@section('title', 'Analyse du Rapport')
@section('page_title', 'Détails de l\'Analyse Compliance')

@section('content')
<div class="row g-4 mb-4">
    <!-- Analysis Summary Card -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-4 text-center border-md-end border-sm-0 pe-md-4 mb-3 mb-md-0">
                        <div class="compliance-gauge position-relative" style="height: 120px;">
                            <canvas id="complianceGauge"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle mt-2">
                                <h3 class="fw-bold mb-0">{{ $report->compliance_rate }}%</h3>
                                <small class="text-muted d-block" style="font-size: 0.65rem;">SCORE</small>
                            </div>
                        </div>
                        <div class="mt-2 text-muted small">Taux de Conformité FATCA</div>
                    </div>
                    <div class="col-md-8 ps-md-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $report->original_filename }}</h5>
                                <div class="text-muted small">
                                    <i class="fas fa-calendar-alt me-1"></i> Période : {{ $report->reporting_period ? $report->reporting_period->format('Y') : 'N/A' }}
                                    <span class="mx-2">|</span>
                                    <i class="fas fa-clock me-1"></i> Analysé le {{ $report->created_at->format('d/m/Y à H:i') }}
                                </div>
                            </div>
                            {!! $report->status_badge !!}
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="bg-light p-2 rounded text-center">
                                    <h5 class="fw-bold mb-0 text-danger">{{ $report->total_errors }}</h5>
                                    <small class="text-muted" style="font-size: 0.75rem;">Erreurs</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-light p-2 rounded text-center">
                                    <h5 class="fw-bold mb-0 text-warning">{{ $report->total_warnings }}</h5>
                                    <small class="text-muted" style="font-size: 0.75rem;">Alertes</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-light p-2 rounded text-center">
                                    <h5 class="fw-bold mb-0 text-primary">{{ $report->total_records }}</h5>
                                    <small class="text-muted" style="font-size: 0.75rem;">Records</small>
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
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-cog me-2"></i> Actions Disponibles</h6>
            </div>
            <div class="card-body p-4 pt-0">
                <div class="d-grid gap-2">
                    @if($report->total_errors > 0)
                        <a href="{{ route('reports.download_corrected', $report->id) }}" class="btn btn-primary py-2 rounded-3 shadow-sm">
                            <i class="fas fa-magic me-2"></i> Générer XML Corrigé
                        </a>
                    @else
                        <a href="{{ route('reports.download_xml', $report->id) }}" class="btn btn-success py-2 rounded-3 shadow-sm text-white">
                            <i class="fas fa-check-double me-2"></i> Télécharger XML Final
                        </a>
                    @endif

                    <a href="{{ route('reports.download_pdf', $report->id) }}" class="btn btn-outline-secondary py-2 rounded-3">
                        <i class="fas fa-file-pdf me-2 text-danger"></i> Rapport de Remédiation (PDF)
                    </a>

                    <form action="{{ route('reports.encrypt', $report->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 mt-2">
                            <i class="fas fa-lock-open me-2 text-warning"></i> Chiffrement Asymétrique RSA
                        </button>
                    </form>
                    
                    @if($report->encrypted_xml_path)
                        <div class="alert alert-success mt-2 py-2 small border-0">
                            <i class="fas fa-shield-check me-1"></i> Fichier sécurisé disponible (.enc)
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Errors Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="fas fa-list-check me-2 text-primary"></i> Répertoire des Anomalies</h6>
        <div class="input-group input-group-sm" style="width: 280px;">
            <span class="input-group-text bg-light border-0"><i class="fas fa-search"></i></span>
            <input type="text" id="errorSearch" class="form-control bg-light border-0" placeholder="Filtrer les anomalies...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="errorTable">
                <thead class="bg-light">
                    <tr>
                        <th class="px-4">#</th>
                        <th>Sévérité</th>
                        <th>Source / Élément</th>
                        <th>Détails de l'Anomalie</th>
                        <th>Suggestion CBC</th>
                        <th class="text-center">Action Auto</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report->validationErrors as $index => $error)
                    <tr class="error-row" data-search="{{ strtolower($error->message) }} {{ strtolower($error->element) }}">
                        <td class="px-4 text-muted">{{ $index + 1 }}</td>
                        <td>{!! $error->severity_badge !!}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $error->element }}</div>
                            <small class="text-muted d-block">{{ $error->category_label }}</small>
                            <span class="badge bg-light text-muted border-0 fw-normal" style="font-size: 0.65rem;">{{ $error->fatca_section }}</span>
                        </td>
                        <td>
                            <div class="text-dark mb-1">{{ $error->message }}</div>
                            <div class="d-flex gap-2 small">
                                <span class="text-muted">Obs : <code class="text-danger">{{ $error->actual_value }}</code></span>
                                <span class="text-muted">Att : <code class="text-success">{{ $error->expected_value }}</code></span>
                            </div>
                        </td>
                        <td>
                            <div class="text-primary small fw-medium">{{ $error->suggestion }}</div>
                        </td>
                        <td class="text-center">
                            @if($error->auto_correctable)
                                <span class="text-success" title="Correction automatique possible">
                                    <i class="fas fa-check-circle fs-5"></i>
                                </span>
                            @else
                                <span class="text-muted opacity-50" title="Correction manuelle requise">
                                    <i class="fas fa-minus-circle fs-5"></i>
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="fas fa-check-double text-success fa-3x mb-3 opacity-25"></i>
                            <h5 class="fw-bold">Félicitations !</h5>
                            <p class="text-muted">Aucune anomalie détectée. Ce fichier est prêt pour l'IRS.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search filter
        const searchInput = document.getElementById('errorSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.error-row').forEach(row => {
                    const text = row.getAttribute('data-search');
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }

        // Compliance Gauge Chart
        const ctx = document.getElementById('complianceGauge');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [{{ $report->compliance_rate }}, {{ 100 - $report->compliance_rate }}],
                        backgroundColor: ['#2a9d8f', '#f4f4f9'],
                        borderWidth: 0,
                        circumference: 180,
                        rotation: 270,
                        cutout: '85%',
                        borderRadius: 5
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
                        duration: 1500,
                        easing: 'easeOutQuart'
                    }
                }
            });
        }
    });
</script>
@endsection
