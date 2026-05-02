@extends('layouts.app')

@section('title', 'Nouveau Rapport')
@section('page_title', 'Conversion Excel vers FATCA XML')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-4 px-4 text-center">
                <div class="bg-cbc-gold bg-opacity-10 p-3 rounded-circle d-inline-block mb-3">
                    <i class="fas fa-file-invoice text-cbc-gold fs-3"></i>
                </div>
                <h4 class="fw-bold mb-1">Analyseur de Conformité FATCA</h4>
                <p class="text-muted mb-0">Importer un fichier Excel pour validation selon la Publication 5124</p>
            </div>
            
            <div class="card-body p-4">
                <form action="{{ route('reports.store') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                    @csrf
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-uppercase text-muted">Période de Reporting</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-calendar-check text-cbc-gold"></i></span>
                                <input type="date" name="reporting_period" class="form-control border-start-0 ps-0" value="{{ date('Y-12-31') }}" required>
                            </div>
                            <div class="form-text small">Clôture de l'année fiscale (ex: 31/12/{{ date('Y') }})</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-uppercase text-muted">Fichier Source (Excel/CSV)</label>
                            <label for="excel_file" class="upload-area border-2 w-100 mb-0">
                                <i class="fas fa-cloud-upload-alt fa-3x text-cbc-gold mb-3 opacity-50"></i>
                                <h6 class="fw-bold">Cliquez pour parcourir vos fichiers</h6>
                                <p class="text-muted small mb-0">Formats acceptés : .xlsx, .xls, .csv</p>
                                <input type="file" name="excel_file" id="excel_file" class="d-none" required onchange="updateFileName(this)">
                                <div id="file-name" class="mt-3 fw-bold text-success animate__animated animate__fadeIn"></div>
                            </label>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-4 d-flex align-items-center">
                        <div class="bg-cbc-gold bg-opacity-10 p-2 rounded-circle me-3">
                            <i class="fas fa-info-circle text-cbc-gold"></i>
                        </div>
                        <div class="small">
                            <strong>Note :</strong> Le système détecte automatiquement les colonnes (TIN, Noms, Soldes) et applique les règles de conversion v2.0.
                        </div>
                    </div>

                    <div class="d-grid pt-2">
                        <button type="submit" class="btn btn-primary py-3 fw-bold rounded-3 shadow-sm" id="submitBtn">
                            <i class="fas fa-cog me-2" id="submitIcon"></i> LANCER L'ANALYSE DE CONFORMITÉ
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <p class="text-muted small">
                <i class="fas fa-shield-alt me-1"></i> Système sécurisé par CBC Bank Compliance Department
            </p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function updateFileName(input) {
        const fileName = input.files[0] ? input.files[0].name : '';
        const nameDiv = document.getElementById('file-name');
        if (fileName) {
            nameDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i> Fichier prêt : ' + fileName;
        } else {
            nameDiv.textContent = '';
        }
    }

    document.getElementById('uploadForm').onsubmit = function() {
        const btn = document.getElementById('submitBtn');
        const icon = document.getElementById('submitIcon');
        
        btn.disabled = true;
        btn.classList.add('bg-secondary');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> ANALYSE EN COURS PAR LE MOTEUR COMPLIANCE...';
    };
</script>
@endsection
