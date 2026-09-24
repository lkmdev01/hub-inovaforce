<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'status', 'ran_at', 'details', 'error_message'])]
/**
 * @property string $name
 * @property string $status
 * @property Carbon $ran_at
 * @property array<string, mixed>|null $details
 * @property string|null $error_message
 */
class SystemRun extends Model
{
    protected function casts(): array
    {
        return ['ran_at' => 'datetime', 'details' => 'array'];
    }
}
