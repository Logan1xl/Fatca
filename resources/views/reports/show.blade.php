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
                        <div
                            class="col-md-4 bg-dark text-white p-4 d-flex flex-column justify-content-center align-items-center border-end border-primary border-opacity-25">
                            <div class="compliance-gauge position-relative" style="height: 140px; width: 140px;">
                                <canvas id="complianceGauge"></canvas>
                                <div class="position-absolute top-50 start-50 translate-middle mt-2 text-center">
                                    <h2 class="fw-bold mb-0">{{ $report->compliance_rate }}%</h2>
                                    <small class="text-white-50 d-block"
                                        style="font-size: 0.6rem; letter-spacing: 1px;">SCORE</small>
                                </div>
                            </div>
                            <div class="mt-3 text-cbc-gold small text-uppercase fw-bold">Conformité Globale</div>
                        </div>
                        <div class="col-md-8 p-4">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <div>
                                    <h4 class="fw-bold text-dark mb-1">{{ $report->original_filename }}</h4>
                                    <div class="d-flex align-items-center text-muted small">
                                        <span class="badge bg-cbc-dark text-cbc-gold border me-2"><i
                                                class="fas fa-calendar me-1"></i>
                                            {{ $report->reporting_period ? $report->reporting_period->format('Y') : '2024' }}</span>
                                        <span class="badge bg-cbc-dark text-cbc-gold border"><i
                                                class="fas fa-clock me-1"></i>
                                            {{ $report->created_at->format('d M H:i') }}</span>
                                    </div>
                                </div>
                                <div class="scale-up animate__animated animate__pulse animate__infinite">
                                    {!! $report->status_badge !!}
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-4">
                                    <div
                                        class="p-3 rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-10 text-center h-100">
                                        <h4 class="fw-bold mb-0 text-danger">{{ $report->total_errors }}</h4>
                                        <small class="text-danger small fw-medium">Critiques</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div
                                        class="p-3 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-10 text-center h-100">
                                        <h4 class="fw-bold mb-0 text-warning">{{ $report->total_warnings }}</h4>
                                        <small class="text-warning small fw-medium">Alertes</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div
                                        class="p-3 rounded-4 bg-cbc-dark bg-opacity-10 border border-cbc-dark border-opacity-10 text-center h-100">
                                        <h4 class="fw-bold mb-0 text-cbc-gold">{{ $report->total_records }}</h4>
                                        <small class="text-muted small fw-medium">Lignes</small>
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
                        <span class="p-2 bg-cbc-gold bg-opacity-10 rounded-3 me-2">
                            <i class="fas fa-bolt text-cbc-gold"></i>
                        </span>
                        Actions de Remédiation
                    </h6>
                </div>
                <div class="card-body p-4 pt-2">
                    <div class="d-grid gap-3">
                        <!-- Bouton de modification manuelle -->
                        <a href="{{ route('reports.edit_data', $report->id) }}"
                            class="btn btn-cbc-gold py-3 rounded-4 shadow-lg border-0 text-dark d-flex align-items-center justify-content-center hover-lift">
                            <i class="fas fa-edit me-3 fs-5"></i>
                            <div class="text-start">
                                <div class="fw-bold">Modifier les Données</div>
                                <div class="small opacity-75 text-dark">Édition manuelle des lignes</div>
                            </div>
                        </a>

                        @if($report->total_errors > 0)
                            <div class="alert alert-danger rounded-4 p-3 mb-0 small border-0 bg-opacity-10">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Action requise :</strong> Des erreurs critiques ({{ $report->total_errors }}) bloquent la génération du XML.
                            </div>
                            
                            <form action="{{ route('reports.apply_autofix', $report->id) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="btn btn-primary w-100 py-3 rounded-4 shadow-lg border-0 d-flex align-items-center justify-content-center hover-lift">
                                    <i class="fas fa-magic me-3 fs-5"></i>
                                    <div class="text-start">
                                        <div class="fw-bold">Tentative Auto-Fix</div>
                                        <div class="small opacity-75">Correction intelligente des formats</div>
                                    </div>
                                </button>
                            </form>
                        @elseif($report->status !== 'valid')
                            <div class="alert alert-success rounded-4 p-3 mb-0 small border-0 bg-opacity-10">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Félicitations !</strong> Les données sont conformes. Vous pouvez générer le XML.
                            </div>

                            <form action="{{ route('reports.generate_xml', $report->id) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="btn btn-success w-100 py-3 rounded-4 shadow-lg border-0 text-white d-flex align-items-center justify-content-center hover-lift">
                                    <i class="fas fa-cog fa-spin me-3 fs-5 text-white"></i>
                                    <div class="text-start">
                                        <div class="fw-bold text-white">Générer le XML Final</div>
                                        <div class="small opacity-75 text-white">Prêt pour soumission IRS</div>
                                    </div>
                                </button>
                            </form>
                        @else
                            <a href="{{ route('reports.download_xml', $report->id) }}"
                                class="btn btn-success py-3 rounded-4 shadow-lg border-0 text-white d-flex align-items-center justify-content-center hover-lift">
                                <i class="fas fa-file-download me-3 fs-5 text-white"></i>
                                <div class="text-start">
                                    <div class="fw-bold text-white">Télécharger XML Final</div>
                                    <div class="small opacity-75 text-white">Format FATCA v2.0</div>
                                </div>
                            </a>
                        @endif

                        <a href="{{ route('reports.download_pdf', $report->id) }}"
                            class="btn btn-light py-3 rounded-4 border-0 d-flex align-items-center justify-content-center hover-lift">
                            <i class="fas fa-file-pdf me-3 fs-5 text-danger"></i>
                            <div class="text-start">
                                <div class="fw-bold">Rapport Audit PDF</div>
                                <div class="small text-muted">Synthèse institutionnelle</div>
                            </div>
                        </a>

                        <form action="{{ route('reports.encrypt', $report->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="btn btn-dark w-100 py-3 rounded-4 border-0 d-flex align-items-center justify-content-center hover-lift">
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

    <div class="card border-0 shadow-lg mb-4" style="border-radius: 1.5rem;">
        <div class="card-header bg-white border-0 p-0">
            <ul class="nav nav-tabs nav-fill border-0 px-4 pt-3" id="analysisTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold py-3 border-0 border-bottom border-3 border-transparent"
                        id="errors-tab" data-bs-toggle="tab" data-bs-target="#errors-content" type="button" role="tab">
                        <i class="fas fa-exclamation-circle me-2"></i>Détails des Anomalies
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold py-3 border-0 border-bottom border-3 border-transparent"
                        id="spreadsheet-tab" data-bs-toggle="tab" data-bs-target="#spreadsheet-content" type="button"
                        role="tab">
                        <i class="fas fa-table me-2"></i>Vue Tableur (Données Excel)
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="analysisTabsContent">
        <!-- Tab 1: Errors List -->
        <div class="tab-pane fade show active" id="errors-content" role="tabpanel">
            <!-- Errors Table (moved inside tab) -->
            <div class="card border-0 shadow-lg mb-5" style="border-radius: 1.5rem;">
                <div class="card-header bg-white border-0 py-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-1">Répertoire des Non-Conformités</h5>
                        <p class="text-muted small mb-0">Analyse granulaire selon les standards FATCA</p>
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
                            <input type="text" id="errorSearch" class="form-control bg-light border-0"
                                placeholder="Rechercher...">
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
                                    <tr class="error-row border-bottom"
                                        data-search="{{ strtolower($error->message) }} {{ strtolower($error->element) }}"
                                        data-category="{{ $error->category_label }}" data-row-ref="{{ $error->row_reference }}">
                                        <td class="px-4">
                                            <div class="scale-hover">{!! $error->severity_badge !!}</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-cbc-dark mb-0">{{ $error->element }}</div>
                                            <span
                                                class="badge bg-light text-muted fw-normal p-0">{{ $error->category_label }}</span>
                                            @if($error->row_reference !== null)
                                                <div class="mt-1">
                                                    <span class="badge bg-cbc-gold bg-opacity-25 text-cbc-dark rounded-pill"
                                                        style="font-size: 0.6rem;">Ligne Excel #{{ $error->row_reference }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-dark fw-medium mb-1" style="max-width: 400px;">
                                                {{ $error->message }}</div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span
                                                    class="badge rounded-pill bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1"
                                                    style="font-size: 0.7rem;">
                                                    Obs: {{ Str::limit($error->actual_value, 20) }}
                                                </span>
                                                <i class="fas fa-arrow-right text-muted" style="font-size: 0.6rem;"></i>
                                                <span
                                                    class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"
                                                    style="font-size: 0.7rem;">
                                                    Attendu: {{ Str::limit($error->expected_value, 20) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-start text-primary small">
                                                <i class="fas fa-lightbulb me-2 mt-1"></i>
                                                <span>{{ $error->suggestion }}</span>
                                            </div>
                                            <div class="text-muted mt-1" style="font-size: 0.65rem;">Ref:
                                                {{ $error->fatca_section }}</div>
                                        </td>
                                        <td class="text-center">
                                            @if($error->auto_correctable)
                                                <div class="text-success scale-hover" data-bs-toggle="tooltip"
                                                    title="Réparable automatiquement">
                                                    <i class="fas fa-check-double fs-5"></i>
                                                </div>
                                            @else
                                                <div class="text-muted opacity-25" data-bs-toggle="tooltip"
                                                    title="Nécessite intervention système source">
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
                                                <p class="text-muted mx-auto" style="max-width: 400px;">Aucune anomalie détectée
                                                    sur les {{ $report->total_records }} enregistrements analysés.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Spreadsheet View -->
        <div class="tab-pane fade" id="spreadsheet-content" role="tabpanel">
            <div class="card border-0 shadow-lg mb-5" style="border-radius: 1.5rem;">
                <div class="card-header bg-white border-0 py-4 px-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1">Visualisation des Données Sources</h5>
                            <p class="text-muted small mb-0">Contenu brut du fichier Excel avec marquage des anomalies</p>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <a href="{{ route('reports.edit_data', $report->id) }}" class="btn btn-cbc-gold btn-sm rounded-pill px-3 fw-bold">
                                <i class="fas fa-edit me-1"></i> Modifier ces données
                            </a>
                            <div class="badge bg-primary rounded-pill px-3 py-2">
                                {{ count($report->raw_data ?? []) }} Enregistrements
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                        @if(!empty($report->raw_data))
                            <table class="table table-sm table-hover align-middle mb-0" id="excelSpreadsheet">
                                <thead class="sticky-top bg-white shadow-sm" style="z-index: 10;">
                                    <tr class="bg-light">
                                        <th class="px-3 py-2 text-center" style="width: 50px;">#</th>
                                        <th class="py-2 px-3" style="min-width: 250px;">Diagnostic Compliance</th>
                                        @foreach(array_keys($report->raw_data[0] ?? []) as $header)
                                            @if($header !== '_row_index')
                                                <th class="py-2 px-3 text-nowrap">{{ strtoupper(str_replace('_', ' ', $header)) }}</th>
                                            @endif
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report->raw_data as $row)
                                        @php
                                            $rowIndex = $row['_row_index'] ?? null;
                                            $rowErrors = $report->validationErrors->where('row_reference', $rowIndex);
                                            $hasError = $rowErrors->where('severity', 'error')->count() > 0;
                                            $hasWarning = $rowErrors->where('severity', 'warning')->count() > 0;
                                        @endphp
                                        <tr class="spreadsheet-row" data-row-index="{{ $rowIndex }}">
                                            <td class="text-center text-muted border-end">{{ $rowIndex }}</td>
                                            <td class="px-3 border-end" style="min-width: 250px;">
                                                @if($rowErrors->count() > 0)
                                                    @foreach($rowErrors as $err)
                                                        <div class="mb-1 d-flex align-items-baseline">
                                                            @if($err->severity === 'error')
                                                                <i class="fas fa-times-circle text-danger me-2" style="font-size: 0.7rem;"></i>
                                                                <span class="text-danger fw-bold"
                                                                    style="font-size: 0.7rem;">{{ $err->message }}</span>
                                                            @else
                                                                <i class="fas fa-exclamation-triangle text-warning me-2"
                                                                    style="font-size: 0.7rem;"></i>
                                                                <span class="text-warning fw-medium"
                                                                    style="font-size: 0.7rem;">{{ $err->message }}</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="text-center">
                                                        <i class="fas fa-check-circle text-success"></i>
                                                        <span class="text-success small ms-1">Conforme</span>
                                                    </div>
                                                @endif
                                            </td>
                                            @foreach($row as $key => $value)
                                                @if($key !== '_row_index')
                                                    @php
                                                        $mapping = $report->mapping ?? [];
                                                        $cellError = $rowErrors->first(function ($e) use ($key, $mapping) {
                                                            $element = strtolower(str_replace([' ', '/', '_'], '', $e->element));
                                                            $normalizedKey = strtolower(str_replace([' ', '_'], '', $key));

                                                            // 1. Check direct mapping from the report
                                                            foreach ($mapping as $fatcaField => $originalHeader) {
                                                                $normalizedFatca = strtolower(str_replace([' ', '_'], '', $fatcaField));
                                                                if ($normalizedFatca === $element || str_contains($element, $normalizedFatca)) {
                                                                    if (strtolower(str_replace([' ', '_'], '', $originalHeader)) === $normalizedKey) {
                                                                        return true;
                                                                    }
                                                                }
                                                            }

                                                            // 2. Fallback: Common FATCA field name mappings
                                                            $fieldSynonyms = [
                                                                'tin' => ['tin', 'taxid', 'nif', 'ssn', 'itin', 'ein'],
                                                                'firstname' => ['first', 'prenom', 'given'],
                                                                'lastname' => ['last', 'nom', 'family', 'surname'],
                                                                'organisationname' => ['org', 'entity', 'raison', 'nom'],
                                                                'accountnumber' => ['account', 'compte', 'iban'],
                                                                'accountbalance' => ['balance', 'solde', 'amount'],
                                                                'countrycode' => ['country', 'pays'],
                                                                'birthdate' => ['birth', 'naissance', 'dob'],
                                                            ];

                                                            foreach ($fieldSynonyms as $fatca => $synonyms) {
                                                                if (str_contains($element, $fatca)) {
                                                                    foreach ($synonyms as $syn) {
                                                                        if ($normalizedKey === $syn || preg_match('/(?:\b|_)' . preg_quote($syn, '/') . '(?:\b|_)/i', $key)) {
                                                                            return true;
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            // 3. Last resort: Direct string match with word boundaries
                                                            if (preg_match('/(?:\b|_)' . preg_quote($element, '/') . '(?:\b|_)/i', $key))
                                                                return true;

                                                            return false;
                                                        });
                                                        $cellSeverity = $cellError ? $cellError->severity : null;
                                                    @endphp
                                                    <td class="px-3 py-2 text-nowrap @if($cellSeverity === 'error') bg-danger bg-opacity-25 text-danger @elseif($cellSeverity === 'warning') bg-warning bg-opacity-25 text-warning @endif"
                                                        @if($cellError) data-bs-toggle="tooltip" data-bs-placement="top"
                                                        title="{{ $cellError->message }}" @endif>
                                                        <span class="@if($cellError) fw-bold @endif">{{ $value }}</span>
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="text-center py-5">
                                <i class="fas fa-table-list fa-3x text-muted opacity-25 mb-3"></i>
                                <p class="text-muted">Aucune donnée source disponible pour ce rapport.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-3px);
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important;
        }

        .scale-hover {
            transition: transform 0.2s ease;
        }

        .scale-hover:hover {
            transform: scale(1.1);
        }

        .compliance-badge {
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 8px;
        }

        /* Tabs Styling */
        .nav-tabs .nav-link {
            color: #6c757d;
            transition: all 0.3s;
        }

        .nav-tabs .nav-link:hover {
            color: #d4af37;
        }

        .nav-tabs .nav-link.active {
            color: #d4af37;
            border-bottom: 3px solid #d4af37 !important;
            background: transparent;
        }

        /* Spreadsheet Styling */
        #excelSpreadsheet thead th {
            font-size: 0.7rem;
            font-weight: 700;
            color: #1a1a1a;
            background: #f8f9fa;
        }

        #excelSpreadsheet tbody td {
            font-size: 0.8rem;
        }

        .spreadsheet-row {
            cursor: default;
            transition: background 0.1s;
        }

        .bg-soft-info {
            background-color: rgba(13, 202, 240, 0.1);
        }
    </style>
@endsection