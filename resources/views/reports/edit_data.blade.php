@extends('layouts.app')

@section('title', 'Modification des Données')
@section('page_title', 'Correction Manuelle des Données')

@section('content')
<div class="container-fluid animate__animated animate__fadeIn">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-lg overflow-hidden" style="border-radius: 1.5rem;">
                <div class="card-header bg-cbc-dark text-white p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1 text-cbc-gold">Éditeur de Données Sources</h4>
                            <p class="text-white-50 mb-0">Rapport : {{ $report->original_filename }}</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('reports.show', $report->id) }}" class="btn btn-outline-light rounded-pill px-4">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <button type="submit" form="editDataForm" class="btn btn-cbc-gold text-dark fw-bold rounded-pill px-4">
                                <i class="fas fa-save me-2"></i>Enregistrer et Valider
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="alert alert-info m-4 rounded-4 border-0 bg-opacity-10 d-flex align-items-center">
                        <i class="fas fa-info-circle fs-4 me-3 text-info"></i>
                        <div>
                            <strong>Note :</strong> Modifiez directement les cellules ci-dessous. Une fois enregistré, le système relancera automatiquement l'analyse de conformité FATCA.
                        </div>
                    </div>

                    <form id="editDataForm" action="{{ route('reports.update_data', $report->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0" id="editableTable">
                                <thead class="sticky-top bg-white shadow-sm" style="z-index: 10;">
                                    <tr class="bg-light">
                                        <th class="px-3 py-3 text-center" style="width: 50px;">#</th>
                                        @if(!empty($report->raw_data))
                                            @foreach(array_keys($report->raw_data[0] ?? []) as $header)
                                                @if($header !== '_row_index')
                                                    <th class="py-3 px-3 text-nowrap text-cbc-dark" style="font-size: 0.75rem; letter-spacing: 1px;">
                                                        {{ strtoupper(str_replace('_', ' ', $header)) }}
                                                    </th>
                                                @endif
                                            @endforeach
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($report->raw_data ?? [] as $rowIndex => $row)
                                        <tr>
                                            <td class="text-center text-muted border-end fw-bold">{{ $row['_row_index'] ?? $rowIndex + 1 }}</td>
                                            @foreach($row as $key => $value)
                                                @if($key !== '_row_index')
                                                    <td class="p-1">
                                                        <input type="text" 
                                                               name="data[{{ $rowIndex }}][{{ $key }}]" 
                                                               value="{{ $value }}" 
                                                               class="form-control form-control-sm border-0 bg-transparent focus-shadow-none"
                                                               style="min-width: 150px;">
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="100" class="text-center py-5 text-muted">Aucune donnée à afficher.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .focus-shadow-none:focus {
        box-shadow: none;
        background-color: rgba(212, 175, 55, 0.05) !important;
        outline: 1px solid #d4af37;
    }
    #editableTable input {
        transition: all 0.2s;
        font-size: 0.85rem;
    }
    #editableTable tr:hover input {
        background-color: rgba(0,0,0,0.02);
    }
    .sticky-top {
        top: 0;
    }
</style>
@endsection
