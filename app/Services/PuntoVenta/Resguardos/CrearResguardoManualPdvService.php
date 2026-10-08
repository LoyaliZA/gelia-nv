<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\RegistroManualResguardoPdvCreado;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\DepartamentosOrigenResguardoManualPdv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CrearResguardoManualPdvService
{
    public const HANDOFF = 'manual';

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly RegistroManualResguardoPdvConfig $config,
        private readonly IngresarResguardoManualPdvService $ingresar,
    ) {}

    public function ejecutar(User $actor, array $datos): ResguardoPdv
    {
        if (! $this->config->estaActivoPara($actor)) {
            throw new AuthorizationException('El registro manual de resguardos no está habilitado.');
        }

        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw new AuthorizationException('No hay sucursal activa para registrar el resguardo.');
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            $sucursalId
        );

        $idempotencyKey = (string) $datos['idempotency_key'];
        $folio = trim((string) $datos['folio']);
        $cantidadBultos = (int) $datos['cantidad_bultos_esperada'];
        $clienteId = (int) $datos['cliente_id'];
        $origenId = (int) $datos['origen_id'];
        $enviaTercero = (bool) ($datos['envia_a_otra_persona'] ?? false);
        $nombreTercero = $enviaTercero
            ? trim((string) ($datos['envia_otra_persona'] ?? ''))
            : '';
        $observaciones = $datos['observaciones'] ?? null;
        $cantidadPiezas = $datos['cantidad_piezas'] ?? null;
        $piezas = is_array($datos['piezas'] ?? null) ? $datos['piezas'] : [];
        $this->assertAlcanceDemo($actor, $sucursalId, $datos, $piezas);
        $archivoTicket = $datos['archivo_ticket'] ?? null;
        $fotoPaquete = $datos['foto_paquete'] ?? null;

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $actor,
                $sucursalId,
                $idempotencyKey,
                $folio,
                $cantidadBultos,
                $clienteId,
                $origenId,
                $enviaTercero,
                $nombreTercero,
                $observaciones,
                $cantidadPiezas,
                $piezas,
                $archivoTicket,
                $fotoPaquete,
                &$pathsEscritos,
            ) {
                $existente = $this->resguardoPorIdempotencia($idempotencyKey);
                if ($existente !== null) {
                    return $existente;
                }

                $this->assertFolioAbiertoUnico($sucursalId, $folio);

                $cliente = Cliente::query()->find($clienteId);
                if (! $cliente instanceof Cliente) {
                    throw ValidationException::withMessages([
                        'cliente_id' => 'Seleccione una cuenta de cliente válida.',
                    ]);
                }

                $origen = DepartamentosOrigenResguardoManualPdv::encontrarActivo($origenId);
                if ($origen === null) {
                    throw ValidationException::withMessages([
                        'origen_id' => 'Seleccione un área de origen válida.',
                    ]);
                }

                $ahora = now();

                try {
                    $resguardo = ResguardoPdv::query()->create([
                        'pedido_bma_id' => null,
                        'cliente_id' => $cliente->id,
                        'sucursal_id' => $sucursalId,
                        'almacen_id' => null,
                        'estado' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                        'cantidad_bultos_esperada' => $cantidadBultos,
                        'salida_cedis_at' => $ahora,
                        'es_demo' => (bool) $actor->es_demo,
                        'snapshot_folio' => $folio,
                        'snapshot_cliente_nombre' => $cliente->nombre,
                        'snapshot_json' => [
                            'pedido_bma_id' => null,
                            'folio' => $folio,
                            'folio_remision' => $folio,
                            'sucursal_id' => $sucursalId,
                            'handoff' => self::HANDOFF,
                            'envia_a_otra_persona' => $enviaTercero,
                            'envia_otra_persona' => $enviaTercero ? $nombreTercero : null,
                            'origen_id' => $origen->id,
                            'origen_nombre' => $origen->nombre,
                            'departamento_id' => $origen->id,
                            'departamento_nombre' => $origen->nombre,
                            'registrado_por_id' => $actor->id,
                            'registrado_por_nombre' => trim((string) $actor->name) ?: null,
                            'observaciones' => $observaciones,
                            'cantidad_piezas' => $cantidadPiezas,
                            'piezas' => $piezas,
                        ],
                        'version' => 1,
                    ]);

                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'tipo_evento' => ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO,
                        'estado_anterior' => null,
                        'estado_nuevo' => ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => [
                            'sucursal_id' => $sucursalId,
                            'handoff' => self::HANDOFF,
                            'cliente_id' => $cliente->id,
                            'folio' => $folio,
                            'cantidad_bultos_esperada' => $cantidadBultos,
                            'origen_id' => $origen->id,
                            'departamento_id' => $origen->id,
                            'cantidad_piezas' => $cantidadPiezas,
                        ],
                        'idempotency_key' => $idempotencyKey,
                    ]);

                    if ($archivoTicket instanceof UploadedFile) {
                        $this->ingresar->guardar(
                            $resguardo,
                            $evento,
                            $archivoTicket,
                            ResguardoPdvEvidencia::USO_TICKET,
                            $actor->id,
                            $pathsEscritos
                        );
                    }
                    if ($fotoPaquete instanceof UploadedFile) {
                        $this->ingresar->guardar(
                            $resguardo,
                            $evento,
                            $fotoPaquete,
                            ResguardoPdvEvidencia::USO_PAQUETE,
                            $actor->id,
                            $pathsEscritos
                        );
                    }

                    $resguardo = $this->ingresar->completar($resguardo->fresh(), $actor, $idempotencyKey);

                    RegistroManualResguardoPdvCreado::dispatch($resguardo, $evento, $sucursalId);

                    return $resguardo;
                } catch (UniqueConstraintViolationException $e) {
                    $this->eliminarArchivosHuerfanos($pathsEscritos);
                    $pathsEscritos = [];
                    $recuperado = $this->resguardoPorIdempotencia($idempotencyKey);
                    if ($recuperado !== null) {
                        return $recuperado;
                    }

                    throw $e;
                }
            });
        } catch (\Throwable $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    private function eliminarArchivosHuerfanos(array $pathsEscritos): void
    {
        foreach ($pathsEscritos as $ruta) {
            Storage::disk('local')->delete($ruta);
        }
    }

    private function resguardoPorIdempotencia(string $clave): ?ResguardoPdv
    {
        $evento = ResguardoPdvEvento::query()
            ->where('idempotency_key', $clave)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO)
            ->first();

        if ($evento === null) {
            return null;
        }

        return ResguardoPdv::query()->find($evento->resguardo_id);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array<string, mixed>>  $piezas
     */
    private function assertAlcanceDemo(User $actor, int $sucursalId, array $datos, array $piezas): void
    {
        if (! $actor->es_demo) {
            return;
        }

        if (isset($datos['sucursal_id']) && (int) $datos['sucursal_id'] !== $sucursalId) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'La sucursal no corresponde al alcance de la cuenta.',
            ]);
        }

        $sucursalDemo = Sucursal::query()->whereKey($sucursalId)->where('es_demo', true)->exists();
        if (! $sucursalDemo) {
            throw ValidationException::withMessages([
                'sucursal_id' => 'La sucursal no corresponde al alcance de la cuenta.',
            ]);
        }

        foreach ($piezas as $pieza) {
            $productoId = (int) ($pieza['producto_id'] ?? 0);
            if ($productoId < 1 || ! Producto::query()->whereKey($productoId)->where('es_demo', true)->exists()) {
                throw ValidationException::withMessages([
                    'piezas' => 'Seleccione productos del catálogo de demostración.',
                ]);
            }
        }
    }

    private function assertFolioAbiertoUnico(int $sucursalId, string $folio): void
    {
        $duplicado = ResguardoPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->where('snapshot_folio', $folio)
            ->whereNotIn('estado', [
                ResguardoPdv::ESTADO_ENTREGADO,
                ResguardoPdv::ESTADO_DEVUELTO,
            ])
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages([
                'folio' => 'Ya existe un resguardo abierto con ese folio en esta sucursal.',
            ]);
        }
    }
}
