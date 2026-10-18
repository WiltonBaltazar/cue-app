<?php

namespace App\Concerns;

use App\Support\AuditSanitizer;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit logging for models (FR-029, FR-035). Only changed attributes are logged; personal
 * fields and every `encrypted` cast are recorded as "alterado" without values; secrets
 * are never logged at all.
 */
trait LogsAuditActivity
{
    use LogsActivity;

    /**
     * Attributes never logged, not even as "changed".
     *
     * @var list<string>
     */
    private static array $auditSecretFields = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logOnly($this->auditAttributes())
            ->logExcept(self::$auditSecretFields)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = AuditSanitizer::sanitize($activity->properties ?? collect(), $this->auditPersonalFields());
    }

    /**
     * Attributes worth auditing; defaults to the fillable ones. Override to add guarded
     * attributes such as `role` or `status`.
     *
     * @return list<string>
     */
    protected function auditAttributes(): array
    {
        return array_values($this->getFillable());
    }

    /**
     * @return list<string>
     */
    protected function auditPersonalFields(): array
    {
        $encrypted = array_keys(array_filter(
            $this->getCasts(),
            fn (string $cast): bool => Str::startsWith($cast, 'encrypted'),
        ));

        return array_values(array_unique([...AuditSanitizer::PERSONAL_FIELDS, ...$encrypted]));
    }
}
