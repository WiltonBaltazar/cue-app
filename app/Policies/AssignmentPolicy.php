<?php

namespace App\Policies;

/**
 * Only coordinators create or change assignments (FR-013); volunteers act on their own
 * shifts through declines and swaps (User Story 4).
 */
class AssignmentPolicy extends CoordinatorOnlyPolicy {}
