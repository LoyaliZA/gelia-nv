<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\Cliente;
use App\Models\ClienteDireccion;
use Illuminate\Support\Facades\DB;

class SincronizarDireccionPrincipalConContactoService
{
    public function __construct(
        private ServicioAuditoriaDireccion $auditoria,
    ) {}

    /**
     * Dual-write controlado: solo dirección principal activa/verificada → *_contacto.
     *
     * @param  array{usuario_id?: int|null, origen?: string|null}  $contexto
     */
    public function ejecutar(int $clienteId, array $contexto = []): Cliente
    {
        return DB::transaction(function () use ($clienteId, $contexto) {
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($clienteId);

            $principal = ClienteDireccion::query()
                ->where('cliente_id', $clienteId)
                ->where('es_principal', true)
                ->where('esta_activa', true)
                ->orderByDesc('version')
                ->first();

            if (! $principal) {
                return $cliente;
            }

            $anteriores = $cliente->only([
                'direccion_contacto',
                'colonia_contacto',
                'municipio_contacto',
                'estado_contacto',
                'pais_contacto',
                'cp_contacto',
                'telefono',
            ]);

            $calleCompleta = trim(implode(' ', array_filter([
                $principal->calle,
                $principal->numero_exterior ? '#'.$principal->numero_exterior : null,
                $principal->numero_interior ? 'Int. '.$principal->numero_interior : null,
            ])));

            $nuevos = [
                'direccion_contacto' => $calleCompleta !== '' ? $calleCompleta : $principal->calle,
                'colonia_contacto' => $principal->colonia,
                'municipio_contacto' => $principal->municipio ?: $principal->ciudad,
                'estado_contacto' => $principal->estado,
                'pais_contacto' => $principal->pais,
                'cp_contacto' => $principal->codigo_postal,
            ];

            if (filled($principal->telefono_destinatario)) {
                $nuevos['telefono'] = $principal->telefono_destinatario;
            }

            $cliente->update($nuevos);

            $this->auditoria->ejecutar(
                $clienteId,
                'sincronizar_contacto',
                $contexto['usuario_id'] ?? null,
                $principal->id,
                null,
                $anteriores,
                $nuevos,
                $contexto['origen'] ?? 'dual_write',
            );

            return $cliente->fresh();
        });
    }
}
