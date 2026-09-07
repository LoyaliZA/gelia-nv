<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUsuario
{
    protected function belongsToUsuario(string $foreignKey, ?string $ownerKey = null): BelongsTo
    {
        return $this->belongsTo(User::class, $foreignKey, $ownerKey)->withTrashed();
    }
}
