<?php

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\ProductoCosto;
use App\Models\ProductoImagen;
use App\Models\ProductoImagenLote;
use App\Models\ProductoImagenLoteArchivo;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\Productos\Imagenes\AlmacenArchivoImagenProducto;
use App\Services\Productos\Imagenes\GestionarGaleriaProductoService;
use App\Services\Productos\Imagenes\GestionarLoteImagenProductoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/lib.php';

$comando = $GLOBALS['argv'][1] ?? '';
$app = fase5_app();

function salir(array $datos, int $codigo = 0): void
{
    echo json_encode($datos, JSON_UNESCAPED_UNICODE)."\n";
    exit($codigo);
}

function galeria(): GestionarGaleriaProductoService
{
    return app(GestionarGaleriaProductoService::class);
}

function resumen(int $productoId): array
{
    $imagenes = ProductoImagen::query()->where('producto_id', $productoId)->orderBy('id')->get();

    return [
        'producto' => fase5_fila_producto($productoId),
        'imagenes' => $imagenes->map(fn (ProductoImagen $imagen) => [
            'id' => $imagen->id,
            'posicion' => $imagen->posicion,
            'principal' => $imagen->principal_producto_id,
            'estado' => $imagen->estado,
            'original' => $imagen->clave_original,
            'miniatura' => $imagen->clave_miniatura,
            'original_existe' => $imagen->clave_original ? app(AlmacenArchivoImagenProducto::class)->existe($imagen->clave_original) : false,
            'miniatura_existe' => $imagen->clave_miniatura ? app(AlmacenArchivoImagenProducto::class)->existe($imagen->clave_miniatura) : false,
            'miniatura_en_directorio_original' => $imagen->clave_miniatura && $imagen->clave_original
                ? dirname($imagen->clave_miniatura) === dirname($imagen->clave_original)
                : null,
        ])->all(),
        'limpiezas' => DB::table('producto_imagen_limpiezas')->count(),
    ];
}

