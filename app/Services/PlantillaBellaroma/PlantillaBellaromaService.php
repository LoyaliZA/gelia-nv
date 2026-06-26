<?php

namespace App\Services\PlantillaBellaroma;

use App\Models\BellaromaTemplate;
use App\Models\BellaromaConfig;
use App\Models\User;
use App\Notifications\PlantillaBellaromaGenerada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;

class PlantillaBellaromaService
{
    public function procesar($archivoExistencias, $archivoPrecios, $tipoEntrega = 'inmediata', $fechaProgramada = null)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $timezone = config('app.timezone', 'America/Mexico_City');
        $fechaEntrega = null;
        if ($tipoEntrega === 'manana') {
            $fechaEntrega = now($timezone)->addDay()->setTime(7, 0, 0);
        } elseif ($tipoEntrega === 'fecha' && $fechaProgramada) {
            $fechaSoloFecha = substr($fechaProgramada, 0, 10);
            $fechaEntrega = \Carbon\Carbon::createFromFormat('Y-m-d', $fechaSoloFecha, $timezone)->setTime(7, 0, 0);
        }

        $fecha = $fechaEntrega ? $fechaEntrega->format('d-m-y') : now($timezone)->format('d-m-y');
        $hashUnico = uniqid();

        $nombreVisual = "PLANTILLA-PEDIDOS-{$fecha}.xlsx";
        $nombreFisico = "PLANTILLA-PEDIDOS-{$fecha}_{$hashUnico}.xlsx";

        $rutaTemp = sys_get_temp_dir();

        // 1. EXTRAER PORCENTAJES DE LA BASE DE DATOS
        $settings = DB::table('gelia_settings')->pluck('value', 'key');
        
        $mBronce   = 1 - ((float)($settings['pct_bronce'] ?? 12.39) / 100);
        $mPlata    = 1 - ((float)($settings['pct_plata'] ?? 14.14) / 100);
        $mOro      = 1 - ((float)($settings['pct_oro'] ?? 15.89) / 100);
        $mDiamante = 1 - ((float)($settings['pct_diamante'] ?? 17.65) / 100);

