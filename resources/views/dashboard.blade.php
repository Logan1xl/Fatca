@extends('layouts.app')

@section('title', 'Tableau de Bord')
@section('page_title', 'Vue d\'ensemble de la Conformité FATCA')

@section('content')
<div class="row g-4 mb-4">
    <!-- Summary Stats -->
    <div class="col-md-3">
        <div class="card card-stats p-3 h-100 shadow-sm border-0">
            <div class="d-flex align-items-center">
                <div class="bg-cbc-gold bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="fas fa-file-alt text-cbc-gold fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0">{{ $stats['total_reports'] }}</h4>
                    <small class="text-muted">Total Rapports</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats p-3 h-100 shadow-sm border-0" style="border-left-color: #e76f51;">
            <div class="d-flex align-items-center">
                <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="fas fa-exclamation-triangle text-danger fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-danger">{{ $stats['total_errors'] }}</h4>
                    <small class="text-muted">Erreurs Détectées</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats p-3 h-100 shadow-sm border-0" style="border-left-color: #2a9d8f;">
            <div class="d-flex align-items-center">
                <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="fas fa-check-circle text-success fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0 text-success">{{ $stats['valid_reports'] }}</h4>
                    <small class="text-muted">Rapports Valides</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-stats p-3 h-100 shadow-sm border-0" style="border-left-color: #d4af37;">
            <div class="d-flex align-items-center">
                <div class="bg-cbc-gold bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="fas fa-shield-alt text-cbc-gold fs-4"></i>
                </div>
                <div>
                    <h4 class="fw-bold mb-0">{{ number_format($stats['avg_compliance'], 1) }}%</h4>
                    <small class="text-muted">Taux de Conformité</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Reports -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">Rapports Récents</h5>
                <a href="{{ route('reports.create') }}" class="btn btn-primary btn-sm rounded-pill px-3">
                    <i class="fas fa-plus me-1"></i> Nouveau Rapport
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4">Fichier</th>
                                <th>Période</th>
                                <th>Statut</th>
                                <th>Erreurs</th>
                                <th class="text-end px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentReports as $report)
                            <tr>
                                <td class="px-4">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-excel text-success me-3 fs-5"></i>
                                        <div>
                                            <div class="fw-bold">{{ $report->original_filename }}</div>
                                            <small class="text-muted">Importé par {{ $report->user->name ?? 'Admin' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($report->reporting_period)->format('Y') }}</td>
                                <td>
                                    @if($report->status == 'valid')
                                        <span class="badge bg-success bg-opacity-10 text-success compliance-badge">Valide</span>
                                    @elseif($report->status == 'errors_found')
                                        <span class="badge bg-danger bg-opacity-10 text-danger compliance-badge">Erreurs</span>
                                    @elseif($report->status == 'corrected')
                                        <span class="badge bg-cbc-gold bg-opacity-10 text-cbc-gold compliance-badge">Corrigé</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary compliance-badge">{{ $report->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-danger fw-bold">{{ $report->total_errors }}</span>
                                </td>
                                <td class="text-end px-4">
                                    <a href="{{ route('reports.show', $report->id) }}" class="btn btn-light btn-sm rounded-circle">
                                        <i class="fas fa-eye text-cbc-gold"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Aucun rapport disponible.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Distribution -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0">Répartition des Erreurs</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @forelse($errorCategories as $category)
                    <li class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-tag text-muted me-2 small"></i>
                            {{ ucfirst(str_replace('_', ' ', $category->category)) }}
                        </div>
                        <span class="badge bg-light text-dark rounded-pill">{{ $category->total }}</span>
                    </li>
                    @empty
                    <li class="list-group-item border-0 px-0 text-center text-muted">Aucune erreur répertoriée.</li>
                    @endforelse
                </ul>
                
                <div class="mt-4 p-3 bg-light rounded-3">
                    <h6><i class="fas fa-lightbulb text-warning me-2"></i> Conseil Pro</h6>
                    <small class="text-muted">Assurez-vous que les colonnes 'TIN' et 'CountryCode' sont bien remplies dans vos fichiers Excel pour réduire 80% des erreurs communes.</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
