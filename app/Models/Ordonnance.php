<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ordonnance extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'medecin_id',
        'date_prescription',
        'date_validite',
        'instructions',
        'renouvellements',
        'statut',
    ];

    protected $casts = [
        'date_prescription' => 'date',
        'date_validite' => 'date',
    ];

    /**
     * Relation avec le rendez-vous
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Relation avec le patient
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relation avec le médecin
     */
    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class);
    }

    /**
     * Relation avec les médicaments
     */
    public function medicaments(): HasMany
    {
        return $this->hasMany(Medicament::class);
    }

    /**
     * Vérifier si l'ordonnance est expirée
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->date_validite < now();
    }

    /**
     * Vérifier si l'ordonnance peut être renouvelée
     */
    public function getCanBeRenewedAttribute(): bool
    {
        return $this->renouvellements > 0 && !$this->is_expired;
    }
}