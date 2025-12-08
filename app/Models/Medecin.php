<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medecin extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'specialite',
        'address',
        'bio',
        'password',
        'photo_profil',
        'experience_years',
        'languages',
        'professional_background',
        'consultation_price',
        'insurance_accepted',
        'working_hours',
        'clinique_id',
        'type',
        'fonction',
        'commune',
        'ville'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'working_hours' => 'array',
        'insurance_accepted' => 'boolean',
        'languages' => 'array',
    ];

    // Relation avec la clinique principale
    public function clinique()
    {
        return $this->belongsTo(Clinique::class);
    }

    // Relation many-to-many avec les cliniques
    public function cliniques()
    {
        return $this->belongsToMany(Clinique::class, 'clinique_medecin')
            ->withPivot('fonction', 'created_at', 'updated_at')
            ->withTimestamps();
    }

    public function getNameAttribute()
    {
        return $this->nom . ' ' . $this->prenom;
    }

    // Relation avec les rendez-vous
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // Relation avec les favoris
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    // Relation avec les patients qui ont ce médecin en favori
    public function favoritedByPatients()
    {
        return $this->belongsToMany(Patient::class, 'favorites')
            ->withTimestamps();
    }

    // Vérifier si le médecin appartient à une clinique
    public function belongsToClinique($cliniqueId)
    {
        return $this->cliniques()->where('cliniques.id', $cliniqueId)->exists();
    }

    // Scope pour les médecins indépendants
    public function scopeIndependent($query)
    {
        return $query->where('type', 'independant');
    }

    // Scope pour les médecins en clinique
    public function scopeInClinique($query)
    {
        return $query->where('type', 'clinique');
    }

    // Vérifier si le médecin est indépendant
    public function isIndependent()
    {
        return $this->type === 'independant';
    }

    // Vérifier si le médecin travaille en clinique
    public function isInClinique()
    {
        return $this->type === 'clinique';
    }

    // Getter pour le nom complet
    public function getFullNameAttribute()
    {
        return "Dr. {$this->prenom} {$this->nom}";
    }

    // Getter pour l'URL de la photo de profil
    public function getPhotoUrlAttribute()
    {
        return $this->photo_profil ? asset('storage/' . $this->photo_profil) : null;
    }

    // Méthode pour ajouter le médecin à une clinique
    public function addToClinique($cliniqueId, $fonction = null)
    {
        if ($this->isIndependent()) {
            $this->update(['type' => 'clinique']);
        }

        return $this->cliniques()->attach($cliniqueId, [
            'fonction' => $fonction ?? 'Médecin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Méthode pour retirer le médecin d'une clinique
    public function removeFromClinique($cliniqueId)
    {
        $this->cliniques()->detach($cliniqueId);

        // Si le médecin n'est plus dans aucune clinique, le passer en indépendant
        if ($this->cliniques()->count() === 0) {
            $this->update(['type' => 'independant', 'clinique_id' => null]);
        }
    }

    // Méthode pour obtenir la clinique principale
    public function getMainCliniqueAttribute()
    {
        if ($this->isInClinique()) {
            return $this->clinique ?? $this->cliniques->first();
        }
        return null;
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function verifiedReviews()
    {
        return $this->hasMany(Review::class)->verified();
    }

    public function averageRating()
    {
        return $this->verifiedReviews()->avg('rating');
    }

    public function reviewsCount()
    {
        return $this->verifiedReviews()->count();
    }

     /**
     * Relation avec les ordonnances créées
     */
    public function ordonnances(): HasMany
    {
        return $this->hasMany(Ordonnance::class);
    }

    /**
     * Relation avec les arrêts maladie créés
     */
    public function arretsMaladie(): HasMany
    {
        return $this->hasMany(ArretMaladie::class);
    }

    /**
     * Relation avec les examens prescrits
     */
    public function examens(): HasMany
    {
        return $this->hasMany(Examen::class);
    }

    /**
     * Relation avec les partages de dossier médical (en tant que source)
     */
    public function medicalRecordSharesSent(): HasMany
    {
        return $this->hasMany(MedicalRecordShare::class, 'medecin_source_id');
    }

    /**
     * Relation avec les partages de dossier médical (en tant que destinataire)
     */
    public function medicalRecordSharesReceived(): HasMany
    {
        return $this->hasMany(MedicalRecordShare::class, 'medecin_dest_id');
    }
}
