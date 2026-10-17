<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case DRAFT = 'draft';
    /** Set by the system for header and preamble lines; re-mapping can bring them back. */
    case SKIPPED = 'skipped';
    /** Set by the owner; never changed by re-mapping. */
    case DISCARDED = 'discarded';
    case CONFIRMED = 'confirmed';
}
