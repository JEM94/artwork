<?php

namespace App\Models;

use Artwork\Modules\Project\Models\Project;
use Artwork\Modules\Project\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $text
 * @property int $project_id
 * @property int $project_file_id
 * @property int $money_source_file_id
 * @property int $contract_id
 * @property int $user_id
 * @property string $created_at
 * @property string $updated_at
 */
class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'text',
        'project_id',
        'project_file_id',
        'money_source_file_id',
        'contract_id',
        'user_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    //@todo: fix phpcs error - refactor function name to projectFile
    //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function project_file(): BelongsTo
    {
        return $this->belongsTo(ProjectFile::class, 'project_file_id');
    }

    //@todo: fix phpcs error - refactor function name to moneySourceFile
    //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function money_source_file(): BelongsTo
    {
        return $this->belongsTo(MoneySourceFile::class, 'money_source_file_id');
    }
}
