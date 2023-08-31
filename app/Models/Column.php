<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $project_id
 * @property string $name
 * @property string $subName
 * @property string $type
 * @property int $linked_first_column
 * @property int $linked_second_column
 * @property string $color
 * @property boolean $is_locked
 */
class Column extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id',
        'name',
        'subName',
        'type',
        'linked_first_column',
        'linked_second_column',
        'color',
        'is_locked',
        'locked_by',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    protected $with = [
        'locked_by'
    ];

    public function subPositionRows(): BelongsToMany
    {
        return $this->belongsToMany(SubPositionRow::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function cells(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ColumnCell::class, 'column_id');
    }

    function subPositionSumDetails(): HasMany
    {
        return $this->hasMany(SubpositionSumDetail::class);
    }

    function mainPositionSumDetails(): HasMany
    {
        return $this->hasMany(MainPositionDetails::class);
    }

    function budgetSumDetails(): HasMany
    {
        return $this->hasMany(BudgetSumDetails::class);
    }

    public function locked_by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by', 'id');
    }
}
