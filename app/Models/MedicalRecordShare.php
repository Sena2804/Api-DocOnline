<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'medecin_source_id',
        'medecin_dest_id',
        'reason',
        'shared_at',
        'access_expires_at',
        'is_active',
        'access_token',
    ];

    protected $casts = [
        'shared_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'is_active' => 'boolean',
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
     * Relation avec le médecin source
     */
    public function medecinSource(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_source_id');
    }

    /**
     * Relation avec le médecin destinataire
     */
    public function medecinDest(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_dest_id');
    }

    /**
     * Vérifier si le partage est encore valide
     */
    public function getIsValidAttribute(): bool
    {
        return $this->is_active && $this->access_expires_at > now();
    }

    /**
     * Générer un token d'accès sécurisé
     */
    public function generateAccessToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['access_token' => hash('sha256', $token)]);
        return $token;
    }

    /**
     * Vérifier un token d'accès
     */
    public function verifyAccessToken($token): bool
    {
        return hash_equals($this->access_token, hash('sha256', $token));
    }
}