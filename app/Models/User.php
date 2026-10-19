<?php

namespace App\Models;

use App\Concerns\LogsAuditActivity;
use App\Enums\UserRole;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string $password
 * @property UserRole $role
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Volunteer|null $volunteer
 */
#[Fillable(['name', 'phone', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, LogsAuditActivity, Notifiable, TwoFactorAuthenticatable;

    /**
     * Role is not mass assignable; it changes only through audited coordinator actions.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'volunteer',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Role is guarded, so add it explicitly: role changes must be audited (FR-035).
     *
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return ['name', 'phone', 'email', 'role'];
    }

    /**
     * Coordinators may also have a volunteer profile to be scheduled.
     *
     * @return HasOne<Volunteer, $this>
     */
    public function volunteer(): HasOne
    {
        return $this->hasOne(Volunteer::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Push is on unless the user switched this type off (FR-025).
     */
    public function wantsPush(string $type): bool
    {
        return $this->notificationPreferences()->where('type', $type)->value('push') ?? true;
    }

    public function isCoordinator(): bool
    {
        return $this->role === UserRole::Coordinator;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Store phone numbers normalised to E.164 so login and uniqueness compare like with like.
     *
     * @return Attribute<string, string>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Phone::normalize($value) ?? $value,
        );
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => $value === null || trim($value) === '' ? null : mb_strtolower(trim($value)),
        );
    }
}
