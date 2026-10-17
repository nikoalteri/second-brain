<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The column mapping last used for a target, offered again on its next import. */
class ImportMapping extends Model
{
    protected $fillable = ['user_id', 'account_id', 'credit_card_id', 'mapping'];

    protected $casts = ['mapping' => 'array'];
}
