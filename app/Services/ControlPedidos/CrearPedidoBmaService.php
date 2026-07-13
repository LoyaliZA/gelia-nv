<?php

namespace App\Services\ControlPedidos;

use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CrearPedidoBmaService
{
    use ResuelveDatosPedidoBma;

    public function __construct(
        private GenerarFolioPedidoBmaService $folioService,
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(array $datos, int $vendedorId): PedidoBma
    {
        return DB::transaction(function () use ($datos, $vendedorId) {
            $estatusBorrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)
                ?? CatalogoEstatusPedido::porCodigo('BORRADOR');

            if (!$estatusBorrador) {
                throw new \RuntimeException('No se encontró el estatus BORRADOR en catálogo.');
            }

            $clienteId = $this->resolverClienteId($datos);

            $pedido = PedidoBma::create(array_merge(
                $this->atributosPedidoBase($datos),
                [
                    'folio' => $this->folioService->ejecutar(),
                    'vendedor_id' => $vendedorId,
                    'cliente_id' => $clienteId,
                    'catalogo_estatus_pedido_id' => $estatusBorrador->id,
                ]
            ));

            $this->guardarDocumentos($pedido, $datos['comprobantes'] ?? []);

            $this->historialService->registrarCreacion($pedido->id, $vendedorId, $estatusBorrador->id);

            return $pedido->load(['cliente', 'estatus', 'envioTienda', 'documentos', 'almacen', 'banco']);
        });
    }

    private function resolverClienteId(array $datos): ?int
    {
        if (!empty($datos['cliente_id'])) {
            return (int) $datos['cliente_id'];
        }

        if (!empty($datos['numero_cliente'])) {
            $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
            if ($cliente) {
                return $cliente->id;
            }
        }

        return null;
    }

    private function guardarDocumentos(PedidoBma $pedido, array $archivos): void
    {
        $orden = 0;
        foreach ($archivos as $archivo) {
            if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
                continue;
            }

            $ruta = $archivo->store("pedidos_bma/comprobantes/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => $orden++,
            ]);
        }
    }
}
