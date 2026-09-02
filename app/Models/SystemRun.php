<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'status', 'ran_at', 'details', 'error_message'])]
class SystemRun extends Model
{
    protected function casts(): array
    {
        return ['ran_at' => 'datetime', 'details' => 'array'];
    }
}
