<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationError extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'severity',
        'category',
        'element',
        'row_reference',
        'message',
        'expected_value',
        'actual_value',
        'suggestion',
        'fatca_section',
        'auto_correctable',
    ];

    protected function casts(): array
    {
        return [
            'auto_correctable' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function getSeverityBadgeAttribute(): string
    {
        return match ($this->severity) {
            'error' => '<span class="badge bg-danger bg-opacity-10 text-danger compliance-badge"><i class="fas fa-times-circle me-1"></i>Erreur</span>',
            'warning' => '<span class="badge bg-warning bg-opacity-10 text-warning compliance-badge"><i class="fas fa-exclamation-triangle me-1"></i>Alerte</span>',
            'info' => '<span class="badge bg-info bg-opacity-10 text-info compliance-badge"><i class="fas fa-info-circle me-1"></i>Info</span>',
            default => '<span class="badge bg-secondary bg-opacity-10 text-secondary compliance-badge">N/A</span>',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'structure' => 'Structure (Organisation)',
            'format' => 'Format (Technique)',
            'characters' => 'Caractères Interdits',
            'data' => 'Données Clients',
            'fatca_status' => 'Statut FATCA',
            'financial' => 'Comptes Financiers',
            'coherence' => 'Cohérence Logique',
            'conversion' => 'Conversion XML',
            'regulatory' => 'Réglementaire',
            'required' => 'Champ Requis',
            default => ucfirst($this->category),
        };
    }
}
