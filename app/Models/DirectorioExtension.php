<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectorioExtension extends Model
{
    use SoftDeletes;

    protected $table = 'directorio_extensiones';
    
    protected $fillable = [
        'area_id',
        'rh_colaborador_id',
        'extension',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function encargado()
    {
        return $this->belongsTo(RhColaborador::class, 'rh_colaborador_id');
    }
}
