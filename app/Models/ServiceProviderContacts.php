<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_provider_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone_number
 * @property string $created_at
 * @property string $updated_at
 */
class ServiceProviderContacts extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_provider_id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
    ];

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
