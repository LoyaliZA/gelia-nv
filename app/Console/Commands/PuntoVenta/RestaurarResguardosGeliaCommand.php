<?php

namespace App\Console\Commands\PuntoVenta;

use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\PaqueteResguardosGelia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

#[Signature('pdv:restaurar-resguardos-gelia
    {--sql= : Dump gelia_resguardos_database.sql.gz}
    {--archivos= : Tar gelia_resguardos_archivos.tar.gz}
    {--staging= : Directorio ya extraído del tar}
    {--sucursal= : ID de sucursal destino; por defecto PRINCIPAL}
    {--dry-run : Informa el cruce sin escribir resguardos ni archivos}')]
#[Description('Restaura en este entorno el paquete de resguardos GELIA migrados desde TERA')]
class RestaurarResguardosGeliaCommand extends Command
{
    /** @var array<string, list<int>> */
    private array $clientesPorNombre = [];

    /** @var array<int, object> */
    private array $clientesPorId = [];

    public function handle(): int
    {
        $sql = (string) ($this->option('sql') ?: '');
        $tar = (string) ($this->option('archivos') ?: '');
        $dryRun = (bool) $this->option('dry-run');

        if ($sql === '' || ! is_file($sql)) {
            $this->error('Indica el dump con --sql=. No se encontró: '.$sql);

            return self::FAILURE;
        }

        if (! $this->estructuraLista()) {
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

        $staging = (string) ($this->option('staging') ?: storage_path('app/gelia-resguardos-staging'));
        $extraidoAqui = false;

        try {
            $this->cargarSql($sql);
            $this->indexarClientes();
            DB::statement("SET time_zone = '+00:00'");

            $resguardos = DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardos')->orderBy('id')->get();
            $clavesOrigen = DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardo_eventos')
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO)
                ->pluck('idempotency_key', 'resguardo_id')
                ->mapWithKeys(fn ($clave, $id) => [(int) $id => $clave]);
            $claves = DB::table('pdv_resguardo_eventos')
                ->where('tipo_evento', ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_CREADO)
                ->where('idempotency_key', 'like', 'tera:pickup:%:registro')
                ->pluck('idempotency_key')
                ->flip();

            $nuevos = 0;
            $omitidos = 0;
            foreach ($resguardos as $fila) {
                $clave = $clavesOrigen[(int) $fila->id] ?? null;
                if ($clave === null || $claves->has($clave)) {
                    $omitidos++;
                } else {
                    $nuevos++;
                }
            }

            $this->info(sprintf(
                'Paquete: %d resguardos. Nuevos: %d. Ya integrados: %d. Sucursal destino: %s (#%d).',
                $resguardos->count(),
                $nuevos,
                $omitidos,
                $sucursal->nombre,
                $sucursal->id
            ));

            if ($dryRun) {
                $this->info('Simulación terminada. No se escribió nada.');

                return self::SUCCESS;
            }

            if ($nuevos > 0) {
                $yaExtraido = is_dir($staging.'/pdv/resguardos');
                if (! $this->prepararArchivos($tar, $staging)) {
                    return self::FAILURE;
                }
                $extraidoAqui = ! $yaExtraido;
            }

            $importados = 0;
            $archivos = 0;
            foreach ($resguardos as $fila) {
                $clave = $clavesOrigen[(int) $fila->id] ?? null;
                if ($clave === null || $claves->has($clave)) {
                    continue;
                }

                try {
                    $copiados = DB::transaction(function () use ($fila, $sucursal, $departamentos, $staging) {
                        return $this->restaurarUno($fila, (int) $sucursal->id, $departamentos, $staging);
                    });
                } catch (Throwable $e) {
                    $this->error('Falló el folio '.$fila->snapshot_folio.' (origen '.$fila->id.'): '.$e->getMessage());

                    return self::FAILURE;
                }

                $claves->put($clave, true);
                $importados++;
                $archivos += $copiados;
                if ($importados % 100 === 0) {
                    $this->line("Integrados {$importados}…");
                }
            }

            $this->info(sprintf('Listo. Nuevos: %d. Ya existentes: %d. Archivos copiados: %d.', $importados, $omitidos, $archivos));
        } finally {
            $this->eliminarTablasTemporales();
            if ($extraidoAqui && is_dir($staging)) {
                Process::forever()->run(['rm', '-rf', $staging]);
            }
        }

        return self::SUCCESS;
    }

