<?php

namespace App\Support;

use App\Enums\Period;
use App\Models\Availability;
use App\Models\Volunteer;
use App\Models\VolunteerSkill;

/**
 * The single place that decides which volunteer fields each audience receives (FR-026,
 * FR-031). `full()` contains sensitive data: only for coordinators and the owner.
 */
class VolunteerPresenter
{
    /**
     * List rows for coordinators: no sensitive fields.
     *
     * @return array<string, mixed>
     */
    public static function summary(Volunteer $volunteer): array
    {
        return [
            'id' => $volunteer->id,
            'fullName' => $volunteer->full_name,
            'status' => $volunteer->status->value,
            'statusLabel' => $volunteer->status->label(),
            'inactiveUntil' => $volunteer->inactive_until?->toDateString(),
            'isMinor' => $volunteer->is_minor,
            'previouslyRejectedAt' => $volunteer->previously_rejected_at?->toIso8601String(),
            'submittedAt' => $volunteer->created_at?->toIso8601String(),
            'roles' => $volunteer->skills->map(fn (VolunteerSkill $skill) => $skill->teamRole->name)->values(),
        ];
    }

    /**
     * Full profile including sensitive fields. Callers must have passed the
     * `viewSensitive` policy check.
     *
     * @return array<string, mixed>
     */
    public static function full(Volunteer $volunteer): array
    {
        return [
            ...self::summary($volunteer),
            'phone' => $volunteer->user?->phone,
            'email' => $volunteer->user?->email,
            'birthDate' => $volunteer->birthDate()->toDateString(),
            'age' => $volunteer->age(),
            'neighbourhood' => $volunteer->neighbourhood,
            'guardianName' => $volunteer->guardian_name,
            'guardianPhone' => $volunteer->guardian_phone,
            'guardianConsent' => $volunteer->guardian_consent_confirmed_at === null ? null : [
                'confirmedAt' => $volunteer->guardian_consent_confirmed_at->toIso8601String(),
                'method' => $volunteer->guardian_consent_method?->label(),
            ],
            'educationLevel' => $volunteer->education_level,
            'training' => $volunteer->training,
            'commitments' => $volunteer->commitments,
            'motivations' => $volunteer->motivations,
            'comments' => $volunteer->comments,
            'maxFrequency' => $volunteer->max_frequency->value,
            'maxFrequencyLabel' => $volunteer->max_frequency->label(),
            'variableHours' => $volunteer->variable_hours,
            'availableForTraining' => $volunteer->available_for_training,
            'canSupportOthers' => $volunteer->can_support_others,
            'reviewNotes' => $volunteer->review_notes,
            'reviewedAt' => $volunteer->reviewed_at?->toIso8601String(),
            'skills' => $volunteer->skills->map(fn (VolunteerSkill $skill) => [
                'teamRoleId' => $skill->team_role_id,
                'name' => $skill->teamRole->name,
                'hasExperience' => $skill->has_experience,
                'level' => $skill->level->value,
                'isPreferred' => $skill->is_preferred,
            ])->values(),
            'availability' => $volunteer->availabilities
                ->sortBy(fn (Availability $slot) => $slot->weekday * 10 + array_search($slot->period, Period::cases(), true))
                ->map(fn (Availability $slot) => [
                    'weekday' => $slot->weekday,
                    'period' => $slot->period->value,
                    'label' => Availability::WEEKDAYS[$slot->weekday].' · '.$slot->period->label(),
                ])->values(),
        ];
    }
}
