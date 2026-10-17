<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case UPLOADED = 'uploaded';
    case MAPPED = 'mapped';
    case COMPLETED = 'completed';
    case DISCARDED = 'discarded';
}
