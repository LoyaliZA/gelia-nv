<?php
namespace App\Support\ControlPedidos;

final class HuellaSolicitudPedido
{
    public static function de(mixed $valor): string
    {
        return hash('sha256', json_encode(self::ordenar($valor), JSON_THROW_ON_ERROR));
    }
    private static function ordenar(mixed $valor): mixed
    {
        if ($valor instanceof \Illuminate\Http\UploadedFile) return ['archivo' => hash_file('sha256', $valor->getRealPath()), 'nombre' => $valor->getClientOriginalName()];
        if (!is_array($valor)) return $valor;
        if (!array_is_list($valor)) ksort($valor);
        return array_map(self::ordenar(...), $valor);
    }
}
