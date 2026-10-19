<?php

namespace App\Enums;

/**
 * What the schedule editor can do with several selected assignments at once.
 */
enum BulkAssignmentAction: string
{
    case Remove = 'remove';
    case Replace = 'replace';
    case Lock = 'lock';
    case Unlock = 'unlock';
}
