<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Antecedent extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'type',
        'description',
        'date_diagnostic',
        'traitement',
        'statut',
        'notes',
    ];

    protected $casts = [
        'date_diagnostic' => 'date',
    ];

    /**
     * Relation avec le patient
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}