match ($comando) {
    'preparar' => (function () {
        $kernel = app(Illuminate\Contracts\Console\Kernel::class);
        $codigo = $kernel->call('migrate', ['--force' => true, '--database' => 'mysql']);
        $token = DB::table('fase5_marcador')->value('token');
        if ($codigo !== 0 || $token !== 'fase5-aislada' || DB::connection()->getDatabaseName() !== 'fase5_sintetica') {
            salir(['ok' => false, 'migrate' => $codigo, 'token' => $token], 3);
        }
        $columnas = collect(DB::select('show columns from productos'))->pluck('Field')->all();
        $rh = 'ok';
        try {
            DB::table('productos')->select(['id', 'sku', 'descripcion', 'costo', 'precio_venta'])->limit(1)->get();
        } catch (Throwable $e) {
            $rh = $e->getMessage();
        }
        if (! is_dir('/tmp/fase5-imagenes')) {
            mkdir('/tmp/fase5-imagenes', 0777, true);
        }
        $sucursal = Sucursal::query()->create(['codigo' => 'F5', 'nombre' => 'Sucursal sintetica', 'activo' => true]);
        $almacenA = Almacen::query()->create(['codigo' => 'F5A', 'nombre' => 'Almacen A', 'sucursal_id' => $sucursal->id, 'activo' => true]);
        $almacenB = Almacen::query()->create(['codigo' => 'F5B', 'nombre' => 'Almacen B', 'sucursal_id' => $sucursal->id, 'activo' => true]);
        $producto = fase5_producto(910001);
        ProductoCosto::query()->create(['producto_id' => $producto->id, 'almacen_id' => $almacenA->id, 'costo' => '10.50', 'precio_venta' => '20.00']);
        ProductoCosto::query()->create(['producto_id' => $producto->id, 'almacen_id' => $almacenB->id, 'costo' => '99.25', 'precio_venta' => '80.00']);
        $fresco = $producto->fresh();
        $user = User::query()->create([
            'name' => 'Verificacion Fase 5',
            'email' => 'fase5-sintetica@example.test',
            'password' => Hash::make('fase5-temp-only'),
        ]);
        salir([
            'ok' => true,
            'migrate' => $codigo,
            'base' => DB::connection()->getDatabaseName(),
            'token' => $token,
            'columnas_costo_en_productos' => array_values(array_intersect($columnas, ['costo', 'precio_venta'])),
            'rh_select' => $rh,
            'accessor_costo' => $fresco->costo,
            'accessor_precio' => $fresco->precio_venta,
            'ids_costo' => DB::table('producto_costos')->orderBy('id')->pluck('costo'),
            'user_id' => $user->id,
        ]);
    })(),
    'sembrar' => (function () {
        $modo = $GLOBALS['argv'][2] ?? '';
        $producto = fase5_producto((int) $GLOBALS['argv'][3]);
        $antes = fase5_fila_producto($producto->id);
        $imagenes = [];
        if (in_array($modo, ['dos', 'tres', 'una'], true)) {
            $n = $modo === 'tres' ? 3 : ($modo === 'dos' ? 2 : 1);
            for ($i = 1; $i <= $n; $i++) {
                $ruta = '/tmp/fase5-imagenes/src-'.$producto->id.'-'.$i.'.png';
                fase5_png($ruta, $modo === 'una' ? 1600 : 32);
                $imagenes[] = galeria()->agregar($producto, fase5_upload($ruta, $producto->folio.'-'.$i.'.png'))->id;
            }
        }
        if ($modo === 'lote') {
            $ruta = '/tmp/fase5-imagenes/lote-'.$producto->folio.'.png';
            fase5_png($ruta, 32);
            $user = User::query()->firstOrFail();
            $lote = app(GestionarLoteImagenProductoService::class)->analizar($user, [
                fase5_upload($ruta, $producto->folio.'.png'),
            ]);
            $archivo = $lote->archivos()->firstOrFail();
            salir([
                'producto_id' => $producto->id,
                'antes' => $antes,
                'lote_id' => $lote->id,
                'archivo_id' => $archivo->id,
                'clasificacion' => $archivo->clasificacion,
                'estado' => $archivo->estado,
                'producto_archivo' => $archivo->producto_id,
            ]);
        }
        salir([
            'producto_id' => $producto->id,
            'antes' => $antes,
            'imagenes' => $imagenes,
        ]);
    })(),
    'observar-archivo' => (function () {
        $archivoId = (int) $GLOBALS['argv'][2];
        $listo = $GLOBALS['argv'][3];
        $soltar = $GLOBALS['argv'][4];
        DB::beginTransaction();
        DB::select('select id from producto_imagen_lote_archivos where id = ? for update', [$archivoId]);
        file_put_contents($listo, (string) getmypid());
        $limite = time() + 40;
        while (! is_file($soltar) && time() < $limite) {
            usleep(50000);
        }
        DB::commit();
        salir(['ok' => is_file($soltar)]);
    })(),
    'observar' => (function () {
        $productoId = (int) $GLOBALS['argv'][2];
        $listo = $GLOBALS['argv'][3];
        $soltar = $GLOBALS['argv'][4];
        DB::beginTransaction();
        DB::select('select id from productos where id = ? for update', [$productoId]);
        file_put_contents($listo, (string) getmypid());
        $limite = time() + 40;
        while (! is_file($soltar) && time() < $limite) {
            usleep(50000);
        }
        DB::commit();
        salir(['ok' => is_file($soltar)]);
    })(),
    'carga' => (function () {
        $producto = Producto::query()->findOrFail((int) $GLOBALS['argv'][2]);
        $ruta = '/tmp/fase5-imagenes/carga-'.getmypid().'.png';
        fase5_png($ruta);
        $imagen = galeria()->agregar($producto, fase5_upload($ruta, 'carga-'.getmypid().'.png'));
        salir(['imagen_id' => $imagen->id, 'posicion' => $imagen->posicion, 'principal' => $imagen->principal_producto_id]);
    })(),
    'principal' => (function () {
        $producto = Producto::query()->findOrFail((int) $GLOBALS['argv'][2]);
        $imagen = ProductoImagen::query()->findOrFail((int) $GLOBALS['argv'][3]);
        galeria()->marcarPrincipal($producto, $imagen);
        salir(['ok' => true, 'imagen_id' => $imagen->id]);
    })(),
    'reordenar' => (function () {
        $producto = Producto::query()->findOrFail((int) $GLOBALS['argv'][2]);
        $orden = array_map('intval', explode(',', $GLOBALS['argv'][3]));
        galeria()->reordenar($producto, $orden);
        salir(['ok' => true, 'orden' => $orden]);
    })(),
    'reintento' => (function () {
        echo 'PID '.getmypid()."\n";
        $producto = Producto::query()->findOrFail((int) $GLOBALS['argv'][2]);
        $imagen = ProductoImagen::query()->findOrFail((int) $GLOBALS['argv'][3]);
        $resultado = galeria()->reintentarMiniatura($producto, $imagen);
        salir([
            'estado' => $resultado->estado,
            'original' => $resultado->clave_original,
            'miniatura' => $resultado->clave_miniatura,
        ]);
    })(),
    'confirmar' => (function () {
        echo 'PID '.getmypid()."\n";
        $lote = ProductoImagenLote::query()->findOrFail((int) $GLOBALS['argv'][2]);
        $archivoId = (int) $GLOBALS['argv'][3];
        $resultado = app(GestionarLoteImagenProductoService::class)->confirmar($lote, [[
            'id' => $archivoId,
            'accion' => 'confirmar',
        ]]);
        salir(['resultado' => $resultado]);
    })(),
    'reemplazar' => (function () {
        $producto = Producto::query()->findOrFail((int) $GLOBALS['argv'][2]);
        $imagen = ProductoImagen::query()->findOrFail((int) $GLOBALS['argv'][3]);
        $ruta = '/tmp/fase5-imagenes/reemplazo-'.getmypid().'.png';
        fase5_png($ruta, 40);
        $actual = galeria()->reemplazar($producto, $imagen, fase5_upload($ruta, 'reemplazo.png'));
        salir(['original' => $actual->clave_original, 'miniatura' => $actual->clave_miniatura, 'estado' => $actual->estado]);
    })(),
    'resumen' => (function () {
        salir(resumen((int) $GLOBALS['argv'][2]));
    })(),
    'vigilar' => (function () {
        $archivo = $GLOBALS['argv'][2];
        $limite = time() + 30;
        while (time() < $limite) {
            $filas = DB::select('select trx_id, trx_state, left(coalesce(trx_query, ""), 180) as trx_query from information_schema.innodb_trx');
            file_put_contents($archivo, json_encode($filas, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
            usleep(40000);
        }
        salir(['ok' => true]);
    })(),
    'trx' => (function () {
        $filas = DB::select('select trx_id, trx_state, left(coalesce(trx_query, ""), 180) as trx_query from information_schema.innodb_trx');
        salir(['trx' => $filas]);
    })(),
    'archivo' => (function () {
        $fila = ProductoImagenLoteArchivo::query()->findOrFail((int) $GLOBALS['argv'][2]);
        salir([
            'estado' => $fila->estado,
            'imagen_id' => $fila->imagen_id,
            'reclamado_en' => $fila->reclamado_en?->toDateTimeString(),
        ]);
    })(),
    default => salir(['error' => 'comando'], 1),
};
