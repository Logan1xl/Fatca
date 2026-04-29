@extends('layouts.app')

@section('title', 'Journaux d\'Audit')
@section('page_title', 'Sécurité et Traçabilité Système')

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 py-4 px-4">
        <h5 class="fw-bold mb-0">Historique des Actions</h5>
        <small class="text-muted">Journal immuable des activités de conformité</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase text-muted small">
                    <tr>
                        <th class="px-4 py-3">Horodatage</th>
                        <th>Utilisateur / Entité</th>
                        <th>Type d'Action</th>
                        <th>Description de l'Événement</th>
                        <th class="px-4">Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="px-4 text-muted small">
                            <div class="fw-bold text-dark">{{ $log->created_at->format('d/m/Y') }}</div>
                            {{ $log->created_at->format('H:i:s') }}
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-light p-2 rounded-circle me-3">
                                    <i class="fas fa-user-shield text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $log->user->name ?? 'Système Automatique' }}</div>
                                    <small class="text-muted">{{ $log->user->email ?? 'no-reply@cbc-bank.com' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-primary bg-opacity-10 text-primary compliance-badge">
                                {{ strtoupper($log->action) }}
                            </span>
                        </td>
                        <td>
                            <div class="text-dark small">{{ $log->details }}</div>
                        </td>
                        <td class="px-4">
                            <code class="bg-light px-2 py-1 rounded small border">{{ $log->ip_address }}</code>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">Aucun log d'audit n'a été enregistré.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="p-4 border-top">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
