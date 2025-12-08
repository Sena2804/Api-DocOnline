<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArretMaladie extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'medecin_id',
        'date_debut',
        'date_fin',
        'duree_jours',
        'motif',
        'diagnostic',
        'recommandations',
        'renouvelable',
        'date_visite_controle',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_visite_controle' => 'date',
        'renouvelable' => 'boolean',
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
     * Vérifier si l'arrêt est actif
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->statut === 'actif' && $this->date_fin >= now();
    }

    /**
     * Vérifier si l'arrêt est terminé
     */
    public function getIsFinishedAttribute(): bool
    {
        return $this->statut === 'termine' || $this->date_fin < now();
    }

    /**
     * Vérifier si l'arrêt peut être renouvelé
     */
    public function getCanBeRenewedAttribute(): bool
    {
        return $this->renouvelable && $this->is_active;
    }
}