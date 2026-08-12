<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $termino = trim($request->query('q', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json([]);
        }

        $conFiscales = filter_var($request->query('con_fiscales', false), FILTER_VALIDATE_BOOLEAN);

        $columnas = [
            'id',
            'numero_cliente',
            'nombre',
            'es_heredado',
            'es_inactivo',
            'lista_actual_id',
            'monto_venta_actual',
        ];

        if ($conFiscales) {
            $columnas = array_merge($columnas, [
                'nombre_razon_social',
                'rfc',
                'codigo_postal',
                'regimen_fiscal',
                'correo_electronico',
                'uso_factura',
                'telefono',
            ]);
        }

        $query = Cliente::query()
            ->select($columnas)
            ->with('listaDescuento:id,nombre');

        $this->aplicarBusqueda($query, $termino);

        $clientes = $query->limit(50)->get()->map(function ($cliente) use ($conFiscales) {
            $fila = [
                'id' => $cliente->id,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre,
                'es_heredado' => (bool) $cliente->es_heredado,
                'es_inactivo' => (bool) $cliente->es_inactivo,
                'lista_actual_id' => $cliente->lista_actual_id,
                'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista',
                'monto_venta_actual' => (float) $cliente->monto_venta_actual,
            ];

            if ($conFiscales) {
                $fila['nombre_razon_social'] = $cliente->nombre_razon_social;
                $fila['rfc'] = $cliente->rfc;
                $fila['codigo_postal'] = $cliente->codigo_postal;
                $fila['regimen_fiscal'] = $cliente->regimen_fiscal;
                $fila['correo_electronico'] = $cliente->correo_electronico;
                $fila['uso_factura'] = $cliente->uso_factura;
                $fila['telefono'] = $cliente->telefono;
            }

            return $fila;
        });

        return response()
            ->json($clientes)
            ->header('Cache-Control', 'private, max-age=60');
    }

    public function show($numero): JsonResponse
    {
        $cliente = Cliente::query()
            ->select([
                'id',
                'numero_cliente',
                'nombre',
                'es_heredado',
                'es_inactivo',
                'lista_actual_id',
                'monto_venta_actual',
            ])
            ->with('listaDescuento:id,nombre')
            ->where('numero_cliente', $numero)
            ->first();

        if (!$cliente) {
            return response()->json(['encontrado' => false], 404);
        }

        return response()->json([
            'encontrado' => true,
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'es_heredado' => (bool) $cliente->es_heredado,
            'es_inactivo' => (bool) $cliente->es_inactivo,
            'lista_actual_id' => $cliente->lista_actual_id,
            'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista',
            'monto_venta_actual' => (float) $cliente->monto_venta_actual,
        ]);
    }

    public function direccionEnvio(int $id): JsonResponse
    {
        $cliente = Cliente::query()
            ->select([
                'id',
                'numero_cliente',
                'nombre',
                'direccion_contacto',
                'colonia_contacto',
                'municipio_contacto',
                'estado_contacto',
                'pais_contacto',
                'cp_contacto',
                'codigo_postal',
            ])
            ->find($id);

        if (!$cliente) {
            return response()->json(['encontrado' => false], 404);
        }

        $direcciones = app(GestionDireccionesClienteService::class)
            ->listarActivasVerificadasPorCliente((int) $cliente->id)
            ->values()
            ->all();

        $tienePrincipal = collect($direcciones)->contains(fn (array $d) => ! empty($d['es_principal']));

        // Solo catálogo cliente_direcciones (nunca campos legado de contacto).
        return response()->json([
            'encontrado' => true,
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'direcciones_normalizadas' => true,
            'direcciones' => $direcciones,
            'tiene_direccion' => count($direcciones) > 0,
            'tiene_direccion_principal' => $tienePrincipal,
            'domicilio_entrega' => null,
            'codigo_postal' => null,
        ]);
    }

    private function aplicarBusqueda($query, string $termino): void
    {
        $query->where(function ($sub) use ($termino) {
            if (preg_match('/^\d/', $termino)) {
                $sub->where('numero_cliente', 'like', "{$termino}%");
            }

            $sub->orWhere('nombre', 'like', "{$termino}%");

            if (mb_strlen($termino) >= 3) {
                $sub->orWhere('nombre', 'like', "%{$termino}%");
            }
        });
    }
}
