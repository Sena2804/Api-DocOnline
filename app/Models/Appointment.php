<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'medecin_id',
        'date',
        'heure',
        'consultation_type',
        'statut',
        'motif',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'video_started_at' => 'datetime',
        'video_ended_at' => 'datetime',
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
     * Relation avec l'ordonnance
     */
    public function ordonnance()
    {
        return $this->hasOne(Ordonnance::class);
    }
}
