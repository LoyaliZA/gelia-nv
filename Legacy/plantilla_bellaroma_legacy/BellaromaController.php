<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Vtiful\Kernel\Excel;
use Vtiful\Kernel\Format;
use App\Models\BellaromaTemplate;
use App\Models\BellaromaConfig;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\PlantillaBellaromaMail;
use Illuminate\Support\Facades\DB; // <-- Agregado para leer configuraciones
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class BellaromaController extends Controller
{
    public function index()
    {
        $hoy = date('Y-m-d');

        // Plantillas generadas exactamente hoy (Orden estricto por ID descendente)
        $templatesHoy = BellaromaTemplate::whereDate('created_at', $hoy)
            ->orderByDesc('id')
            ->get();

        // Historial (Orden estricto por ID descendente)
        $templatesHistorial = BellaromaTemplate::whereDate('created_at', '<', $hoy)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $configHora = BellaromaConfig::where('llave', 'hora_notificacion')->first();
        $horaNotificacion = $configHora ? $configHora->valor : '';

        $generadoHoy = $templatesHoy->isNotEmpty();
        $driveFolderId = config('services.google_drive.folder_id'); // <-- Extraemos el ID de la carpeta raíz

        return view('bellaroma', compact('templatesHoy', 'templatesHistorial', 'horaNotificacion', 'generadoHoy', 'driveFolderId'));
    }

    public function generar(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $request->validate([
            'existencias' => 'required|file',
            'precios' => 'required|file',
        ]);

        // Condicional: Si el usuario activó el switch, damos la fecha de mañana
        $fecha = $request->has('para_manana') ? date('d-m-y', strtotime('+1 day')) : date('d-m-y');
        $hashUnico = uniqid();

        $nombreVisual = "PLANTILLA-BELLAROMA-{$fecha}.xlsx";
        $nombreFisico = "PLANTILLA-BELLAROMA-{$fecha}_{$hashUnico}.xlsx";

        $rutaTemp = sys_get_temp_dir();

        // 1. EXTRAER PORCENTAJES DE LA BASE DE DATOS
        $settings = DB::table('gelia_settings')->pluck('value', 'key');
        
        $mBronce   = 1 - ((float)($settings['pct_bronce'] ?? 12.39) / 100);
        $mPlata    = 1 - ((float)($settings['pct_plata'] ?? 14.14) / 100);
        $mOro      = 1 - ((float)($settings['pct_oro'] ?? 15.89) / 100);
        $mDiamante = 1 - ((float)($settings['pct_diamante'] ?? 17.65) / 100);

        // 2. PROCESAR PRECIOS
        $diccionarioPrecios = [];
        $this->procesarArchivoSeguro($request->file('precios'), function ($ruta) use (&$diccionarioPrecios) {
            (new FastExcel)->withoutHeaders()->import($ruta, function ($linea) use (&$diccionarioPrecios) {
                if (!isset($linea[1]) || $linea[1] == 'CODIGO_DEL_PRODUCTO' || $linea[1] == '') return;
                $sku = ltrim(trim((string)$linea[1]), '0');
                $precio = $linea[7] ?? 0;
                $diccionarioPrecios[$sku] = is_numeric($precio) ? (float)$precio : 0.0;
            });
        });

        $listaProductos = [];

        // 3. CALCULAR EN BASE A NIVEL BRONCE
        $this->procesarArchivoSeguro($request->file('existencias'), function ($ruta) use (&$listaProductos, $diccionarioPrecios, $mBronce) {
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

        // Configuración de Estilos Originales
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

        // Estilos para Valores Informativos (Gris y Cursiva)
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

        // 4. FÓRMULAS DE AUTO-CÁLCULO EXCEL
        // La tabla empieza en la fila 7, por lo que sumamos de E7/F7 en adelante
        $calcBronce = '=TEXT(SUMPRODUCT(E7:E50000,F7:F50000), "$#,##0.00")';
        
        // Fórmulas matemáticas que revierten el descuento Bronce y aplican el del nivel correspondiente en tiempo real
        $calcPlata = '=TEXT((SUMPRODUCT(E7:E50000,F7:F50000)/' . number_format($mBronce, 4, '.', '') . ')*' . number_format($mPlata, 4, '.', '') . ', "$#,##0.00")';
        $calcOro = '=TEXT((SUMPRODUCT(E7:E50000,F7:F50000)/' . number_format($mBronce, 4, '.', '') . ')*' . number_format($mOro, 4, '.', '') . ', "$#,##0.00")';
        $calcDiamante = '=TEXT((SUMPRODUCT(E7:E50000,F7:F50000)/' . number_format($mBronce, 4, '.', '') . ')*' . number_format($mDiamante, 4, '.', '') . ', "$#,##0.00")';

        // 5. INSERCIÓN DE CELDAS ESTRUCTURADAS
        // Fila 0: Importe Oficial y Título de Notas
        $excel->insertText(0, 0, 'IMPORTE BRONCE', '', $estiloCabeceraBorde);
        $excel->insertFormula(0, 1, $calcBronce, $estiloCabeceraBorde);
        $excel->insertText(0, 2, 'NOTAS IMPORTANTES:', '', $estiloNegrita); // <-- Agregado en Col C
        $excel->insertText(0, 7, 'INSTRUCCIONES:', '', $estiloNegrita);
        
        // Fila 1: Cantidad y Nota 1
        $excel->insertText(1, 0, 'CANTIDAD', '', $estiloCabeceraBorde);
        $excel->insertFormula(1, 1, '=SUM(F7:F50000)', $estiloCabeceraBorde);
        $excel->insertText(1, 2, '1. El cálculo del importe total toma como base inicial el nivel de descuento Bronce.', '', $estiloReferencia); // <-- Agregado en Col C
        $excel->insertText(1, 7, '1.- PARA LLENAR EL FORMATO DE PEDIDO, UNICAMENTE SE TIENE QUE LLENAR LA COLUMNA F CON LAS CANTIDADES DESEADAS', '', $estiloNegrita);
        
        // Fila 2: Plata y Nota 2
        $excel->insertText(2, 0, 'Nivel Plata: ', '', $estiloReferencia);
        $excel->insertFormula(2, 1, $calcPlata, $estiloReferenciaVal);
        $excel->insertText(2, 2, '2. Los importes en niveles superiores son proyecciones de referencia sobre el total.', '', $estiloReferencia); // <-- Agregado en Col C
        $excel->insertText(2, 7, '2.- SE GUARDA EL ARCHIVO', '', $estiloNegrita);
        
        // Fila 3: Oro y Nota 3
        $excel->insertText(3, 0, 'Nivel Oro: ', '', $estiloReferencia);
        $excel->insertFormula(3, 1, $calcOro, $estiloReferenciaVal);
        $excel->insertText(3, 2, '3. Su nivel de descuento mejorará progresivamente en función de su volumen de compras.', '', $estiloReferencia); // <-- Agregado en Col C
        $excel->insertText(3, 7, '3.- SE ENVIA A SU EJECUTIVO DE VENTAS', '', $estiloNegrita);
        
        // Fila 4: Diamante
        $excel->insertText(4, 0, 'Nivel Diamante: ', '', $estiloReferencia);
        $excel->insertFormula(4, 1, $calcDiamante, $estiloReferenciaVal);
        $excel->insertText(4, 7, 'OBSERVACIONES:', '', $estiloNegrita);
        
        // Fila 5: Cabeceras
        $excel->insertText(5, 0, 'FOLIO', '', $estiloCabeceraBorde);
        $excel->insertText(5, 1, 'SKU', '', $estiloCabeceraBorde);
        $excel->insertText(5, 2, 'Descripción', '', $estiloCabeceraBorde);
        $excel->insertText(5, 3, 'Existencia', '', $estiloCabeceraBorde);
        $excel->insertText(5, 4, 'MAYOREO', '', $estiloCabeceraBorde);
        $excel->insertText(5, 5, 'PEDIDO', '', $estiloCabeceraBorde);
        $excel->insertText(5, 7, '1.- TODOS LOS PRODUCTOS QUE EN EXISTENCIA TENGAN UN "+10", SIGNIFICA QUE HAY MAS DE 10 EN INVENTARIO', '', $estiloNegrita);

        // Rellenar Productos a partir de la fila 6
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
        $rutaFinalTemp = $excel->output();

        $rutaStorage = 'bellaroma/' . $nombreFisico;
        Storage::disk('public')->put($rutaStorage, file_get_contents($rutaFinalTemp));

        $tamanoKb = round(filesize($rutaFinalTemp) / 1024, 2) . ' KB';
        unlink($rutaFinalTemp);

        $correoDestino = BellaromaConfig::where('llave', 'correo_destino')->value('valor');
        $enviadoExitosamente = false;

        if (!empty($correoDestino) && filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($correoDestino)->send(new PlantillaBellaromaMail($rutaStorage, $nombreVisual));
                $enviadoExitosamente = true;
            } catch (\Exception $e) {
                \Log::error('AROMAS - Error enviando correo Bellaroma: ' . $e->getMessage());
            }
        }

        $driveId = $this->subirAGoogleDrive($rutaStorage, $nombreVisual);

        $template = BellaromaTemplate::create([
            'nombre_archivo' => $nombreVisual,
            'ruta_fisica' => $rutaStorage,
            'tamano_kb' => $tamanoKb,
            'enviado_correo' => $enviadoExitosamente,
            'subido_drive' => $driveId ? true : false,
            'drive_id' => $driveId 
        ]);

        return response()->json([
            'message' => 'Plantilla generada exitosamente.',
            'template' => $template,
            'download_url' => route('bellaroma.descargar', $template->id)
        ]);
    }

    public function descargar($id)
    {
        $template = BellaromaTemplate::findOrFail($id);

        if (!Storage::disk('public')->exists($template->ruta_fisica)) {
            abort(404, 'El archivo físico ya no existe en el servidor.');
        }

        return Storage::disk('public')->download($template->ruta_fisica, $template->nombre_archivo);
    }

    public function eliminar($id)
    {
        $template = BellaromaTemplate::findOrFail($id);

        if (Storage::disk('public')->exists($template->ruta_fisica)) {
            Storage::disk('public')->delete($template->ruta_fisica);
        }
        $template->delete();

        return response()->json(['message' => 'Plantilla eliminada del sistema.']);
    }

    public function verificarPin(Request $request)
    {
        $request->validate(['pin' => 'required|string']);

        $pinConfig = BellaromaConfig::firstOrCreate(
            ['llave' => 'admin_pin'],
            ['valor' => Hash::make('1234'), 'descripcion' => 'PIN de acceso a configuración']
        );

        if (Hash::check($request->pin, $pinConfig->valor)) {
            $configs = BellaromaConfig::where('llave', '!=', 'admin_pin')->pluck('valor', 'llave');
            return response()->json(['success' => true, 'config' => $configs]);
        }

        return response()->json(['success' => false, 'message' => 'PIN de acceso incorrecto.'], 401);
    }

    public function guardarConfiguracion(Request $request)
    {
        $request->validate([
            'pin_actual' => 'required|string',
            'nuevo_pin' => 'nullable|string|min:4',
            'hora_notificacion' => 'nullable|string',
            'correo_destino' => 'nullable|email'
        ]);

        $pinConfig = BellaromaConfig::where('llave', 'admin_pin')->first();

        if (!$pinConfig || !Hash::check($request->pin_actual, $pinConfig->valor)) {
            return response()->json(['success' => false, 'message' => 'Autorización denegada. PIN incorrecto.'], 401);
        }

        if ($request->filled('nuevo_pin')) {
            $pinConfig->update(['valor' => Hash::make($request->nuevo_pin)]);
        }

        BellaromaConfig::updateOrCreate(
            ['llave' => 'hora_notificacion'],
            ['valor' => $request->hora_notificacion, 'descripcion' => 'Hora programada para alerta (HH:MM)']
        );

        BellaromaConfig::updateOrCreate(
            ['llave' => 'correo_destino'],
            ['valor' => $request->correo_destino, 'descripcion' => 'Email receptor de plantillas']
        );

        return response()->json(['success' => true, 'message' => 'Configuración actualizada exitosamente.']);
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

    private function subirAGoogleDrive($rutaFisica, $nombreArchivo)
    {
        try {
            $clientId = config('services.google_drive.client_id');
            $clientSecret = config('services.google_drive.client_secret');
            $refreshToken = config('services.google_drive.refresh_token');
            $folderId = config('services.google_drive.folder_id');

            if (empty($clientId) || empty($refreshToken) || empty($folderId)) {
                \Log::warning('AROMAS - Faltan credenciales OAuth o Folder ID de Google Drive.');
                return false;
            }

            $client = new Client();
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->refreshToken($refreshToken);
            $client->addScope(Drive::DRIVE_FILE);

            $service = new Drive($client);

            $fileMetadata = new DriveFile([
                'name' => $nombreArchivo,
                'parents' => [$folderId]
            ]);

            $content = file_get_contents(storage_path('app/public/' . $rutaFisica));

            $file = $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return $file->id;
        } catch (\Exception $e) {
            \Log::error('AROMAS - Error Google Drive OAuth: ' . $e->getMessage());
            return null;
        }
    }
}