<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Examen extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'medecin_id',
        'type',
        'description',
        'date_prescription',
        'date_realisation',
        'resultat',
        'observations',
        'fichier_joint',
        'statut',
    ];

    protected $casts = [
        'date_prescription' => 'date',
        'date_realisation' => 'date',
    ];

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
     * Obtenir l'URL du fichier joint
     */
    public function getFichierUrlAttribute(): ?string
    {
        return $this->fichier_joint ? asset('storage/' . $this->fichier_joint) : null;
    }

    /**
     * Vérifier si l'examen est terminé
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->statut === 'termine';
    }
}