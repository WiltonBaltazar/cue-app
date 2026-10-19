<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\Frequency;
use App\Enums\GuardianConsentMethod;
use App\Enums\VolunteerStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VolunteerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Volunteer profile (data-model.md). Fields with an `encrypted` cast are sensitive:
 * visible only to coordinators and the volunteer (FR-026), never logged (FR-029).
 *
 * @property int $id
 * @property int|null $user_id
 * @property VolunteerStatus $status
 * @property string $full_name
 * @property string|null $birth_date Y-m-d (encrypted); null once anonymised
 * @property int|null $birth_year
 * @property bool $is_minor
 * @property string|null $guardian_name
 * @property string|null $guardian_phone
 * @property string|null $neighbourhood
 * @property string|null $education_level
 * @property string|null $training
 * @property string|null $commitments
 * @property string|null $motivations
 * @property string|null $comments
 * @property Frequency $max_frequency
 * @property bool $variable_hours
 * @property bool $available_for_training
 * @property bool $can_support_others
 * @property CarbonImmutable $consent_accepted_at
 * @property int $consent_version
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_notes
 * @property CarbonImmutable|null $previously_rejected_at
 * @property int|null $guardian_consent_confirmed_by
 * @property CarbonImmutable|null $guardian_consent_confirmed_at
 * @property GuardianConsentMethod|null $guardian_consent_method
 * @property CarbonImmutable|null $inactive_since
 * @property CarbonImmutable|null $inactive_until
 * @property int|null $paused_by
 * @property CarbonImmutable|null $removal_requested_at
 * @property CarbonImmutable|null $removal_scheduled_for
 * @property CarbonImmutable|null $anonymized_at
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $user
 */
class Volunteer extends Model
{
    /** @use HasFactory<VolunteerFactory> */
    use HasFactory, LogsAuditActivity;

    public const MINIMUM_AGE = 14;

    public const ADULT_AGE = 18;

    protected $fillable = [
        'user_id',
        'full_name',
        'birth_date',
        'guardian_name',
        'guardian_phone',
        'neighbourhood',
        'education_level',
        'training',
        'commitments',
        'motivations',
        'comments',
        'max_frequency',
        'variable_hours',
        'available_for_training',
        'can_support_others',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VolunteerStatus::class,
            'birth_date' => 'encrypted',
            'birth_year' => 'integer',
            'is_minor' => 'boolean',
            'guardian_name' => 'encrypted',
            'guardian_phone' => 'encrypted',
            'neighbourhood' => 'encrypted',
            'education_level' => 'encrypted',
            'training' => 'encrypted',
            'commitments' => 'encrypted',
            'motivations' => 'encrypted',
            'comments' => 'encrypted',
            'max_frequency' => Frequency::class,
            'variable_hours' => 'boolean',
            'available_for_training' => 'boolean',
            'can_support_others' => 'boolean',
            'consent_accepted_at' => 'datetime',
            'consent_version' => 'integer',
            'reviewed_at' => 'datetime',
            'previously_rejected_at' => 'datetime',
            'guardian_consent_confirmed_at' => 'datetime',
            'guardian_consent_method' => GuardianConsentMethod::class,
            'inactive_since' => 'date',
            'inactive_until' => 'date',
            'removal_requested_at' => 'datetime',
            'removal_scheduled_for' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the plain-text derived fields in step with the encrypted birth date.
        static::saving(function (self $volunteer): void {
            if ($volunteer->isDirty('birth_date')) {
                $volunteer->birth_year = $volunteer->birth_date === null ? null : $volunteer->birthDate()->year;
                $volunteer->is_minor = $volunteer->birth_date !== null && $volunteer->age() < self::ADULT_AGE;
            }
        });
    }

    /**
     * Status, review and guardian-consent changes are audited; personal values never are.
     *
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return [
            ...array_values($this->getFillable()),
            'status',
            'consent_version',
            'reviewed_by',
            'review_notes',
            'guardian_consent_confirmed_by',
            'guardian_consent_method',
            'inactive_since',
            'inactive_until',
            'paused_by',
            'removal_scheduled_for',
        ];
    }

    /**
     * Coordinator notes may describe the person, so they are never logged with values.
     *
     * @return list<string>
     */
    protected function auditPersonalFields(): array
    {
        return [...$this->defaultAuditPersonalFields(), 'review_notes', 'birth_date'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<VolunteerSkill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(VolunteerSkill::class);
    }

    /**
     * @return HasMany<Availability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->whereIn('status', [VolunteerStatus::Pending, VolunteerStatus::NeedsCorrection]);
    }

    /**
     * Active and not leaving: a pending data-removal request takes a volunteer out of
     * scheduling straight away (FR-028).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSchedulable(Builder $query): void
    {
        $query->where('status', VolunteerStatus::Active)->whereNull('removal_requested_at');
    }

    public function isSchedulable(): bool
    {
        return $this->status->isSchedulable() && $this->removal_requested_at === null;
    }

    public function isAnonymized(): bool
    {
        return $this->status === VolunteerStatus::Anonymized;
    }

    /**
     * The only name shown for an anonymised volunteer, in history and the audit log (FR-029).
     */
    public static function anonymousName(int $id): string
    {
        return "Voluntário #{$id}";
    }

    /**
     * @return HasMany<Absence, $this>
     */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function birthDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->birth_date);
    }

    public function age(?CarbonImmutable $on = null): int
    {
        return (int) $this->birthDate()->diffInYears($on ?? CarbonImmutable::now(config('app.display_timezone')));
    }

    /**
     * "Ana Machava" → "Ana M."; the only form shown to colleagues (FR-027).
     */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? end($parts) : null;

        return $last === null ? $first : $first.' '.Str::upper(Str::substr($last, 0, 1)).'.';
    }

    public function hasGuardianConsent(): bool
    {
        return $this->guardian_consent_confirmed_at !== null;
    }
}
