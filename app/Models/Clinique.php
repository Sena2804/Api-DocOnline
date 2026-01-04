<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Clinique extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'email',
        'password',
        'telephone',
        'address',
        'type_etablissement',
        'description',
        'photo_profil',
        'urgences_24h',
        'parking_disponible',
        'site_web',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'urgences_24h' => 'boolean',
        'parking_disponible' => 'boolean',
    ];

    // Relation avec les médecins
    public function medecins()
    {
        return $this->belongsToMany(Medecin::class, 'clinique_medecin')
            ->withPivot('fonction', 'created_at', 'updated_at')
            ->withTimestamps();
    }

    // Getter pour l'URL de la photo de profil
    public function getPhotoUrlAttribute()
    {
        return $this->photo_profil ? asset('storage/' . $this->photo_profil) : null;
    }
}
