<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteConsignatario;
use App\Models\ClienteDocumento;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\Clientes\Expediente\GestionarExpedienteClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpedienteClienteController extends Controller
{
    public function pagina(Request $request, Cliente $cliente): Response
    {
        abort_unless($request->user()?->can('clientes.expediente.ver'), 403);

        return Inertia::render('Admin/ExpedienteCliente', [
            'cliente' => ['id' => $cliente->id, 'nombre' => $cliente->nombre, 'numero_cliente' => $cliente->numero_cliente],
        ]);
    }

    public function index(Request $request, Cliente $cliente, GestionarExpedienteClienteService $expediente): JsonResponse
    {
        $pedido = $this->pedido($request);

        return response()->json([
            'permisos' => collect(['editar', 'documentos', 'ver_identificacion', 'eliminar', 'ver_historico'])->mapWithKeys(fn ($permiso) => [$permiso => $request->user()->can('clientes.expediente.'.$permiso)])->all(),
            'consignatarios' => $expediente->listar($cliente, $request->user(), $request->boolean('historico'), $pedido),
        ]);
    }

    public function store(Request $request, Cliente $cliente, GestionarExpedienteClienteService $expediente): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:40'],
        ]);
        $creado = $expediente->crear($cliente, $request->user(), $datos['nombre'], $datos['telefono'], $this->pedido($request));

        return response()->json(['id' => $creado->id, 'version' => [
            'id' => $creado->versionVigente->id,
            'nombre' => $creado->versionVigente->nombre,
            'telefono' => $creado->versionVigente->telefono,
        ]], 201);
    }

    public function update(Request $request, Cliente $cliente, ClienteConsignatario $consignatario, GestionarExpedienteClienteService $expediente): JsonResponse
    {
        abort_unless((int) $consignatario->cliente_id === (int) $cliente->id, 404);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:40'],
        ]);
        $version = $expediente->editarDatos($consignatario, $request->user(), $datos['nombre'], $datos['telefono'], $this->pedido($request));

        return response()->json(['version_id' => $version->id, 'nombre' => $version->nombre, 'telefono' => $version->telefono]);
    }

    public function documento(Request $request, Cliente $cliente, ClienteConsignatario $consignatario, GestionarExpedienteClienteService $expediente): JsonResponse
    {
        abort_unless((int) $consignatario->cliente_id === (int) $cliente->id, 404);
        $request->validate(['archivo' => ['required', 'file', 'max:10240']]);
        $documento = $expediente->guardarDocumento($consignatario, $request->user(), $request->file('archivo'), $this->pedido($request));

        return response()->json(['id' => $documento->id, 'version' => $documento->version, 'nombre_original' => $documento->nombre_original], 201);
    }

    public function descargar(Request $request, Cliente $cliente, ClienteDocumento $documento, GestionarExpedienteClienteService $expediente): StreamedResponse
    {
        $documento->loadMissing('consignatario');
        abort_unless((int) $documento->consignatario?->cliente_id === (int) $cliente->id, 404);
        $pedido = $this->pedido($request);
        abort_unless($expediente->puedeDescargar($request->user(), $documento, $pedido), 403);

        return Storage::disk('local')->download($expediente->rutaPrivada($documento), $documento->nombre_original);
    }

    public function destroyConsignatario(Request $request, Cliente $cliente, ClienteConsignatario $consignatario, GestionarExpedienteClienteService $expediente): JsonResponse
    {
        abort_unless((int) $consignatario->cliente_id === (int) $cliente->id, 404);
        $expediente->eliminarConsignatario($consignatario, $request->user(), $this->pedido($request));

        return response()->json(['ok' => true]);
    }

    public function destroyDocumento(Request $request, Cliente $cliente, ClienteDocumento $documento, GestionarExpedienteClienteService $expediente): JsonResponse
    {
        $documento->loadMissing('consignatario');
        abort_unless((int) $documento->consignatario?->cliente_id === (int) $cliente->id, 404);
        $expediente->eliminarDocumento($documento, $request->user(), $this->pedido($request));

        return response()->json(['ok' => true]);
    }

    private function pedido(Request $request): ?PedidoBma
    {
        $request->validate(['pedido_id' => ['nullable', 'integer', 'min:1']]);
        $id = $request->input('pedido_id');

        return $id !== null ? PedidoBma::query()->findOrFail($id) : null;
    }
}
