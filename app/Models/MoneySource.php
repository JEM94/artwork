<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class MoneySource extends Model
{
    use HasFactory;
    use Searchable;

    protected $fillable = [
        'name',
        'amount',
        'start_date',
        'end_date',
        'source_name',
        'description',
        'is_group',
        'users',
        'group_id',
        'sub_money_source_id'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'is_group' => $this->is_group
        ];
    }
}
