<?php

namespace App\Console\Commands\PuntoVenta;

use App\Support\PuntoVenta\Resguardos\PaqueteResguardosGelia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('pdv:exportar-resguardos-gelia
    {--destino= : Directorio del paquete; por defecto snapshots/gelia-resguardos-produccion-FECHA}')]
#[Description('Empaqueta los resguardos migrados desde TERA, ya en estructura GELIA, para restaurarlos en otro entorno')]
class ExportarResguardosGeliaCommand extends Command
{
    public function handle(): int
    {
        $ids = DB::table('pdv_resguardos')
            ->where('snapshot_json->importacion->sistema', PaqueteResguardosGelia::ORIGEN)
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->error('No hay resguardos con importación '.PaqueteResguardosGelia::ORIGEN.'.');

            return self::FAILURE;
        }

        $destino = (string) ($this->option('destino') ?: base_path('snapshots/gelia-resguardos-produccion-'.now()->format('Ymd_His')));
        if (! is_dir($destino) && ! mkdir($destino, 0775, true) && ! is_dir($destino)) {
            $this->error('No se pudo crear '.$destino);

            return self::FAILURE;
        }

        $sql = $destino.'/gelia_resguardos_database.sql.gz';
        $this->info('Exportando '.$ids->count().' resguardos a '.$sql);
        $conteos = $this->escribirSql($sql, $ids->all());

        $lista = $destino.'/rutas-archivos.txt';
        $faltantes = $destino.'/rutas-faltantes.txt';
        $this->escribirRutas($ids->all(), $lista, $faltantes, $conteos);

        $tar = $destino.'/gelia_resguardos_archivos.tar.gz';
        $this->info('Empaquetando evidencias…');
        $this->empaquetarArchivos($lista, $tar);

        $this->escribirResumen($destino, $ids->all(), $conteos);
        $this->escribirInstrucciones($destino);
        $this->escribirManifiesto($destino, $conteos, $sql, $tar, $lista, $faltantes);

        $this->info('Paquete listo en '.$destino);

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, int>
     */
    private function escribirSql(string $sql, array $ids): array
    {
        $handle = gzopen($sql, 'wb9');
        if ($handle === false) {
            throw new RuntimeException('No se pudo crear el dump.');
        }

        $pdo = DB::connection()->getPdo();
        $conteos = [];

        try {
            gzwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n");
            foreach (array_reverse(PaqueteResguardosGelia::tablas()) as $tabla) {
                gzwrite($handle, 'DROP TABLE IF EXISTS `'.PaqueteResguardosGelia::PREFIJO.$tabla."`;\n");
            }
            foreach (preg_split("/\n(?=CREATE )/", trim(PaqueteResguardosGelia::ddl())) as $sentencia) {
                $sentencia = trim($sentencia);
                if ($sentencia !== '') {
                    gzwrite($handle, $sentencia."\n");
                }
            }

            foreach (PaqueteResguardosGelia::tablas() as $tabla) {
                $columnas = PaqueteResguardosGelia::columnas()[$tabla];
                $conteos[$tabla] = 0;
                $lote = [];
                $consulta = DB::table($tabla)->select($columnas)->orderBy('id');
                if ($tabla === 'pdv_resguardos') {
                    $consulta->whereIn('id', $ids);
                } elseif ($tabla === 'pdv_resguardo_entrega_bultos') {
                    $consulta->whereIn('entrega_id', function ($q) use ($ids) {
                        $q->select('id')->from('pdv_resguardo_entregas')->whereIn('resguardo_id', $ids);
                    });
                } else {
                    $consulta->whereIn('resguardo_id', $ids);
                }

                foreach ($consulta->cursor() as $fila) {
                    $valores = [];
                    foreach ($columnas as $columna) {
                        $valores[] = $this->sqlValor($pdo, $fila->{$columna});
                    }
                    $lote[] = '('.implode(',', $valores).')';
                    $conteos[$tabla]++;
                    if (count($lote) >= 40) {
                        $this->volcarLote($handle, $tabla, $columnas, $lote);
                        $lote = [];
                    }
                }
                if ($lote !== []) {
                    $this->volcarLote($handle, $tabla, $columnas, $lote);
                }
            }
        } finally {
            gzclose($handle);
        }

        return $conteos;
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<string>  $lote
     */
    private function volcarLote($handle, string $tabla, array $columnas, array $lote): void
    {
        $destino = PaqueteResguardosGelia::PREFIJO.$tabla;
        $cols = implode(',', array_map(fn (string $columna) => '`'.$columna.'`', $columnas));
        gzwrite($handle, 'INSERT INTO `'.$destino.'` ('.$cols.') VALUES '.implode(',', $lote).";\n");
    }

    private function sqlValor(\PDO $pdo, mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        return $pdo->quote((string) $valor);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, int>  $conteos
     */
    private function escribirRutas(array $ids, string $lista, string $faltantes, array &$conteos): void
    {
        $presentes = fopen($lista, 'wb');
        $ausentes = fopen($faltantes, 'wb');
        if ($presentes === false || $ausentes === false) {
            throw new RuntimeException('No se pudieron escribir las listas de archivos.');
        }

        $conteos['rutas_empaquetadas'] = 0;
        $conteos['rutas_faltantes'] = 0;
        $disco = Storage::disk('local');

        DB::table('pdv_resguardo_evidencias')
            ->whereIn('resguardo_id', $ids)
            ->orderBy('id')
            ->select(['ruta_interna'])
            ->cursor()
            ->each(function (object $fila) use ($disco, $presentes, $ausentes, &$conteos) {
                $ruta = (string) $fila->ruta_interna;
                if ($disco->exists($ruta)) {
                    fwrite($presentes, $ruta."\n");
                    $conteos['rutas_empaquetadas']++;
                } else {
                    fwrite($ausentes, $ruta."\n");
                    $conteos['rutas_faltantes']++;
                }
            });

        fclose($presentes);
        fclose($ausentes);
    }

    private function empaquetarArchivos(string $lista, string $tar): void
    {
        if (filesize($lista) === 0) {
            throw new RuntimeException('No hay evidencias para empaquetar.');
        }

        $resultado = Process::forever()->run([
            'tar', '-I', 'gzip -1', '-cf', $tar, '-C', storage_path('app/private'), '-T', $lista,
        ]);

        if (! $resultado->successful()) {
            throw new RuntimeException(trim($resultado->errorOutput()) ?: 'tar falló al empaquetar evidencias.');
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, int>  $conteos
     */
    private function escribirResumen(string $destino, array $ids, array $conteos): void
    {
        $estados = DB::table('pdv_resguardos')
            ->whereIn('id', $ids)
            ->selectRaw('estado, COUNT(*) c')
            ->groupBy('estado')
            ->pluck('c', 'estado');

        $sinCuenta = DB::table('pdv_resguardos')
            ->whereIn('id', $ids)
            ->whereNull('cliente_id')
            ->orderBy('snapshot_cliente_nombre')
            ->pluck('snapshot_cliente_nombre')
            ->countBy();

        $lineas = ["estado\tresguardos"];
        foreach ($estados as $estado => $cantidad) {
            $lineas[] = $estado."\t".$cantidad;
        }
        $lineas[] = "concepto\ttotal";
        $lineas[] = "bultos\t".$conteos['pdv_resguardo_bultos'];
        $lineas[] = "eventos\t".$conteos['pdv_resguardo_eventos'];
        $lineas[] = "entregas\t".$conteos['pdv_resguardo_entregas'];
        $lineas[] = "evidencias\t".$conteos['pdv_resguardo_evidencias'];
        $lineas[] = "archivos_empaquetados\t".$conteos['rutas_empaquetadas'];
        $lineas[] = "archivos_faltantes\t".$conteos['rutas_faltantes'];
        $lineas[] = 'nombres_sin_cuenta\t'.$sinCuenta->count();
        foreach ($sinCuenta as $nombre => $cantidad) {
            $lineas[] = "sin_cuenta\t{$cantidad}\t{$nombre}";
        }

        file_put_contents($destino.'/resumen-estatus.txt', implode("\n", $lineas)."\n");
    }

    private function escribirInstrucciones(string $destino): void
    {
        $texto = <<<'TXT'
Integración en producción de los resguardos GELIA restaurados desde TERA
=======================================================================

Este paquete no es un volcado de toda la base. Contiene únicamente los resguardos
que ya se adaptaron a la estructura de GELIA (tablas pdv_resguardo_*) a partir del
respaldo de TERA, junto con sus evidencias. Los identificadores de este entorno
(resguardo, bulto, cliente, sucursal, departamento) no se reutilizan en producción:
el comando de restauración asigna identificadores nuevos y vuelve a vincular
sucursal, departamento y cuenta de cliente contra el catálogo de producción.

Contenido
---------
- gelia_resguardos_database.sql.gz
  Datos en tablas temporales _imp_pdv_*. No incluye CREATE de las tablas reales
  ni toca pedidos, usuarios, permisos ni otros resguardos.
- gelia_resguardos_archivos.tar.gz
  Evidencias en la ruta relativa pdv/resguardos/{id-origen}/...
- resumen-estatus.txt, rutas-archivos.txt, rutas-faltantes.txt, MANIFEST.txt

Requisitos en producción
------------------------
1. El código de esta revisión ya está desplegado, en particular:
   app/Console/Commands/PuntoVenta/RestaurarResguardosGeliaCommand.php
   app/Support/PuntoVenta/Resguardos/PaqueteResguardosGelia.php
2. Las migraciones del módulo de resguardos PDV ya corrieron. Deben existir las
   columnas custodia_confirmada_at, custodia_at, piezas, condicion y es_demo.
3. Existe la sucursal activa PRINCIPAL, o se indica otra con --sucursal=.
4. Existen los departamentos activos Aromas y Bellaroma. El comando los busca
   por nombre, no por el id de este entorno.
5. Hay copia de seguridad reciente de la base de producción y de storage/app/private.
6. El paquete está en el servidor de producción, dentro del proyecto, por ejemplo
   snapshots/gelia-resguardos-produccion-FECHA/.

No ejecutar migrate:fresh ni ningún comando con ALLOW_DESTRUCTIVE_DB.

Simulación
----------
Desde la raíz del proyecto, con los contenedores en marcha:

./vendor/bin/sail artisan pdv:restaurar-resguardos-gelia \
  --sql=snapshots/gelia-resguardos-produccion-FECHA/gelia_resguardos_database.sql.gz \
  --archivos=snapshots/gelia-resguardos-produccion-FECHA/gelia_resguardos_archivos.tar.gz \
  --dry-run

La simulación carga las tablas temporales, cruza las claves tera:pickup:*:registro
con lo que ya exista y no escribe resguardos ni archivos. Al terminar borra las
tablas _imp_pdv_*.

Restauración
------------
./vendor/bin/sail artisan pdv:restaurar-resguardos-gelia \
  --sql=snapshots/gelia-resguardos-produccion-FECHA/gelia_resguardos_database.sql.gz \
  --archivos=snapshots/gelia-resguardos-produccion-FECHA/gelia_resguardos_archivos.tar.gz

Opciones:
- --sucursal=ID   Sucursal destino. Si se omite, usa la sucursal activa PRINCIPAL.
- --staging=RUTA  Directorio ya extraído del tar. Si se omite, extrae en
                  storage/app/gelia-resguardos-staging y lo elimina al terminar.
- --dry-run       Solo informa cuántos resguardos son nuevos y cuántos ya existen.

Qué hace el comando
-------------------
- Inserta cada resguardo nuevo en una transacción propia: bultos, entregas,
  eventos y evidencias.
- Conserva folio, estados, fechas, piezas, condición, receptor y la bitácora.
- Las fechas ya están en hora de México. Antes de escribir fija la sesión MySQL
  en UTC (+00:00), igual que este entorno, para no recorrer el reloj.
- Vuelve a resolver cliente_id por nombre normalizado de la cuenta (mayúsculas,
  sin acentos). Si no hay cuenta equivalente, el resguardo queda con cliente_id
  nulo y el nombre visible en snapshot_cliente_nombre. Esos nombres están en
  resumen-estatus.txt.
- Reescribe sucursal_id y los id de departamento dentro de snapshot_json.
- Copia cada evidencia a storage/app/private/pdv/resguardos/{id-nuevo}/...
  y guarda esa ruta. No publica los archivos en el disco public.
- Si la clave de idempotencia tera:pickup:{id}:registro ya existe, omite ese
  resguardo completo. Se puede ejecutar de nuevo sin duplicar.
- No modifica resguardos que no vengan de esta migración.
- Si un código de etiqueta ya existe en producción, genera otro para el bulto
  nuevo. El folio del resguardo no cambia.

Verificación
------------
Después de la restauración:

./vendor/bin/sail artisan tinker --execute="echo json_encode(DB::table('pdv_resguardos')->where('snapshot_json->importacion->sistema','TERA')->selectRaw('estado, count(*) c')->groupBy('estado')->get());"

Comparar esos conteos con resumen-estatus.txt. Abrir en la aplicación un folio
entregado y uno en custodia, y confirmar que se ven la firma, la evidencia de
entrega y las fotos de ticket o paquete.

Repetir el comando con --dry-run debe reportar cero resguardos nuevos.

Qué no hacer
------------
- No importar otra vez el dump crudo de TERA (pdv:importar-resguardos-tera)
  encima de este paquete: son el mismo origen y las claves de idempotencia
  coinciden, pero este paquete ya trae la estructura adaptada.
- No cargar gelia_resguardos_database.sql.gz a mano sobre las tablas pdv_resguardo_*.
  Ese SQL solo llena tablas temporales _imp_pdv_* y el comando las elimina.
- No copiar el tar dentro de storage/app/public.
TXT;

        file_put_contents($destino.'/INTEGRACION.txt', $texto);
    }

    /**
     * @param  array<string, int>  $conteos
     */
    private function escribirManifiesto(string $destino, array $conteos, string $sql, string $tar, string $lista, string $faltantes): void
    {
        $lineas = [
            'sistema=GELIA-NV',
            'tipo=respaldo_resguardos_produccion',
            'fecha='.now()->toIso8601String(),
            'origen='.PaqueteResguardosGelia::ORIGEN,
            'dump=gelia_resguardos_database.sql.gz',
            'archivos=gelia_resguardos_archivos.tar.gz',
            'instrucciones=INTEGRACION.txt',
            'nota=Resguardos ya adaptados a pdv_resguardo_*. Restaurar solo con pdv:restaurar-resguardos-gelia. No sustituye un respaldo completo de la base.',
            'tablas='.implode(',', PaqueteResguardosGelia::tablas()),
            'resguardos='.$conteos['pdv_resguardos'],
            'bultos='.$conteos['pdv_resguardo_bultos'],
            'eventos='.$conteos['pdv_resguardo_eventos'],
            'entregas='.$conteos['pdv_resguardo_entregas'],
            'entrega_bultos='.$conteos['pdv_resguardo_entrega_bultos'],
            'evidencias='.$conteos['pdv_resguardo_evidencias'],
            'rutas_referenciadas='.($conteos['rutas_empaquetadas'] + $conteos['rutas_faltantes']),
            'rutas_empaquetadas='.$conteos['rutas_empaquetadas'],
            'rutas_faltantes='.$conteos['rutas_faltantes'],
            'sha256_dump='.hash_file('sha256', $sql),
            'sha256_archivos='.hash_file('sha256', $tar),
            'sha256_rutas='.hash_file('sha256', $lista),
            'sha256_faltantes='.hash_file('sha256', $faltantes),
        ];

        file_put_contents($destino.'/MANIFEST.txt', implode("\n", $lineas)."\n");
    }
}
