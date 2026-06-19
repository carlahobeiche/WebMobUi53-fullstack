<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{

// Laravel bloque par défaut l'écriture automatique en base de données.
// On déclare 'label' comme autorisé pour pouvoir créer des options avec ->create(['label' => '...'])
protected $fillable = ['label'];
//donc wn gros c'est pour dire à Laravel que le champ label est autorisé à être rempli avec create()
    
    /**
     * Get the poll that owns the option.
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * Get the votes for this option.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}

//C'est quoi ? Un modèle = la représentation d'une table en base de données. Ce fichier représente les options d'un sondage ("Option A", "Option B"...)