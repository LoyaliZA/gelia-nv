<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\ClienteDireccion;
use App\Models\EnlaceDireccion;
use App\Models\SolicitudDireccion;
use App\Models\User;
use App\Notifications\AlertaDireccion;
use App\Support\Clientes\Direcciones\SanitizarEntradaDireccionPublica;
use Illuminate\Support\Facades\DB;

class AplicarDireccionPublicaDesdeEnlaceService
{
    public function __construct(
        private ValidarEnlaceDireccionService $validador,
        private GestionDireccionesClienteService $gestion,
    ) {}

    /**
     * @param  array<string, mixed>  $datosDireccion
     */
    public function ejecutar(string $token, array $datosDireccion): ClienteDireccion
    {
        $resultado = DB::transaction(function () use ($token, $datosDireccion) {
            $enlace = $this->reclamarEnlace($token);
            $datosDireccion = SanitizarEntradaDireccionPublica::ejecutar($datosDireccion);

            $accion = $enlace->accion_permitida;
            $clienteId = (int) $enlace->cliente_id;

            $ctx = [
                'usuario_id' => $enlace->creado_por,
                'origen' => ClienteDireccion::ORIGEN_PUBLIC_FORM,
                'verificar' => true,
            ];

            $direccion = match ($accion) {
                SolicitudDireccion::ACCION_ACTUALIZAR => $this->actualizarDireccion($enlace, $clienteId, $datosDireccion, $ctx),
                SolicitudDireccion::ACCION_PRIMERA => $this->crearPrimera($clienteId, $datosDireccion, $ctx),
                default => $this->gestion->crearDireccionAdicional($clienteId, $datosDireccion, $ctx),
            };

            return [$enlace, $direccion];
        });

        [$enlace, $direccion] = $resultado;
        $this->notificarVendedora($enlace, $direccion);

        return $direccion;
    }

    private function reclamarEnlace(string $token): EnlaceDireccion
    {
        $enlace = $this->validador->porToken($token);

        if (! $enlace) {
            throw new \InvalidArgumentException('Enlace no válido.');
        }

        $enlace = EnlaceDireccion::query()
            ->whereKey($enlace->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($enlace->fueUsado()) {
            throw new \InvalidArgumentException('Este enlace ya fue utilizado.');
        }

        if ($enlace->revocado_en !== null || ($enlace->expira_en !== null && $enlace->expira_en->isPast())) {
            throw new \InvalidArgumentException('El enlace expiró o fue revocado.');
        }

        if (! filled($enlace->accion_permitida)) {
            throw new \InvalidArgumentException('Este enlace no permite registro directo.');
        }

        // Cierra el token de inmediato dentro de la misma transacción (un solo uso).
        $enlace->update(['usado_en' => now()]);

        return $enlace->fresh();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, verificar?: bool}  $ctx
     */
    private function actualizarDireccion(EnlaceDireccion $enlace, int $clienteId, array $datos, array $ctx): ClienteDireccion
    {
        $direccionId = $enlace->direccion_id;

        if (! $direccionId) {
            throw new \InvalidArgumentException('El enlace de actualización no tiene dirección vinculada.');
        }

        $direccion = ClienteDireccion::query()
            ->where('cliente_id', $clienteId)
            ->whereKey($direccionId)
            ->activas()
            ->first();

        if (! $direccion) {
            throw new \InvalidArgumentException('La dirección a actualizar no está disponible.');
        }

        return $this->gestion->actualizarDireccionInPlace($direccion->id, $datos, $ctx);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{usuario_id?: int|null, origen?: string|null, verificar?: bool}  $ctx
     */
    private function crearPrimera(int $clienteId, array $datos, array $ctx): ClienteDireccion
    {
        $existentes = ClienteDireccion::query()
            ->where('cliente_id', $clienteId)
            ->activas()
            ->count();

        if ($existentes > 0) {
            return $this->gestion->crearDireccionAdicional($clienteId, $datos, $ctx);
        }

        return $this->gestion->crearPrimeraDireccion($clienteId, $datos, $ctx);
    }

    private function notificarVendedora(EnlaceDireccion $enlace, ClienteDireccion $direccion): void
    {
        $enlace->loadMissing(['cliente.vendedor']);

        $destinatario = $enlace->cliente?->vendedor;
        if (! $destinatario && $enlace->creado_por) {
            $destinatario = User::query()->find($enlace->creado_por);
        }

        if (! $destinatario) {
            return;
        }

        $cliente = $enlace->cliente;
        if (! $cliente) {
            return;
        }

        $destinatario->notify(new AlertaDireccion(
            $cliente,
            $direccion,
            'direccion_formulario_respondido',
            'El cliente completó el formulario de dirección de envío.',
        ));
    }
}
