<?php

namespace App\Console\Commands\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEntrega;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('pdv:importar-resguardos-tera
    {--sql= : Dump .sql.gz de pickups TERA}
    {--staging= : Directorio ya extraído con evidences/, pickups/ y signatures/}
    {--sucursal= : ID de sucursal destino; por defecto PRINCIPAL}
    {--dry-run : Calcula el cruce sin escribir}')]
#[Description('Integra resguardos del sistema TERA al módulo PDV, conservando fechas y evidencias')]
class ImportarResguardosTeraCommand extends Command
{
    private const ORIGEN = 'TERA';

    /** @var array<string, list<int>> */
    private array $clientesPorNombre = [];

    /** @var array<int, object> */
    private array $clientesPorId = [];

    public function handle(): int
    {
        $sql = (string) ($this->option('sql') ?: base_path('snapshots/tera-resguardos-backup-20261003_122322/tera_resguardos_database.sql.gz'));
        $staging = (string) ($this->option('staging') ?: storage_path('app/tera-resguardos-staging'));
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($sql)) {
            $this->error('No se encontró el dump: '.$sql);

            return self::FAILURE;
        }

        $sucursal = $this->resolverSucursal();
        if ($sucursal === null) {
            return self::FAILURE;
        }

        $departamentos = $this->resolverDepartamentos();
        if ($departamentos === null) {
            return self::FAILURE;
        }

        $this->indexarClientes();
        $pickups = $this->leerTabla($sql, 'pickups');
        $edits = $this->leerTabla($sql, 'pickup_edits');

        $this->info(sprintf(
            'Dump: %d resguardos, %d correcciones. Sucursal destino: %s (#%d).',
            count($pickups),
            count($edits),
            $sucursal->nombre,
            $sucursal->id
        ));

        $cruce = $this->resumenCruce($pickups);
        $this->line(sprintf(
            'Clientes vinculados por nombre: %d. Sin cuenta equivalente: %d resguardos (%d nombres).',
            $cruce['vinculados'],
            $cruce['sin_cuenta_resguardos'],
            count($cruce['sin_cuenta_nombres'])
        ));
        foreach ($cruce['sin_cuenta_nombres'] as $nombre) {
            $this->line('  Sin cuenta: '.$nombre);
        }

        if ($dryRun) {
            $this->info('Simulación terminada. No se escribió nada.');

            return self::SUCCESS;
        }

        if (! is_dir($staging.'/evidences') || ! is_dir($staging.'/pickups') || ! is_dir($staging.'/signatures')) {
            $this->error('Falta el directorio de archivos extraídos: '.$staging);

            return self::FAILURE;
        }

        $importados = 0;
        $omitidos = 0;
        $archivos = 0;
        $faltantes = 0;
        $mapa = $this->mapaYaImportado();

        foreach ($pickups as $fila) {
            $pickup = $this->enHoraLocal($this->pickup($fila));
            $clave = $this->clave($pickup['id'], 'registro');
            if (isset($mapa[$clave])) {
                $omitidos++;

                continue;
            }

            try {
                $resultado = DB::transaction(function () use ($pickup, $sucursal, $departamentos, $staging) {
                    return $this->importarPickup($pickup, (int) $sucursal->id, $departamentos, $staging);
                });
            } catch (Throwable $e) {
                $this->error('Falló el folio '.$pickup['ticket_folio'].' (pickup '.$pickup['id'].'): '.$e->getMessage());

                return self::FAILURE;
            }

            $mapa[$clave] = $resultado['resguardo_id'];
            $importados++;
            $archivos += $resultado['archivos'];
            $faltantes += $resultado['faltantes'];

            if ($importados % 100 === 0) {
                $this->line("Importados {$importados}…");
            }
        }

        $correcciones = $this->importarCorrecciones($edits, $mapa);

        $this->info(sprintf(
            'Listo. Nuevos: %d. Ya existentes: %d. Archivos copiados: %d. Archivos ausentes: %d. Correcciones: %d.',
            $importados,
            $omitidos,
            $archivos,
            $faltantes,
            $correcciones
        ));

        return self::SUCCESS;
    }

    private function resolverSucursal(): ?object
    {
        $id = $this->option('sucursal');
        $query = DB::table('sucursales')->where('activo', 1);
        $sucursal = $id !== null && $id !== ''
            ? $query->where('id', (int) $id)->first()
            : $query->where('nombre', 'PRINCIPAL')->first();

        if ($sucursal === null) {
            $this->error('No hay sucursal destino activa.');

            return null;
        }

        return $sucursal;
    }

    /**
     * @return array<int, array{id: int, nombre: string}>|null
     */
    private function resolverDepartamentos(): ?array
    {
        $filas = DB::table('departamentos')
            ->where('activo', 1)
            ->whereIn('nombre', ['Aromas', 'Bellaroma'])
            ->get(['id', 'nombre']);

        $mapa = [];
        foreach ($filas as $fila) {
            $clave = $this->normalizarNombre((string) $fila->nombre);
            $mapa[$clave] = ['id' => (int) $fila->id, 'nombre' => (string) $fila->nombre];
        }

        if (! isset($mapa['AROMAS'], $mapa['BELLAROMA'])) {
            $this->error('Faltan los departamentos de origen Aromas o Bellaroma.');

            return null;
        }

        return [
            1 => $mapa['AROMAS'],
            2 => $mapa['BELLAROMA'],
        ];
    }

    private function indexarClientes(): void
    {
        $filas = DB::table('clientes')->orderBy('id')->get(['id', 'numero_cliente', 'nombre', 'deleted_at', 'es_demo']);
        foreach ($filas as $fila) {
            $this->clientesPorId[(int) $fila->id] = $fila;
            $nombre = $this->normalizarNombre((string) $fila->nombre);
            $this->clientesPorNombre[$nombre][] = (int) $fila->id;
        }
    }

    /**
     * @param  list<list<string|null>>  $pickups
     * @return array{vinculados: int, sin_cuenta_resguardos: int, sin_cuenta_nombres: list<string>}
     */
    private function resumenCruce(array $pickups): array
    {
        $vinculados = 0;
        $sinCuenta = 0;
        $nombres = [];

        foreach ($pickups as $fila) {
            $pickup = $this->enHoraLocal($this->pickup($fila));
            $clienteId = $this->resolverClienteId($pickup['client_name']);
            if ($clienteId === null) {
                $sinCuenta++;
                $nombres[$pickup['client_name']] = true;
            } else {
                $vinculados++;
            }
        }

        ksort($nombres);

        return [
            'vinculados' => $vinculados,
            'sin_cuenta_resguardos' => $sinCuenta,
            'sin_cuenta_nombres' => array_keys($nombres),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function mapaYaImportado(): array
    {
        return DB::table('pdv_resguardo_eventos')
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO)
            ->where('idempotency_key', 'like', 'tera:pickup:%:registro')
            ->pluck('resguardo_id', 'idempotency_key')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $pickup
     * @param  array<int, array{id: int, nombre: string}>  $departamentos
     * @return array{resguardo_id: int, archivos: int, faltantes: int}
     */
    private function importarPickup(array $pickup, int $sucursalId, array $departamentos, string $staging): array
    {
        $departamento = $departamentos[(int) $pickup['department_id']] ?? $departamentos[1];
        $clienteId = $this->resolverClienteId($pickup['client_name']);
        $cliente = $clienteId !== null ? $this->clientesPorId[$clienteId] : null;
        $entregado = (int) $pickup['status_id'] === 7;
        $registroAt = $pickup['created_at'] ?: $pickup['ticket_date'].' 00:00:00';
        $recepcionAt = $pickup['received_by_checker_at'] ?: $registroAt;
        $entregaAt = $pickup['delivered_at'] ?: ($pickup['updated_at'] ?: $registroAt);
        if ($entregado && strcmp((string) $recepcionAt, (string) $entregaAt) > 0) {
            $recepcionAt = $entregaAt;
        }

        $bultos = max(1, (int) $pickup['bags']);
        $piezas = $this->piezasPorBulto((int) $pickup['pieces'], $bultos);
        $tercero = (int) $pickup['is_third_party'] === 1;
        $estado = $entregado ? ResguardoPdv::ESTADO_ENTREGADO : ResguardoPdv::ESTADO_EN_CUSTODIA;
        $ahoraRegistro = $registroAt;

        $snapshot = [
            'pedido_bma_id' => null,
            'folio' => $pickup['ticket_folio'],
            'folio_remision' => $pickup['ticket_folio'],
            'sucursal_id' => $sucursalId,
            'handoff' => 'manual',
            'envia_a_otra_persona' => false,
            'envia_otra_persona' => null,
            'origen_id' => $departamento['id'],
            'origen_nombre' => $departamento['nombre'],
            'departamento_id' => $departamento['id'],
            'departamento_nombre' => $departamento['nombre'],
            'registrado_por_id' => null,
            'registrado_por_nombre' => 'Migración TERA',
            'observaciones' => $pickup['notes'],
            'cantidad_piezas' => (int) $pickup['pieces'],
            'piezas' => [],
            'importacion' => [
                'sistema' => self::ORIGEN,
                'pickup_id' => (int) $pickup['id'],
                'client_ref_id' => $pickup['client_ref_id'],
                'ticket_date' => $pickup['ticket_date'],
                'amount' => $pickup['amount'],
                'balance' => $pickup['balance'],
                'correction_notes' => $pickup['correction_notes'],
                'estatus_origen' => $entregado ? 'DELIVERED' : 'IN_CUSTODY',
            ],
        ];

        $resguardoId = DB::table('pdv_resguardos')->insertGetId([
            'pedido_bma_id' => null,
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'almacen_id' => null,
            'estado' => $estado,
            'cantidad_bultos_esperada' => $bultos,
            'salida_cedis_at' => $ahoraRegistro,
            'recepcion_fisica_at' => $recepcionAt,
            'custodia_confirmada_at' => $recepcionAt,
            'entrega_completada_at' => $entregado ? $entregaAt : null,
            'devolucion_confirmada_at' => null,
            'vencido_repuesto_at' => null,
            'entrega_bloqueada' => 0,
            'snapshot_folio' => $pickup['ticket_folio'],
            'snapshot_cliente_nombre' => $cliente->nombre ?? $pickup['client_name'],
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'version' => 1,
            'es_demo' => 0,
            'created_at' => $ahoraRegistro,
            'updated_at' => $pickup['updated_at'] ?: $ahoraRegistro,
        ]);

        $bultoIds = [];
        foreach ($piezas as $indice => $cantidad) {
            $numero = $indice + 1;
            $bultoIds[] = DB::table('pdv_resguardo_bultos')->insertGetId([
                'resguardo_id' => $resguardoId,
                'pedido_bma_id' => null,
                'folio' => $bultos === 1 ? $pickup['ticket_folio'] : $pickup['ticket_folio'].'-'.$numero,
                'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                'tipo' => ResguardoPdvBulto::TIPO_BOLSA,
                'piezas' => $cantidad,
                'condicion' => 'bueno',
                'estado' => $entregado ? ResguardoPdvBulto::ESTADO_ENTREGADO : ResguardoPdvBulto::ESTADO_EN_CUSTODIA,
                'recepcion_at' => $recepcionAt,
                'recepcion_por_id' => null,
                'custodia_at' => $recepcionAt,
                'custodia_por_id' => null,
                'entrega_at' => $entregado ? $entregaAt : null,
                'devolucion_salida_at' => null,
                'version' => 1,
                'created_at' => $recepcionAt,
                'updated_at' => $entregado ? $entregaAt : $recepcionAt,
            ]);
        }

        $eventoRegistroId = $this->insertarEvento(
            $resguardoId,
            ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO,
            null,
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            $ahoraRegistro,
            $this->clave($pickup['id'], 'registro'),
            [
                'sucursal_id' => $sucursalId,
                'handoff' => 'manual',
                'cliente_id' => $clienteId,
                'folio' => $pickup['ticket_folio'],
                'cantidad_bultos_esperada' => $bultos,
                'origen_id' => $departamento['id'],
                'departamento_id' => $departamento['id'],
                'cantidad_piezas' => (int) $pickup['pieces'],
                'importacion' => self::ORIGEN,
            ]
        );

        $this->insertarEvento(
            $resguardoId,
            ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
            ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
            ResguardoPdv::ESTADO_EN_RECEPCION,
            $recepcionAt,
            $this->clave($pickup['id'], 'recepcion'),
            ['importacion' => self::ORIGEN, 'cantidad_bultos' => $bultos]
        );

        $this->insertarEvento(
            $resguardoId,
            ResguardoPdvEvento::TIPO_CUSTODIA_COMPLETA,
            ResguardoPdv::ESTADO_EN_RECEPCION,
            ResguardoPdv::ESTADO_EN_CUSTODIA,
            $recepcionAt,
            $this->clave($pickup['id'], 'custodia'),
            ['importacion' => self::ORIGEN, 'condicion' => 'bueno']
        );

        $entregaId = null;
        $eventoEntregaId = null;
        if ($entregado) {
            $relacion = $tercero ? ResguardoPdvEntrega::RELACION_TERCERO : ResguardoPdvEntrega::RELACION_TITULAR;
            $nombreRetira = trim((string) $pickup['receiver_name']);
            if ($nombreRetira === '') {
                $nombreRetira = $tercero ? 'Tercero autorizado' : 'Titular de la cuenta';
            }

            $entregaId = DB::table('pdv_resguardo_entregas')->insertGetId([
                'resguardo_id' => $resguardoId,
                'pedido_bma_id' => null,
                'relacion' => $relacion,
                'nombre_quien_retira' => $nombreRetira,
                'entregado_por_id' => null,
                'entregado_at' => $entregaAt,
                'incidencia_autorizada_id' => null,
                'snapshot_json' => json_encode([
                    'receptor' => ['nombre' => $nombreRetira, 'relacion' => $relacion],
                    'metodo_validacion' => 'migracion_tera',
                    'observaciones' => $pickup['notes'],
                    'parcial' => false,
                    'operacion_multiple' => false,
                    'importacion' => self::ORIGEN,
                ], JSON_UNESCAPED_UNICODE),
                'idempotency_key' => $this->clave($pickup['id'], 'entrega-reg'),
                'version' => 1,
                'created_at' => $entregaAt,
                'updated_at' => $entregaAt,
            ]);

            $pivote = [];
            foreach ($bultoIds as $bultoId) {
                $pivote[] = [
                    'entrega_id' => $entregaId,
                    'bulto_id' => $bultoId,
                    'created_at' => $entregaAt,
                    'updated_at' => $entregaAt,
                ];
            }
            DB::table('pdv_resguardo_entrega_bultos')->insert($pivote);

            $eventoEntregaId = $this->insertarEvento(
                $resguardoId,
                $tercero ? ResguardoPdvEvento::TIPO_ENTREGA_TERCERO : ResguardoPdvEvento::TIPO_ENTREGA_TITULAR,
                ResguardoPdv::ESTADO_EN_CUSTODIA,
                ResguardoPdv::ESTADO_ENTREGADO,
                $entregaAt,
                $this->clave($pickup['id'], 'entrega'),
                [
                    'entrega_id' => $entregaId,
                    'receptor' => ['nombre' => $nombreRetira, 'relacion' => $relacion],
                    'cantidad_entregada' => $bultos,
                    'parcial' => false,
                    'importacion' => self::ORIGEN,
                ]
            );
        }

        $copiados = [];
        $archivos = 0;
        $faltantes = 0;
        try {
            $archivos += $this->copiarEvidencia(
                $staging,
                $pickup['initial_evidence_path'],
                $resguardoId,
                $eventoRegistroId,
                null,
                ResguardoPdvEvidencia::USO_TICKET,
                $ahoraRegistro,
                $copiados
            ) ? 1 : 0;
            if ($pickup['initial_evidence_path'] && ! $this->archivoExiste($staging, $pickup['initial_evidence_path'])) {
                $faltantes++;
            }

            $paquete = $this->copiarEvidencia(
                $staging,
                $pickup['package_evidence_path'],
                $resguardoId,
                $eventoRegistroId,
                null,
                ResguardoPdvEvidencia::USO_PAQUETE,
                $ahoraRegistro,
                $copiados
            );
            $archivos += $paquete ? 1 : 0;
            if ($pickup['package_evidence_path'] && ! $this->archivoExiste($staging, $pickup['package_evidence_path'])) {
                $faltantes++;
            }

            if ($entregado && $entregaId !== null && $eventoEntregaId !== null) {
                $firma = $this->copiarEvidencia(
                    $staging,
                    $pickup['signature_path'],
                    $resguardoId,
                    $eventoEntregaId,
                    $entregaId,
                    'firma',
                    $entregaAt,
                    $copiados
                );
                $archivos += $firma ? 1 : 0;
                if ($pickup['signature_path'] && ! $this->archivoExiste($staging, $pickup['signature_path'])) {
                    $faltantes++;
                }

                $foto = $this->copiarEvidencia(
                    $staging,
                    $pickup['evidence_path'],
                    $resguardoId,
                    $eventoEntregaId,
                    $entregaId,
                    'evidencia_entrega',
                    $entregaAt,
                    $copiados
                );
                $archivos += $foto ? 1 : 0;
                if ($pickup['evidence_path'] && ! $this->archivoExiste($staging, $pickup['evidence_path'])) {
                    $faltantes++;
                }
            }
        } catch (Throwable $e) {
            $this->borrarCopias($copiados);
            throw $e;
        }

        return [
            'resguardo_id' => $resguardoId,
            'archivos' => $archivos,
            'faltantes' => $faltantes,
        ];
    }

    /**
     * @param  list<list<string|null>>  $edits
     * @param  array<string, int>  $mapa
     */
    private function importarCorrecciones(array $edits, array $mapa): int
    {
        $insertadas = 0;
        foreach ($edits as $fila) {
            $edit = [
                'id' => (string) $fila[0],
                'pickup_id' => (string) $fila[1],
                'changes' => $fila[3],
                'reason' => $fila[4],
                'created_at' => $this->aHoraLocal($fila[5] ?: $fila[6]),
            ];
            $claveRegistro = $this->clave($edit['pickup_id'], 'registro');
            $resguardoId = $mapa[$claveRegistro] ?? null;
            if ($resguardoId === null || $edit['created_at'] === null) {
                continue;
            }

            $clave = 'tera:pickup:'.$edit['pickup_id'].':edit:'.$edit['id'];
            if (DB::table('pdv_resguardo_eventos')->where('idempotency_key', $clave)->exists()) {
                continue;
            }

            $changes = json_decode((string) $edit['changes'], true);
            $this->insertarEvento(
                $resguardoId,
                ResguardoPdvEvento::TIPO_CORRECCION_ADMINISTRATIVA,
                null,
                null,
                $edit['created_at'],
                $clave,
                [
                    'importacion' => self::ORIGEN,
                    'motivo' => $edit['reason'],
                    'cambios' => is_array($changes) ? $changes : $edit['changes'],
                ]
            );
            $insertadas++;
        }

        return $insertadas;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function insertarEvento(
        int $resguardoId,
        string $tipo,
        ?string $anterior,
        ?string $nuevo,
        string $ocurridoAt,
        string $clave,
        array $snapshot,
    ): int {
        return DB::table('pdv_resguardo_eventos')->insertGetId([
            'resguardo_id' => $resguardoId,
            'bulto_id' => null,
            'tipo_evento' => $tipo,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'actor_id' => null,
            'ocurrido_at' => $ocurridoAt,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'idempotency_key' => $clave,
            'created_at' => $ocurridoAt,
            'updated_at' => $ocurridoAt,
        ]);
    }

    /**
     * @param  list<string>  $copiados
     */
    private function copiarEvidencia(
        string $staging,
        ?string $rutaOrigen,
        int $resguardoId,
        int $eventoId,
        ?int $entregaId,
        string $uso,
        string $capturadoAt,
        array &$copiados,
    ): bool {
        if ($rutaOrigen === null || $rutaOrigen === '') {
            return false;
        }

        $absoluta = $staging.'/'.ltrim($rutaOrigen, '/');
        if (! is_file($absoluta)) {
            return false;
        }

        $nombre = basename($absoluta);
        $carpeta = $entregaId === null ? 'registro-manual' : 'entregas';
        $destino = 'pdv/resguardos/'.$resguardoId.'/'.$carpeta.'/'.$nombre;
        $stream = fopen($absoluta, 'rb');
        if ($stream === false) {
            return false;
        }

        try {
            Storage::disk('local')->writeStream($destino, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $copiados[] = $destino;

        $mime = $this->mime($nombre);
        $esFirma = $uso === 'firma';
        $esPdf = $mime === 'application/pdf';

        DB::table('pdv_resguardo_evidencias')->insert([
            'resguardo_id' => $resguardoId,
            'evento_id' => $eventoId,
            'bulto_id' => null,
            'incidencia_id' => null,
            'entrega_id' => $entregaId,
            'tipo' => $esFirma
                ? ResguardoPdvEvidencia::TIPO_FIRMA
                : ($esPdf ? ResguardoPdvEvidencia::TIPO_ARCHIVO : ResguardoPdvEvidencia::TIPO_FOTO),
            'ruta_interna' => $destino,
            'nombre_original' => $nombre,
            'mime_type' => $mime,
            'tamano_bytes' => filesize($absoluta) ?: null,
            'hash_sha256' => hash_file('sha256', $absoluta),
            'actor_id' => null,
            'capturado_at' => $capturadoAt,
            'inmutable' => 1,
            'metadata_json' => json_encode([
                'origen' => $entregaId === null ? 'registro_manual' : 'entrega_fisica',
                'uso' => $uso,
                'sistema' => self::ORIGEN,
                'ruta_origen' => $rutaOrigen,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $capturadoAt,
            'updated_at' => $capturadoAt,
        ]);

        return true;
    }

    private function archivoExiste(string $staging, ?string $rutaOrigen): bool
    {
        if ($rutaOrigen === null || $rutaOrigen === '') {
            return false;
        }

        return is_file($staging.'/'.ltrim($rutaOrigen, '/'));
    }

    /**
     * @param  list<string>  $rutas
     */
    private function borrarCopias(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            Storage::disk('local')->delete($ruta);
        }
    }

    /**
     * @return list<int>
     */
    private function piezasPorBulto(int $piezas, int $bultos): array
    {
        $bultos = max(1, $bultos);
        $piezas = max(1, $piezas);
        if ($piezas < $bultos) {
            $resultado = array_fill(0, $bultos, 1);
            $resultado[0] = $piezas;

            return $resultado;
        }

        $base = intdiv($piezas, $bultos);
        $resto = $piezas % $bultos;
        $resultado = [];
        for ($i = 0; $i < $bultos; $i++) {
            $resultado[] = $base + ($i < $resto ? 1 : 0);
        }

        return $resultado;
    }

    private function resolverClienteId(string $nombre): ?int
    {
        $normalizado = $this->normalizarNombre($nombre);
        $id = $this->elegirCliente($this->clientesPorNombre[$normalizado] ?? []);
        if ($id !== null) {
            return $id;
        }

        if (str_starts_with($normalizado, 'HUB ')) {
            return $this->elegirCliente($this->clientesPorNombre[substr($normalizado, 4)] ?? []);
        }

        return null;
    }

    /**
     * @param  list<int>  $ids
     */
    private function elegirCliente(array $ids): ?int
    {
        if ($ids === []) {
            return null;
        }

        $activos = array_values(array_filter(
            $ids,
            fn (int $id) => $this->clientesPorId[$id]->deleted_at === null
        ));

        return $activos[0] ?? $ids[0];
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        $nombre = strtr($nombre, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $nombre = preg_replace('/[^A-Z0-9 ]/', ' ', $nombre) ?? $nombre;
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }

    /**
     * @param  list<string|null>  $fila
     * @return array<string, string|null>
     */
    private function pickup(array $fila): array
    {
        $columnas = [
            'id', 'ticket_folio', 'ticket_date', 'client_ref_id', 'client_name', 'is_complementary',
            'parent_pickup_id', 'amount', 'balance', 'seller_id', 'pieces', 'bags', 'box_type_id',
            'status_id', 'notes', 'correction_notes', 'receiver_name', 'is_third_party',
            'signature_path', 'evidence_path', 'initial_evidence_path', 'package_evidence_path',
            'received_by_checker_at', 'delivered_at', 'created_at', 'updated_at', 'department_id',
        ];

        $pickup = [];
        foreach ($columnas as $indice => $columna) {
            $pickup[$columna] = $fila[$indice] ?? null;
        }

        return $pickup;
    }

    /**
     * El dump de TERA guarda instantes en UTC. Este sistema persiste la hora de México.
     *
     * @param  array<string, string|null>  $pickup
     * @return array<string, string|null>
     */
    private function enHoraLocal(array $pickup): array
    {
        foreach (['created_at', 'updated_at', 'delivered_at', 'received_by_checker_at'] as $campo) {
            $pickup[$campo] = $this->aHoraLocal($pickup[$campo]);
        }

        return $pickup;
    }

    private function aHoraLocal(?string $instanteUtc): ?string
    {
        if ($instanteUtc === null || $instanteUtc === '') {
            return $instanteUtc;
        }

        return Carbon::parse($instanteUtc, 'UTC')
            ->timezone((string) config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    private function clave(int|string $pickupId, string $paso): string
    {
        return 'tera:pickup:'.$pickupId.':'.$paso;
    }

    private function mime(string $nombre): string
    {
        return match (strtolower(pathinfo($nombre, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return list<list<string|null>>
     */
    private function leerTabla(string $sql, string $tabla): array
    {
        $marker = 'INSERT INTO `'.$tabla.'`';
        $filas = [];
        $buffer = null;
        $handle = gzopen($sql, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el dump.');
        }

        try {
            while (($linea = gzgets($handle)) !== false) {
                if ($buffer === null) {
                    if (! str_starts_with($linea, $marker)) {
                        continue;
                    }
                    $buffer = $linea;
                } else {
                    $buffer .= $linea;
                }

                if (str_ends_with(rtrim($buffer), ';')) {
                    $filas = array_merge($filas, $this->parsearValores($buffer));
                    $buffer = null;
                }
            }
        } finally {
            gzclose($handle);
        }

        return $filas;
    }

    /**
     * @return list<list<string|null>>
     */
    private function parsearValores(string $sql): array
    {
        $inicio = strpos($sql, 'VALUES');
        if ($inicio === false) {
            return [];
        }

        $cuerpo = rtrim(trim(substr($sql, $inicio + 6)), ';');
        $filas = [];
        $fila = [];
        $token = '';
        $enTexto = false;
        $escape = false;
        $profundidad = 0;
        $longitud = strlen($cuerpo);

        for ($i = 0; $i < $longitud; $i++) {
            $caracter = $cuerpo[$i];
            if ($enTexto) {
                if ($escape) {
                    $token .= $this->desescapar($caracter);
                    $escape = false;
                } elseif ($caracter === '\\') {
                    $escape = true;
                } elseif ($caracter === "'") {
                    $enTexto = false;
                } else {
                    $token .= $caracter;
                }

                continue;
            }

            if ($caracter === "'") {
                $enTexto = true;
            } elseif ($caracter === '(') {
                if ($profundidad === 0) {
                    $fila = [];
                    $token = '';
                } else {
                    $token .= $caracter;
                }
                $profundidad++;
            } elseif ($caracter === ')') {
                $profundidad--;
                if ($profundidad === 0) {
                    $fila[] = $this->valor($token);
                    $filas[] = $fila;
                    $token = '';
                } else {
                    $token .= $caracter;
                }
            } elseif ($caracter === ',' && $profundidad === 1) {
                $fila[] = $this->valor($token);
                $token = '';
            } elseif ($profundidad >= 1) {
                $token .= $caracter;
            }
        }

        return $filas;
    }

    private function desescapar(string $caracter): string
    {
        return match ($caracter) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => "\0",
            default => $caracter,
        };
    }

    private function valor(string $token): ?string
    {
        $token = trim($token);
        if ($token === 'NULL') {
            return null;
        }

        return $token;
    }
}
