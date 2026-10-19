<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use Database\Factories\TeamRoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A team service area ("Função"), e.g. Áudio or Streaming.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
class TeamRole extends Model
{
    /** @use HasFactory<TeamRoleFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<VolunteerSkill, $this>
     */
    public function volunteerSkills(): HasMany
    {
        return $this->hasMany(VolunteerSkill::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
