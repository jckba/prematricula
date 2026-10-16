<?php

namespace App\Models;

use App\Enums\PreEnrollmentWindowType;
use Database\Factories\PreEnrollmentWindowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['academic_period_id', 'tipo', 'fecha_inicio', 'fecha_fin', 'motivo', 'timezone', 'origen', 'created_by'])]
class PreEnrollmentWindow extends Model
{
    /** @use HasFactory<PreEnrollmentWindowFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:sP';

    protected function casts(): array
    {
        return ['tipo' => PreEnrollmentWindowType::class, 'fecha_inicio' => 'immutable_datetime', 'fecha_fin' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las ventanas registradas son inmutables.'));
        static::deleting(fn () => throw new LogicException('Las ventanas registradas no se eliminan.'));
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
