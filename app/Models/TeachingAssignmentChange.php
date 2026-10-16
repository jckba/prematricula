<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

#[Fillable(['actor_id', 'accion', 'before', 'after', 'created_at'])]
class TeachingAssignmentChange extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La auditoría de asignaciones es inmutable.'));
        static::deleting(fn () => throw new LogicException('La auditoría de asignaciones es inmutable.'));
    }
}
