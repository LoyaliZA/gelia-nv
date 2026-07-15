<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Support\Collection;

class DetectorDireccionDuplicada
{
    public function __construct(
        private NormalizadorDireccion $normalizador,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return Collection<int, ClienteDireccion>
     */
    public function ejecutar(int $clienteId, array $datos, ?int $excluirDireccionId = null): Collection
    {
        $claveNueva = $this->normalizador->representacionComparable($datos);

        if ($claveNueva === '|||||' || trim(str_replace('|', '', $claveNueva)) === '') {
            return collect();
        }

        $query = ClienteDireccion::query()
            ->where('cliente_id', $clienteId)
            ->where('esta_activa', true);

        if ($excluirDireccionId) {
            $query->where('id', '!=', $excluirDireccionId);
        }

        return $query->get()->filter(function (ClienteDireccion $existente) use ($claveNueva) {
            return $this->normalizador->representacionComparable($existente->toArray()) === $claveNueva;
        })->values();
    }

    public function existe(int $clienteId, array $datos, ?int $excluirDireccionId = null): bool
    {
        return $this->ejecutar($clienteId, $datos, $excluirDireccionId)->isNotEmpty();
    }
}
