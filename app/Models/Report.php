<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'excel_path',
        'xml_path',
        'xml_corrected_path',
        'encrypted_xml_path',
        'pdf_report_path',
        'reporting_period',
        'status',
        'total_errors',
        'total_warnings',
        'total_records',
        'raw_data',
        'mapping',
    ];

    protected function casts(): array
    {
        return [
            'reporting_period' => 'date',
            'raw_data' => 'array',
            'mapping' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validationErrors(): HasMany
    {
        return $this->hasMany(ValidationError::class);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => '<span class="badge bg-secondary bg-opacity-10 text-secondary compliance-badge"><i class="fas fa-clock me-1"></i>En attente</span>',
            'analyzing' => '<span class="badge bg-info bg-opacity-10 text-info compliance-badge"><i class="fas fa-spinner fa-spin me-1"></i>Analyse</span>',
            'errors_found' => '<span class="badge bg-danger bg-opacity-10 text-danger compliance-badge"><i class="fas fa-exclamation-triangle me-1"></i>Erreurs</span>',
            'valid' => '<span class="badge bg-success bg-opacity-10 text-success compliance-badge"><i class="fas fa-check-circle me-1"></i>Valide</span>',
            'corrected' => '<span class="badge bg-primary bg-opacity-10 text-primary compliance-badge"><i class="fas fa-wrench me-1"></i>Corrigé</span>',
            default => '<span class="badge bg-secondary bg-opacity-10 text-secondary compliance-badge">Inconnu</span>',
        };
    }

    public function getComplianceRateAttribute(): float
    {
        if ($this->total_records === 0) return 0;
        $errorWeight = $this->total_errors * 1;
        $maxScore = $this->total_records * 10;
        $score = max(0, $maxScore - $errorWeight);
        return round(($score / $maxScore) * 100, 1);
    }
}
