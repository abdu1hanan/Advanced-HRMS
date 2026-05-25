<?php

namespace App\Models;

use App\Services\HierarchyService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'head_user_id'];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @alias users */
    public function employees(): HasMany
    {
        return $this->users();
    }

    protected static function booted(): void
    {
        static::updated(function (Department $department) {
            if ($department->wasChanged('head_user_id') && $department->head_user_id) {
                app(HierarchyService::class)->backfillManagersForDepartment($department);
            }
        });
    }
}
