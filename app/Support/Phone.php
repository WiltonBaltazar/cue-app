<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Phone numbers are the login username and are stored in E.164 (e.g. +258841234567).
 */
class Phone
{
    public const DEFAULT_REGION = 'MZ';

    /**
     * Normalise user input to E.164, or null when it is not a valid number.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        try {
            $phone = new PhoneNumber($input, self::DEFAULT_REGION);

            return $phone->isValid() ? $phone->formatE164() : null;
        } catch (NumberParseException) {
            return null;
        }
    }
}
