<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListadoDestinatario extends Model
{
    use HasFactory;

    protected $table = 'listado_destinatarios';

    protected $fillable = [
        'tipo_lista',
        'user_id',
        'nombre',
        'email',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function esExterno(): bool
    {
        return $this->user_id === null && !empty($this->email);
    }
}
