<?php
// app/Models/TelemedicineSession.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TelemedicineSession extends Model
{
    protected $fillable = [
        'appointment_id',
        'room_id',
        'room_url',
        'started_at',
        'ended_at',
        'duration_minutes',
        'recording_url',
        'notes',
        'medecin_joined',
        'patient_joined',
        'medecin_joined_at',
        'patient_joined_at',
        'connection_quality',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'medecin_joined_at' => 'datetime',
        'patient_joined_at' => 'datetime',
        'medecin_joined' => 'boolean',
        'patient_joined' => 'boolean',
    ];

    // Relation
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // Accesseurs utiles
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->ended_at) {
            return $this->started_at->diffInMinutes($this->ended_at);
        }
        return null;
    }

    // Méthodes d'action
    public function markAsStarted(): void
    {
        $this->update([
            'started_at' => now(),
            'status' => 'active',
        ]);
    }

    public function markAsCompleted(?string $notes = null): void
    {
        $endedAt = now();
        $duration = $this->started_at ? 
            $this->started_at->diffInMinutes($endedAt) : 0;

        $this->update([
            'ended_at' => $endedAt,
            'duration_minutes' => $duration,
            'status' => 'completed',
            'notes' => $notes,
        ]);
    }

    public function markParticipantJoined(string $participantType): void
    {
        if ($participantType === 'medecin') {
            $this->update([
                'medecin_joined' => true,
                'medecin_joined_at' => now(),
            ]);
        } elseif ($participantType === 'patient') {
            $this->update([
                'patient_joined' => true,
                'patient_joined_at' => now(),
            ]);
        }
    }
}