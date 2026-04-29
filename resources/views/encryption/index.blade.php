@extends('layouts.app')

@section('title', 'Sécurité RSA')
@section('page_title', 'Chiffrement Asymétrique')
@section('page_subtitle', 'Gestion des clés de sécurité CBC')

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Statut de la Paire de Clés</div>
            <div class="card-body">
                @if($keysExist)
                <div class="alert alert-success d-flex align-items-center">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <strong>Paire de clés active</strong><br>
                        Une paire de clés RSA 2048 bits est configurée et prête.
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Clé Publique (PEM)</label>
                    <textarea class="form-control font-monospace" rows="8" readonly style="font-size: 11px;">{{ $publicKey }}</textarea>
                </div>
                @else
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <strong>Aucune clé détectée</strong><br>
                        Vous devez générer une paire de clés pour chiffrer les rapports.
                    </div>
                </div>
                @endif

                <form action="{{ route('settings.encryption.generate') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" onclick="return confirm('Attention : générer de nouvelles clés rendra les anciens fichiers chiffrés illisibles. Continuer ?')">
                        <i class="fas fa-sync me-2"></i> {{ $keysExist ? 'RÉGÉNÉRER LES CLÉS' : 'GÉNÉRER LA PAIRE DE CLÉS' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Pourquoi le chiffrement RSA ?</div>
            <div class="card-body">
                <div class="d-flex mb-4">
                    <div class="stat-icon icon-blue me-3">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold">Confidentialité Bancaire</h6>
                        <p class="text-muted small">Les données FATCA contiennent des informations hautement sensibles. Le chiffrement asymétrique garantit que seul le destinataire possédant la clé privée peut lire le contenu.</p>
                    </div>
                </div>
                <div class="d-flex mb-4">
                    <div class="stat-icon icon-blue me-3">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold">Intégrité des Données</h6>
                        <p class="text-muted small">Le processus assure que le fichier XML n'a pas été modifié entre sa génération par le service conformité et sa réception finale.</p>
                    </div>
                </div>
                <div class="alert alert-info">
                    <small><i class="fas fa-info-circle me-1"></i> La clé privée est stockée en toute sécurité sur le serveur et n'est jamais exposée dans l'interface.</small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
