<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\Cliente;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
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
    ) {}

    public function ejecutar(User $actor, array $datos): ResguardoPdv
    {
        if (! $this->config->estaActivo()) {
            throw new AuthorizationException('El registro manual de resguardos no está habilitado.');
        }

        $sucursalId = $this->alcance->sucursalActivaId($actor);
        if ($sucursalId === null) {
            throw new AuthorizationException('No hay sucursal activa para registrar el resguardo.');
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR_GERENTE,
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
                        'origen_id' => 'Seleccione un área de origen válida (Aromas o Bellaroma).',
                    ]);
                }

                if (! $archivoTicket instanceof UploadedFile || ! $fotoPaquete instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'archivo_ticket' => 'Adjunte el ticket y la foto del paquete.',
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

                    $this->guardarEvidencia(
                        $resguardo,
                        $evento,
                        $archivoTicket,
                        ResguardoPdvEvidencia::USO_TICKET,
                        $actor->id,
                        $ahora,
                        $pathsEscritos
                    );
                    $this->guardarEvidencia(
                        $resguardo,
                        $evento,
                        $fotoPaquete,
                        ResguardoPdvEvidencia::USO_PAQUETE,
                        $actor->id,
                        $ahora,
                        $pathsEscritos
                    );

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
    private function guardarEvidencia(
        ResguardoPdv $resguardo,
        ResguardoPdvEvento $evento,
        UploadedFile $archivo,
        string $uso,
        int $actorId,
        \Illuminate\Support\Carbon $capturadoAt,
        array &$pathsEscritos,
    ): void {
        $ruta = $archivo->store("pdv/resguardos/{$resguardo->id}/registro-manual", 'local');
        $pathsEscritos[] = $ruta;

        $mime = (string) $archivo->getMimeType();
        $esPdf = str_contains(strtolower($mime), 'pdf')
            || strtolower((string) $archivo->getClientOriginalExtension()) === 'pdf';

        ResguardoPdvEvidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'evento_id' => $evento->id,
            'tipo' => $esPdf ? ResguardoPdvEvidencia::TIPO_ARCHIVO : ResguardoPdvEvidencia::TIPO_FOTO,
            'ruta_interna' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $mime,
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath() ?: $archivo->getPathname()),
            'actor_id' => $actorId,
            'capturado_at' => $capturadoAt,
            'inmutable' => true,
            'metadata_json' => [
                'origen' => 'registro_manual',
                'uso' => $uso,
            ],
        ]);
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
