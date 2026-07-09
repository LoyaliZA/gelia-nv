<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Support\ControlPedidos\FormatearDomicilioCliente;
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

        $query = Cliente::query()
            ->select([
                'id',
                'numero_cliente',
                'nombre',
                'nombre_razon_social',
                'rfc',
                'es_heredado',
                'es_inactivo',
                'lista_actual_id',
                'monto_venta_actual',
            ])
            ->with('listaDescuento:id,nombre');

        $this->aplicarBusqueda($query, $termino);

        $clientes = $query->limit(50)->get()->map(fn ($cliente) => [
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'nombre_razon_social' => $cliente->nombre_razon_social,
            'rfc' => $cliente->rfc,
            'es_heredado' => (bool) $cliente->es_heredado,
            'es_inactivo' => (bool) $cliente->es_inactivo,
            'lista_actual_id' => $cliente->lista_actual_id,
            'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista',
            'monto_venta_actual' => (float) $cliente->monto_venta_actual,
        ]);

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

        $domicilio = FormatearDomicilioCliente::ejecutar($cliente);
        $cp = FormatearDomicilioCliente::codigoPostal($cliente);

        return response()->json([
            'encontrado' => true,
            'id' => $cliente->id,
            'numero_cliente' => $cliente->numero_cliente,
            'nombre' => $cliente->nombre,
            'domicilio_entrega' => $domicilio,
            'codigo_postal' => $cp,
            'tiene_direccion' => filled($domicilio) || filled($cp),
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
