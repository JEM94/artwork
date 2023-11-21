<?php

namespace App\Models;

use Artwork\Modules\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Copyright extends Model
{
    use HasFactory;

    protected $fillable = [
        // Urheberrecht ja/nein
        'own_copyright',
        'live_music',
        // Verwertungsgesellschaft
        'collecting_society_id',
        // Großes oder kleines Recht
        'law_size',
        'project_id'
    ];

    protected $casts = [
        'own_copyright' => 'boolean',
        'live_music' => 'boolean'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function collecting_society()
    {
        return $this->belongsTo(CollectingSociety::class, 'collecting_society_id');
    }
}