    private function estructuraLista(): bool
    {
        $requeridas = [
            'pdv_resguardos' => ['custodia_confirmada_at', 'es_demo', 'snapshot_json'],
            'pdv_resguardo_bultos' => ['codigo_etiqueta', 'piezas', 'condicion', 'custodia_at'],
        ];

        foreach ($requeridas as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                $this->error('Falta la tabla '.$tabla.'. Aplica antes las migraciones del módulo de resguardos.');

                return false;
            }
            foreach ($columnas as $columna) {
                if (! Schema::hasColumn($tabla, $columna)) {
                    $this->error('Falta la columna '.$tabla.'.'.$columna.'. La estructura de producción no está al día.');

                    return false;
                }
            }
        }

        return true;
    }

    private function resolverSucursal(): ?object
    {
        $id = $this->option('sucursal');
        $consulta = DB::table('sucursales')->where('activo', 1);
        $sucursal = $id !== null && $id !== ''
            ? $consulta->where('id', (int) $id)->first()
            : $consulta->where('nombre', 'PRINCIPAL')->first();

        if ($sucursal === null) {
            $this->error('No hay sucursal destino activa.');

            return null;
        }

        return $sucursal;
    }

    /**
     * @return array<string, array{id: int, nombre: string}>|null
     */
    private function resolverDepartamentos(): ?array
    {
        $mapa = [];
        foreach (DB::table('departamentos')->where('activo', 1)->get(['id', 'nombre']) as $fila) {
            $mapa[$this->normalizarNombre((string) $fila->nombre)] = [
                'id' => (int) $fila->id,
                'nombre' => (string) $fila->nombre,
            ];
        }

        if (! isset($mapa['AROMAS'], $mapa['BELLAROMA'])) {
            $this->error('Faltan los departamentos de origen Aromas o Bellaroma.');

            return null;
        }

        return $mapa;
    }

    private function cargarSql(string $sql): void
    {
        $this->eliminarTablasTemporales();
        $handle = gzopen($sql, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el dump.');
        }

        $sentencia = '';
        $enCadena = false;
        $escape = false;

        try {
            while (! gzeof($handle)) {
                $bloque = gzread($handle, 65536);
                if ($bloque === false || $bloque === '') {
                    break;
                }
                $longitud = strlen($bloque);
                for ($i = 0; $i < $longitud; $i++) {
                    $caracter = $bloque[$i];
                    if ($enCadena) {
                        $sentencia .= $caracter;
                        if ($escape) {
                            $escape = false;

                            continue;
                        }
                        if ($caracter === '\\') {
                            $escape = true;

                            continue;
                        }
                        if ($caracter === "'") {
                            $enCadena = false;
                        }

                        continue;
                    }
                    if ($caracter === "'") {
                        $enCadena = true;
                        $sentencia .= $caracter;

                        continue;
                    }
                    if ($caracter === ';') {
                        $sqlListo = trim($sentencia);
                        if ($sqlListo !== '') {
                            DB::unprepared($sqlListo);
                        }
                        $sentencia = '';

                        continue;
                    }
                    $sentencia .= $caracter;
                }
            }
        } finally {
            gzclose($handle);
        }

        $resto = trim($sentencia);
        if ($resto !== '') {
            DB::unprepared($resto);
        }
    }

    private function eliminarTablasTemporales(): void
    {
        foreach (array_reverse(PaqueteResguardosGelia::tablas()) as $tabla) {
            DB::statement('DROP TABLE IF EXISTS `'.PaqueteResguardosGelia::PREFIJO.$tabla.'`');
        }
    }

    private function prepararArchivos(string $tar, string $staging): bool
    {
        if (is_dir($staging.'/pdv/resguardos')) {
            return true;
        }

        if ($tar === '' || ! is_file($tar)) {
            $this->error('Indica el tar de evidencias con --archivos=. No se encontró: '.$tar);

            return false;
        }

        if (! is_dir($staging) && ! mkdir($staging, 0775, true) && ! is_dir($staging)) {
            $this->error('No se pudo crear el directorio de extracción.');

            return false;
        }

        $this->info('Extrayendo evidencias…');
        $resultado = Process::forever()->run(['tar', '-xzf', $tar, '-C', $staging]);
        if (! $resultado->successful()) {
            $this->error(trim($resultado->errorOutput()) ?: 'No se pudo extraer el tar de evidencias.');

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, array{id: int, nombre: string}>  $departamentos
     * @return int archivos copiados
     */
    private function restaurarUno(object $fila, int $sucursalId, array $departamentos, string $staging): int
    {
        $origenId = (int) $fila->id;
        $snapshot = $this->json($fila->snapshot_json);
        $departamento = $this->departamentoDe($snapshot, $departamentos);
        $clienteId = $this->resolverClienteId((string) $fila->snapshot_cliente_nombre);

        $snapshot['sucursal_id'] = $sucursalId;
        $snapshot['origen_id'] = $departamento['id'];
        $snapshot['departamento_id'] = $departamento['id'];
        $snapshot['origen_nombre'] = $departamento['nombre'];
        $snapshot['departamento_nombre'] = $departamento['nombre'];

        $resguardoId = DB::table('pdv_resguardos')->insertGetId([
            'pedido_bma_id' => null,
            'cliente_id' => $clienteId,
            'sucursal_id' => $sucursalId,
            'almacen_id' => null,
            'estado' => $fila->estado,
            'cantidad_bultos_esperada' => $fila->cantidad_bultos_esperada,
            'salida_cedis_at' => $fila->salida_cedis_at,
            'recepcion_fisica_at' => $fila->recepcion_fisica_at,
            'custodia_confirmada_at' => $fila->custodia_confirmada_at,
            'entrega_completada_at' => $fila->entrega_completada_at,
            'devolucion_confirmada_at' => $fila->devolucion_confirmada_at,
            'vencido_repuesto_at' => $fila->vencido_repuesto_at,
            'entrega_bloqueada' => $fila->entrega_bloqueada,
            'snapshot_folio' => $fila->snapshot_folio,
            'snapshot_cliente_nombre' => $fila->snapshot_cliente_nombre,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'version' => $fila->version,
            'es_demo' => 0,
            'created_at' => $fila->created_at,
            'updated_at' => $fila->updated_at,
        ]);

        $bultos = [];
        foreach (DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardo_bultos')->where('resguardo_id', $origenId)->orderBy('id')->get() as $bulto) {
            $codigo = (string) $bulto->codigo_etiqueta;
            if ($codigo === '' || DB::table('pdv_resguardo_bultos')->where('codigo_etiqueta', $codigo)->exists()) {
                $codigo = GeneradorCodigoEtiquetaResguardoPdv::generar();
            }
            $bultos[(int) $bulto->id] = DB::table('pdv_resguardo_bultos')->insertGetId([
                'resguardo_id' => $resguardoId,
                'pedido_bma_id' => null,
                'folio' => $bulto->folio,
                'codigo_etiqueta' => $codigo,
                'tipo' => $bulto->tipo,
                'piezas' => $bulto->piezas,
                'condicion' => $bulto->condicion,
                'estado' => $bulto->estado,
                'recepcion_at' => $bulto->recepcion_at,
                'recepcion_por_id' => null,
                'custodia_at' => $bulto->custodia_at,
                'custodia_por_id' => null,
                'entrega_at' => $bulto->entrega_at,
                'devolucion_salida_at' => $bulto->devolucion_salida_at,
                'version' => $bulto->version,
                'created_at' => $bulto->created_at,
                'updated_at' => $bulto->updated_at,
            ]);
        }

        $entregas = [];
        foreach (DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardo_entregas')->where('resguardo_id', $origenId)->orderBy('id')->get() as $entrega) {
            $entregas[(int) $entrega->id] = DB::table('pdv_resguardo_entregas')->insertGetId([
                'resguardo_id' => $resguardoId,
                'pedido_bma_id' => null,
                'relacion' => $entrega->relacion,
                'nombre_quien_retira' => $entrega->nombre_quien_retira,
                'entregado_por_id' => null,
                'entregado_at' => $entrega->entregado_at,
                'incidencia_autorizada_id' => null,
                'snapshot_json' => $entrega->snapshot_json,
                'idempotency_key' => $entrega->idempotency_key,
                'version' => $entrega->version,
                'created_at' => $entrega->created_at,
                'updated_at' => $entrega->updated_at,
            ]);
        }

        $pivote = [];
        $entregaIds = array_keys($entregas);
        if ($entregaIds !== []) {
            foreach (DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardo_entrega_bultos')->whereIn('entrega_id', $entregaIds)->orderBy('id')->get() as $liga) {
                $pivote[] = [
                    'entrega_id' => $entregas[(int) $liga->entrega_id],
                    'bulto_id' => $bultos[(int) $liga->bulto_id],
                    'created_at' => $liga->created_at,
                    'updated_at' => $liga->updated_at,
                ];
            }
        }
        if ($pivote !== []) {
            DB::table('pdv_resguardo_entrega_bultos')->insert($pivote);
        }

        $eventos = [];
        foreach (DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardo_eventos')->where('resguardo_id', $origenId)->orderBy('id')->get() as $evento) {
            $snap = $this->json($evento->snapshot_json);
            if (array_key_exists('sucursal_id', $snap)) {
                $snap['sucursal_id'] = $sucursalId;
            }
            if (array_key_exists('origen_id', $snap)) {
                $snap['origen_id'] = $departamento['id'];
            }
            if (array_key_exists('departamento_id', $snap)) {
                $snap['departamento_id'] = $departamento['id'];
            }
            if (isset($snap['entrega_id'])) {
                $snap['entrega_id'] = $entregas[(int) $snap['entrega_id']] ?? $snap['entrega_id'];
            }
            if (array_key_exists('cliente_id', $snap)) {
                $snap['cliente_id'] = $clienteId;
            }

            $eventos[(int) $evento->id] = DB::table('pdv_resguardo_eventos')->insertGetId([
                'resguardo_id' => $resguardoId,
                'bulto_id' => null,
                'tipo_evento' => $evento->tipo_evento,
                'estado_anterior' => $evento->estado_anterior,
                'estado_nuevo' => $evento->estado_nuevo,
                'actor_id' => null,
                'ocurrido_at' => $evento->ocurrido_at,
                'snapshot_json' => json_encode($snap, JSON_UNESCAPED_UNICODE),
                'idempotency_key' => $evento->idempotency_key,
                'created_at' => $evento->created_at,
                'updated_at' => $evento->updated_at,
            ]);
        }

        $copiados = [];
        $disco = Storage::disk('local');
        try {
            foreach (DB::table(PaqueteResguardosGelia::PREFIJO.'pdv_resguardo_evidencias')->where('resguardo_id', $origenId)->orderBy('id')->get() as $evidencia) {
                $destino = $this->rutaDestino($resguardoId, (string) $evidencia->ruta_interna);
                $absoluta = $staging.'/'.ltrim((string) $evidencia->ruta_interna, '/');
                if (! is_file($absoluta)) {
                    throw new RuntimeException('Falta el archivo '.$evidencia->ruta_interna);
                }
                $stream = fopen($absoluta, 'rb');
                if ($stream === false) {
                    throw new RuntimeException('No se pudo leer '.$evidencia->ruta_interna);
                }
                try {
                    $disco->writeStream($destino, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $copiados[] = $destino;

                DB::table('pdv_resguardo_evidencias')->insert([
                    'resguardo_id' => $resguardoId,
                    'evento_id' => $eventos[(int) $evidencia->evento_id] ?? null,
                    'bulto_id' => null,
                    'incidencia_id' => null,
                    'entrega_id' => $evidencia->entrega_id !== null ? ($entregas[(int) $evidencia->entrega_id] ?? null) : null,
                    'tipo' => $evidencia->tipo,
                    'ruta_interna' => $destino,
                    'nombre_original' => $evidencia->nombre_original,
                    'mime_type' => $evidencia->mime_type,
                    'tamano_bytes' => $evidencia->tamano_bytes,
                    'hash_sha256' => $evidencia->hash_sha256,
                    'actor_id' => null,
                    'capturado_at' => $evidencia->capturado_at,
                    'inmutable' => $evidencia->inmutable,
                    'metadata_json' => $evidencia->metadata_json,
                    'created_at' => $evidencia->created_at,
                    'updated_at' => $evidencia->updated_at,
                ]);
            }
        } catch (Throwable $e) {
            foreach ($copiados as $ruta) {
                $disco->delete($ruta);
            }
            throw $e;
        }

        return count($copiados);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, array{id: int, nombre: string}>  $departamentos
     * @return array{id: int, nombre: string}
     */
    private function departamentoDe(array $snapshot, array $departamentos): array
    {
        $nombre = $this->normalizarNombre((string) ($snapshot['origen_nombre'] ?? $snapshot['departamento_nombre'] ?? ''));
        if (! isset($departamentos[$nombre])) {
            throw new RuntimeException('No hay departamento activo equivalente a '.($snapshot['origen_nombre'] ?? 'sin nombre').'.');
        }

        return $departamentos[$nombre];
    }

    private function rutaDestino(int $resguardoId, string $rutaOrigen): string
    {
        $carpeta = str_contains($rutaOrigen, '/entregas/') ? 'entregas' : 'registro-manual';

        return 'pdv/resguardos/'.$resguardoId.'/'.$carpeta.'/'.basename($rutaOrigen);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        $decodificado = json_decode((string) $valor, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    private function indexarClientes(): void
    {
        foreach (DB::table('clientes')->orderBy('id')->get(['id', 'nombre', 'deleted_at']) as $fila) {
            $this->clientesPorId[(int) $fila->id] = $fila;
            $this->clientesPorNombre[$this->normalizarNombre((string) $fila->nombre)][] = (int) $fila->id;
        }
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
}
