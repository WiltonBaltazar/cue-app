<?php

namespace App\Actions\Volunteers;

use App\Actions\Registration\AnonymizeVolunteer;
use App\Models\Volunteer;
use Illuminate\Validation\ValidationException;

/**
 * A coordinator deletes a volunteer for good (FR-005b): same result as a data-removal
 * request, without the 7 days. Coordinators lose the role first, so nobody deletes
 * themself and the app never runs out of coordinators.
 */
class DeleteVolunteer
{
    public function __construct(private AnonymizeVolunteer $anonymize) {}

    /**
     * @return int published shifts flagged for replacement
     */
    public function handle(Volunteer $volunteer): int
    {
        if ($volunteer->user?->isCoordinator() ?? false) {
            throw ValidationException::withMessages([
                'volunteer' => 'Esta pessoa é coordenadora. Retire-lhe primeiro o papel de coordenador.',
            ]);
        }

        return $this->anonymize->handle($volunteer);
    }
}
