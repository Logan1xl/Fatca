@extends('layouts.app')

@section('title', 'Paramètres Sécurité')
@section('page_title', 'Configuration de la Cryptographie RSA')

@section('content')
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-key me-2 text-primary"></i> Gestion des Clés RSA</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    Pour chiffrer les fichiers XML destinés à l'IRS, vous devez disposer d'une paire de clés RSA (2048 bits). 
                    La clé publique est utilisée pour le chiffrement, tandis que la clé privée doit être conservée en lieu sûr.
                </p>

                @if($keysExist)
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 d-flex align-items-center mb-4">
                        <i class="fas fa-check-circle me-3 fs-4"></i>
                        <div>
                            <strong class="d-block">Clés actives détectées</strong>
                            <small>Le système est prêt pour le chiffrement des rapports.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Clé Publique (Format PEM)</label>
                        <textarea class="form-control bg-light border-0 small" rows="8" readonly style="font-family: monospace; font-size: 0.75rem;">{{ $publicKey }}</textarea>
                    </div>
                @else
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 d-flex align-items-center mb-4">
                        <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                        <div>
                            <strong class="d-block">Aucune clé détectée</strong>
                            <small>Veuillez générer une nouvelle paire de clés pour activer le chiffrement.</small>
                        </div>
                    </div>
                @endif

                <form action="{{ route('settings.generate-keys') }}" method="POST" onsubmit="return confirm('Attention : La génération de nouvelles clés rendra illisibles les anciens fichiers chiffrés. Continuer ?')">
                    @csrf
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 rounded-3 shadow-sm">
                            <i class="fas fa-sync-alt me-2"></i> {{ $keysExist ? 'Régénérer les Clés' : 'Générer les Clés de Sécurité' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-info-circle me-2 text-info"></i> Informations de Sécurité</h6>
            </div>
            <div class="card-body p-4">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item border-0 px-0 py-3">
                        <div class="fw-bold mb-1"><i class="fas fa-shield-alt me-2 text-primary"></i> Chiffrement Hybride</div>
                        <div class="text-muted">Nous utilisons l'AES-256-CBC pour les données et le RSA-2048 pour la clé de session, garantissant performance et sécurité maximale.</div>
                    </li>
                    <li class="list-group-item border-0 px-0 py-3">
                        <div class="fw-bold mb-1"><i class="fas fa-fingerprint me-2 text-primary"></i> Publication 5124</div>
                        <div class="text-muted">Conforme aux exigences techniques de l'IRS pour la transmission sécurisée via IDES.</div>
                    </li>
                    <li class="list-group-item border-0 px-0 py-3">
                        <div class="fw-bold mb-1"><i class="fas fa-file-invoice me-2 text-primary"></i> Traçabilité (Audit)</div>
                        <div class="text-muted">Chaque action de chiffrement ou de génération de clé est enregistrée dans les journaux d'audit avec l'identité de l'opérateur.</div>
                    </li>
                </ul>
                
                <div class="mt-4 p-3 bg-light rounded-3">
                    <div class="d-flex">
                        <i class="fas fa-user-lock text-warning me-3 mt-1"></i>
                        <div>
                            <h6 class="fw-bold mb-1">Stockage des Clés</h6>
                            <small class="text-muted">Les clés sont stockées dans le répertoire <code>storage/app/keys</code>. Assurez-vous que ce dossier est exclu des sauvegardes publiques et correctement protégé au niveau du serveur.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
