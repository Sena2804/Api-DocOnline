<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medicament extends Model
{
    use HasFactory;

    protected $fillable = [
        'ordonnance_id',
        'nom',
        'dosage',
        'posologie',
        'duree',
        'quantite',
        'instructions',
    ];

    /**
     * Relation avec l'ordonnance
     */
    public function ordonnance(): BelongsTo
    {
        return $this->belongsTo(Ordonnance::class);
    }
}