@extends('layouts.app')

@section('title', 'Historique des Rapports')
@section('page_title', 'Archives et Suivi des Rapports FATCA')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0">Rapports Traités</h5>
            <small class="text-muted">Historique complet des soumissions de la CBC</small>
        </div>
        <a href="{{ route('reports.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-2"></i> Importer un Nouveau Fichier
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase text-muted small">
                    <tr>
                        <th class="px-4 py-3">Date d'Analyse</th>
                        <th>Rapport / Fichier</th>
                        <th>Période</th>
                        <th>Statut Compliance</th>
                        <th>Qualité des Données</th>
                        <th class="text-end px-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                    <tr>
                        <td class="px-4 text-muted small">
                            {{ $report->created_at->format('d/m/Y') }}<br>
                            {{ $report->created_at->format('H:i') }}
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 p-2 rounded-3 me-3">
                                    <i class="fas fa-file-excel text-success fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $report->original_filename }}</div>
                                    <small class="text-muted">Par {{ $report->user->name ?? 'Admin' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="fw-medium text-dark">{{ $report->reporting_period ? $report->reporting_period->format('Y') : 'N/A' }}</span>
                        </td>
                        <td>
                            {!! $report->status_badge !!}
                        </td>
                        <td>
                            <div class="d-flex align-items-center" style="width: 140px;">
                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                    <div class="progress-bar {{ $report->compliance_rate > 80 ? 'bg-cbc-gold' : ($report->compliance_rate > 50 ? 'bg-warning' : 'bg-danger') }}" 
                                         role="progressbar" style="width: {{ $report->compliance_rate }}%"></div>
                                </div>
                                <span class="small fw-bold">{{ $report->compliance_rate }}%</span>
                            </div>
                            <small class="text-muted" style="font-size: 0.7rem;">
                                {{ $report->total_errors }} Erreurs | {{ $report->total_warnings }} Alertes
                            </small>
                        </td>
                        <td class="text-end px-4">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg">
                                    <li><a class="dropdown-item py-2" href="{{ route('reports.show', $report->id) }}"><i class="fas fa-eye text-cbc-gold me-2"></i> Voir les détails</a></li>
                                    <li><a class="dropdown-item py-2" href="{{ route('reports.download_pdf', $report->id) }}"><i class="fas fa-file-pdf text-danger me-2"></i> Télécharger PDF</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('reports.destroy', $report->id) }}" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer cet archivage ?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item py-2 text-danger"><i class="fas fa-trash me-2"></i> Supprimer l'archive</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <img src="https://img.icons8.com/bubbles/100/000000/box.png" class="mb-3 opacity-50" alt="vide">
                            <h6 class="text-muted">Aucun rapport n'a été traité pour le moment.</h6>
                            <a href="{{ route('reports.create') }}" class="btn btn-primary btn-sm mt-2 rounded-pill px-4">Commencer maintenant</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reports->hasPages())
        <div class="p-4 border-top">
            {{ $reports->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
