<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SucursalUser extends Pivot
{
    protected $table = 'sucursal_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
