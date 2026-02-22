<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'encrypted_key',
        'is_active',
        'last_verified_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_verified_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'encrypted_key',
    ];

    public function setKeyAttribute(string $value): void
    {
        $this->attributes['encrypted_key'] = Crypt::encryptString($value);
    }

    public function getDecryptedKeyAttribute(): string
    {
        return Crypt::decryptString($this->encrypted_key);
    }
}
