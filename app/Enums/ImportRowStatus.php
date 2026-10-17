<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case DRAFT = 'draft';
    case DISCARDED = 'discarded';
    case CONFIRMED = 'confirmed';
}
