<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Keeps personal data out of the audit log (FR-029, research R15): personal fields are
 * recorded as changed without their values, so audit rows never need rewriting when a
 * volunteer is anonymised.
 */
class AuditSanitizer
{
    public const CHANGED = 'alterado';

    /**
     * Personal fields that are never stored with values, on any model.
     *
     * @var list<string>
     */
    public const PERSONAL_FIELDS = [
        'name',
        'full_name',
        'phone',
        'email',
        'guardian_name',
        'guardian_phone',
    ];

    /**
     * Replace personal values in activity properties ("attributes" / "old") with "alterado".
     *
     * @param  Collection<string, mixed>  $properties
     * @param  list<string>  $personalFields
     * @return Collection<string, mixed>
     */
    public static function sanitize(Collection $properties, array $personalFields): Collection
    {
        return $properties->map(function (mixed $values, string $section) use ($personalFields): mixed {
            if (! in_array($section, ['attributes', 'old'], true) || ! is_array($values)) {
                return $values;
            }

            foreach ($personalFields as $field) {
                if (array_key_exists($field, $values)) {
                    $values[$field] = self::CHANGED;
                }
            }

            return $values;
        });
    }
}