        // 2. PROCESAR PRECIOS
        $diccionarioPrecios = [];
        $this->procesarArchivoSeguro($archivoPrecios, function ($ruta) use (&$diccionarioPrecios) {
            (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$diccionarioPrecios) {
                if (!isset($linea[1]) || $linea[1] == 'CODIGO_DEL_PRODUCTO' || $linea[1] == '') return;
                $sku = ltrim(trim((string)$linea[1]), '0');
                $precio = $linea[7] ?? 0;
                $diccionarioPrecios[$sku] = is_numeric($precio) ? (float)$precio : 0.0;
            });
        });

        $listaProductos = [];

        // 3. CALCULAR EN BASE A NIVEL BRONCE
        $this->procesarArchivoSeguro($archivoExistencias, function ($ruta) use (&$listaProductos, $diccionarioPrecios, $mBronce) {
            $reader = (new FastExcel)->withoutHeaders()->import($ruta);
            foreach ($reader as $linea) {
                if (!isset($linea[4]) || $linea[4] == 'Código') continue;

                $skuCrudo = trim((string)$linea[4]);
                if ($skuCrudo === '') continue;
                $skuBuscador = ltrim($skuCrudo, '0');

                $existenciaReal = (int)($linea[10] ?? 0);
                if ($existenciaReal <= 0) continue;

                $folio = $linea[3] ?? '';
                $descripcion = $linea[5] ?? '';

                $pg = $diccionarioPrecios[$skuBuscador] ?? 0.0;
                
                // El precio principal que se muestra es Bronce
                $mayoreo = $pg * $mBronce;

                $listaProductos[] = [
                    'folio' => (string)$folio,
                    'sku' => (string)$skuCrudo,
                    'descripcion' => (string)$descripcion,
                    'existenciaMostrar' => $existenciaReal > 10 ? '+10' : (string)$existenciaReal,
                    'mayoreo' => round($mayoreo, 2)
                ];
            }
        });

        usort($listaProductos, function ($a, $b) {
            return strcasecmp($a['descripcion'], $b['descripcion']);
        });

        $config = ['path' => $rutaTemp];
        $excel = new Excel($config);
        $excel->fileName($nombreFisico, 'Plantilla');

        $this->aplicarFormato($excel, $listaProductos, $mBronce, $mPlata, $mOro, $mDiamante);

        $rutaFinalTemp = $excel->output();

        $rutaStorage = 'bellaroma/' . $nombreFisico;
        Storage::disk('public')->put($rutaStorage, file_get_contents($rutaFinalTemp));

        $tamanoKb = round(filesize($rutaFinalTemp) / 1024, 2) . ' KB';
        unlink($rutaFinalTemp);

        $template = BellaromaTemplate::create([
            'nombre_archivo' => $nombreVisual,
            'ruta_fisica' => $rutaStorage,
            'tamano_kb' => $tamanoKb,
            'enviado_correo' => true, // Lo marcaremos como true ya que se despacha a la cola
            'fecha_entrega' => $fechaEntrega,
        ]);

        $this->notificarUsuarios($template);

        return $template;
    }

    private function aplicarFormato($excel, $listaProductos, $mBronce, $mPlata, $mOro, $mDiamante)
    {
        $formato = new Format($excel->getHandle());
        $estiloDesbloqueado = $formato->unlocked()->toResource();
        
        $formatoNegritaObj = new Format($excel->getHandle());
        $estiloNegrita = $formatoNegritaObj->bold()->toResource();
        
        $formatoCabeceraBordeObj = new Format($excel->getHandle());
        $estiloCabeceraBorde = $formatoCabeceraBordeObj->bold()->border(Format::BORDER_THIN)->toResource();
        
        $formatoBordeObj = new Format($excel->getHandle());
        $estiloBorde = $formatoBordeObj->border(Format::BORDER_THIN)->toResource();
        
        $formatoPedidoObj = new Format($excel->getHandle());
        $estiloPedidoData = $formatoPedidoObj->border(Format::BORDER_THIN)->unlocked()->toResource();

        $formatoRefObj = new Format($excel->getHandle());
        $estiloReferencia = $formatoRefObj->fontColor(Format::COLOR_GRAY)->italic()->toResource();
        
        $formatoRefValObj = new Format($excel->getHandle());
        $estiloReferenciaVal = $formatoRefValObj->fontColor(Format::COLOR_GRAY)->italic()->border(Format::BORDER_THIN)->toResource();

        $excel->setColumn('A:A', 15.0);
        $excel->setColumn('B:B', 15.0);
        $excel->setColumn('C:C', 65.0);
        $excel->setColumn('D:E', 15.0);
        $excel->setColumn('F:F', 15.0, $estiloDesbloqueado);
        $excel->setColumn('H:H', 75.0);

        $calcBronce = '=TEXT(SUMPRODUCT(E7:E50000,F7:F50000), "$#,##0.00")';
        $calcPlata = '=TEXT((SUMPRODUCT(E7:E50000,F7:F50000)/' . number_format($mBronce, 4, '.', '') . ')*' . number_format($mPlata, 4, '.', '') . ', "$#,##0.00")';
        $calcOro = '=TEXT((SUMPRODUCT(E7:E50000,F7:F50000)/' . number_format($mBronce, 4, '.', '') . ')*' . number_format($mOro, 4, '.', '') . ', "$#,##0.00")';
        $calcDiamante = '=TEXT((SUMPRODUCT(E7:E50000,F7:F50000)/' . number_format($mBronce, 4, '.', '') . ')*' . number_format($mDiamante, 4, '.', '') . ', "$#,##0.00")';

        $excel->insertText(0, 0, 'IMPORTE BRONCE', '', $estiloCabeceraBorde);
        $excel->insertFormula(0, 1, $calcBronce, $estiloCabeceraBorde);
        $excel->insertText(0, 2, 'NOTAS IMPORTANTES:', '', $estiloNegrita);
        $excel->insertText(0, 7, 'INSTRUCCIONES:', '', $estiloNegrita);
        
        $excel->insertText(1, 0, 'CANTIDAD', '', $estiloCabeceraBorde);
        $excel->insertFormula(1, 1, '=SUM(F7:F50000)', $estiloCabeceraBorde);
        $excel->insertText(1, 2, '1. El cálculo del importe total toma como base inicial el nivel de descuento Bronce.', '', $estiloReferencia);
        $excel->insertText(1, 7, '1.- PARA LLENAR EL FORMATO DE PEDIDO, UNICAMENTE SE TIENE QUE LLENAR LA COLUMNA F CON LAS CANTIDADES DESEADAS', '', $estiloNegrita);
        
        $excel->insertText(2, 0, 'Nivel Plata: ', '', $estiloReferencia);
        $excel->insertFormula(2, 1, $calcPlata, $estiloReferenciaVal);
        $excel->insertText(2, 2, '2. Los importes en niveles superiores son proyecciones de referencia sobre el total.', '', $estiloReferencia);
        $excel->insertText(2, 7, '2.- SE GUARDA EL ARCHIVO', '', $estiloNegrita);
        
        $excel->insertText(3, 0, 'Nivel Oro: ', '', $estiloReferencia);
        $excel->insertFormula(3, 1, $calcOro, $estiloReferenciaVal);
        $excel->insertText(3, 2, '3. Su nivel de descuento mejorará progresivamente en función de su volumen de compras.', '', $estiloReferencia);
        $excel->insertText(3, 7, '3.- SE ENVIA A SU EJECUTIVO DE VENTAS', '', $estiloNegrita);
        
        $excel->insertText(4, 0, 'Nivel Diamante: ', '', $estiloReferencia);
        $excel->insertFormula(4, 1, $calcDiamante, $estiloReferenciaVal);
        $excel->insertText(4, 7, 'OBSERVACIONES:', '', $estiloNegrita);
        
        $excel->insertText(5, 0, 'FOLIO', '', $estiloCabeceraBorde);
        $excel->insertText(5, 1, 'SKU', '', $estiloCabeceraBorde);
        $excel->insertText(5, 2, 'Descripción', '', $estiloCabeceraBorde);
        $excel->insertText(5, 3, 'Existencia', '', $estiloCabeceraBorde);
        $excel->insertText(5, 4, 'MAYOREO', '', $estiloCabeceraBorde);
        $excel->insertText(5, 5, 'PEDIDO', '', $estiloCabeceraBorde);
        $excel->insertText(5, 7, '1.- TODOS LOS PRODUCTOS QUE EN EXISTENCIA TENGAN UN "+10", SIGNIFICA QUE HAY MAS DE 10 EN INVENTARIO', '', $estiloNegrita);

        $filaActual = 6;
        foreach ($listaProductos as $producto) {
            $excel->insertText($filaActual, 0, $producto['folio']);
            $excel->insertText($filaActual, 1, $producto['sku']);
            $excel->insertText($filaActual, 2, $producto['descripcion']);
            $excel->insertText($filaActual, 3, $producto['existenciaMostrar'], '', $estiloBorde);
            $excel->insertText($filaActual, 4, (float)$producto['mayoreo'], '$#,##0.00');
            $excel->insertText($filaActual, 5, '', '', $estiloPedidoData);
            $filaActual++;
        }

        $excel->protection('BELLAROMA123');
    }

    private function procesarArchivoSeguro($archivo, callable $callbackLogica)
    {
        if (!$archivo) return;
        $nombreTemp = 'temp_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
        $rutaCompleta = sys_get_temp_dir() . '/' . $nombreTemp;
        $archivo->move(sys_get_temp_dir(), $nombreTemp);
        try {
            $callbackLogica($rutaCompleta);
        } finally {
            if (file_exists($rutaCompleta)) unlink($rutaCompleta);
        }
    }

    private function notificarUsuarios(BellaromaTemplate $template)
    {
        $config = BellaromaConfig::where('llave', 'notified_users')->first();
        if ($config && !empty($config->valor)) {
            $userIds = json_decode($config->valor, true);
            if (is_array($userIds) && count($userIds) > 0) {
                $usuarios = User::whereIn('id', $userIds)->get();
                foreach ($usuarios as $usuario) {
                    $notificacion = new PlantillaBellaromaGenerada($template);
                    if ($template->fecha_entrega) {
                        $notificacion->delay(\Carbon\Carbon::parse($template->fecha_entrega));
                    }
                    $usuario->notify($notificacion);
                }
            }
        }
    }
}
