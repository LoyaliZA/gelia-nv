<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\ConfiguracionEntrega;
use App\Models\CatalogoZonaEntrega;
use Illuminate\Support\Facades\Crypt;

class EntregasController extends Controller
{
    /**
     * Renderiza la vista principal del cotizador de entregas.
     *
     * @return Response
     */
    public function index(): Response
    {
        $configuracion = ConfiguracionEntrega::first();
        
        $apiKey = null;
        if ($configuracion && $configuracion->api_key_google) {
            try {
                $apiKey =Crypt::decryptString($configuracion->api_key_google);
            } catch (\Exception $e) {
                $apiKey = null;
            }
        }

        // 1. Obtenemos las zonas activas
        $zonas = CatalogoZonaEntrega::where('activo', true)->get();

        // 2. Formateamos las coordenadas para el Frontend (Google Maps)
        $zonasFormateadas = $zonas->map(function ($zona) {
            // Extraemos el arreglo de coordenadas [ [lon, lat], [lon, lat] ]
            $coordenadasGeoJson = $zona->coordenadas_poligono['coordinates'][0] ?? [];
            
            // Transformamos al formato { lat: y, lng: x }
            $rutasMaps = array_map(function ($punto) {
                return [
                    'lat' => (float) $punto[1],
                    'lng' => (float) $punto[0],
                ];
            }, $coordenadasGeoJson);

            return [
                'id' => $zona->id,
                'nombre' => $zona->nombre,
                'color_hex' => $zona->color_hex,
                'rutas_formateadas' => $rutasMaps, // Esta es la propiedad que lee MapaGoogle.jsx
            ];
        });

        return Inertia::render('Entregas/Index', [
            'configuracion' => $configuracion,
            'googleApiKey' => $apiKey,
            'zonas' => $zonasFormateadas 
        ]);
    }

    /**
     * Actualiza los parámetros logísticos y cifra credenciales sensibles.
     */
    public function actualizarConfiguracion(Request $request)
    {
        $request->validate([
            'latitud_origen' => 'required|numeric',
            'longitud_origen' => 'required|numeric',
            'radio_tolerancia_km' => 'required|numeric|min:1',
            'tarifa_envio_extra' => 'required|numeric|min:0',
            'cobro_extra_por_km' => 'required|boolean',
            'usar_api_distancia' => 'required|boolean',
            'api_key_google' => 'nullable|string'
        ]);

        $configuracion = ConfiguracionEntrega::first();

        // Extraemos todos los datos excepto la API key para un update seguro
        $datosUpdate = $request->except('api_key_google');

        // Lógica de cifrado: Solo modificamos la API Key si el usuario escribió una nueva.
        // Si el campo viene vacío, conservamos la llave cifrada que ya está en base de datos.
        if ($request->filled('api_key_google')) {
            $datosUpdate['api_key_google'] = Crypt::encryptString($request->api_key_google);
        }

        $configuracion->update($datosUpdate);

        return redirect()->back(); // Inertia interceptará esto para recargar los props
    }

    /**
     * Almacena una nueva zona de entrega con su polígono delimitador.
     */
    public function storeZona(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'coordenadas_poligono' => 'required|array',
            'color_hex' => 'required|string|size:7',
            'costo_base' => 'required|numeric|min:0',
        ]);

        \App\Models\CatalogoZonaEntrega::create([
            'nombre' => $request->nombre,
            // Estructuramos el arreglo en formato GeoJSON estándar para nuestra BD
            'coordenadas_poligono' => [
                'type' => 'Polygon',
                'coordinates' => [$request->coordenadas_poligono]
            ],
            'color_hex' => $request->color_hex,
            'costo_base' => $request->costo_base,
            'activo' => true
        ]);

        return redirect()->back();
    }
}