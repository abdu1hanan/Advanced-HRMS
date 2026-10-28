<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    public const STAFF = 'staff';

    public const GUEST = 'guest';

    protected $fillable = ['name', 'label'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function idByName(string $name): ?int
    {
        return static::query()->where('name', $name)->value('id');
    }
}
