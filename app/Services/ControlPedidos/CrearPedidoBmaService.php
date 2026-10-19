<?php

namespace App\Services\ControlPedidos;

use App\Models\Cliente;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\Direcciones\CrearSnapshotDireccionPedido;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class CrearPedidoBmaService
{
    use ResuelveDatosPedidoBma;

    public function __construct(
        private GenerarFolioPedidoBmaService $folioService,
        private RegistrarHistorialPedidoService $historialService,
        private SincronizarAplicacionesPedidoSafService $safPedido,
        private CrearSnapshotDireccionPedido $crearSnapshot,
        private AsignarSucursalDestinoPedidoService $asignarDestino,
        private ValidarAlmacenSalidaPedido $validarAlmacenSalida,
        private PersistirIntencionPreparacionPedido $intencionPreparacion,
    ) {}

    public function ejecutar(array $datos, int $vendedorId): PedidoBma
    {
        $clave = !empty($datos['captura_clave']) ? hash('sha256', $vendedorId.':'.$datos['captura_clave']) : null;
        $hash = \App\Support\ControlPedidos\HuellaSolicitudPedido::de($datos);
        $replay = function () use ($clave, $hash, $vendedorId) {
            $p = PedidoBma::where('captura_creacion_clave', $clave)->first();
            if (!$p || (int) $p->vendedor_id !== $vendedorId || !hash_equals((string) $p->captura_creacion_hash, $hash) || !hash_equals((string) $p->captura_creacion_base, \App\Support\ControlPedidos\RevisionCapturaPedido::base($p))) throw new \App\Exceptions\ControlPedidos\ConflictoCapturaPedido('La clave de creación ya corresponde a otra captura.');
            return $p;
        };
        try {
            return DB::transaction(function () use ($datos, $vendedorId, $clave, $hash, $replay) {
                if ($clave && PedidoBma::where('captura_creacion_clave', $clave)->exists()) return $replay();
                $pedido = $this->crear($datos, $vendedorId)->fresh();
                if ($clave) $pedido->forceFill(['captura_creacion_clave' => $clave, 'captura_creacion_hash' => $hash, 'captura_creacion_base' => \App\Support\ControlPedidos\RevisionCapturaPedido::base($pedido)])->save();
                return $pedido->fresh();
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            if (!$clave) throw $e;
            return $replay();
        }
    }

    private function crear(array $datos, int $vendedorId): PedidoBma
    {
        return DB::transaction(function () use ($datos, $vendedorId) {
            $estatusBorrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR)
                ?? CatalogoEstatusPedido::porCodigo('BORRADOR');

            if (!$estatusBorrador) {
                throw new \RuntimeException('No se encontró el estatus BORRADOR en catálogo.');
            }

            $principal = $this->resolverPrincipalSiComplementario($datos);
            if ($principal) {
                $datos['pedido_principal_id'] = $principal->id;
                $datos['cliente_id'] = $principal->cliente_id;
                $datos['es_resguardo'] = true;
                $datos['modo_resguardo'] = 'complementario';
            }

            $attrs = $this->atributosPedidoBase($datos);
            $this->validarAlmacenSalida->ejecutar(
                $attrs['almacen_id'] ?? null,
                $principal?->almacen_id
            );
            if (\Illuminate\Support\Facades\Schema::hasColumn('pedidos_bma', 'departamento_operacion_id')) {
                $vendedorContexto = User::findOrFail($vendedorId);
                $attrs['departamento_operacion_id'] = $principal?->departamento_operacion_id
                    ?? \App\Support\ControlPedidos\ContextoDepartamentoPedido::validar($vendedorContexto, !empty($datos['departamento_operacion_id']) ? (int) $datos['departamento_operacion_id'] : null);
            }
            $folio = $principal
                ? $this->folioService->ejecutarComplemento($principal)
                : $this->folioService->ejecutar();

            $pedido = PedidoBma::create(array_merge(
                $attrs,
                [
                    'folio' => $folio,
                    'vendedor_id' => $vendedorId,
                    'cliente_id' => $principal?->cliente_id ?? $this->resolverClienteId($datos),
                    'catalogo_estatus_pedido_id' => $estatusBorrador->id,
                ]
            ));

            $this->guardarDocumentos($pedido, $datos['comprobantes'] ?? []);

            if (array_key_exists('saf_aplicaciones', $datos)) {
                $this->safPedido->reservarParaPedido($pedido, $datos['saf_aplicaciones'] ?? [], $vendedorId);
            }

            if (filter_var($datos['direccion_manual_excepcion'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && is_array($datos['direccion'] ?? null)) {
                $this->crearSnapshot->ejecutarManual(
                    $pedido,
                    $datos['direccion'],
                    $vendedorId,
                    $datos['motivo_direccion_manual'] ?? null
                );
            }

            $this->historialService->registrarCreacion($pedido->id, $vendedorId, $estatusBorrador->id);
            app(ResguardosEnvioPedido::class)->aplicar($pedido, $datos, $vendedorId);
            $this->intencionPreparacion->ejecutar($pedido, $datos);

            $destExplicito = array_key_exists('sucursal_destino_id', $datos);
            $dest = $destExplicito
                ? ($datos['sucursal_destino_id'] !== null && $datos['sucursal_destino_id'] !== ''
                    ? (int) $datos['sucursal_destino_id']
                    : null)
                : ($principal?->sucursal_destino_id !== null ? (int) $principal->sucursal_destino_id : null);

            if ($destExplicito || $dest !== null) {
                $vendedor = User::query()->find($vendedorId);
                if ($vendedor) {
                    $this->asignarDestino->ejecutar($pedido, $vendedor, $dest);
                }
            }

            if ($principal) {
                $this->historialService->ejecutar(
                    $pedido->id,
                    $vendedorId,
                    $estatusBorrador->id,
                    $estatusBorrador->id,
                    "Complemento de {$principal->folio}.",
                    AccionesHistorialPedidoBma::COMPLEMENTO
                );
            }

            return $pedido->load(['cliente', 'estatus', 'envioTienda', 'documentos', 'almacen', 'banco', 'principal', 'sucursalDestino']);
        });
    }

    private function resolverPrincipalSiComplementario(array $datos): ?PedidoBma
    {
        $esResguardo = filter_var($datos['es_resguardo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $modo = strtolower(trim((string) ($datos['modo_resguardo'] ?? '')));
        $tipoId = $datos['tipo_operacion_envio_id'] ?? null;
        $esComplementario = $modo === 'complementario'
            || ($tipoId && CatalogoTipoOperacionEnvio::find((int) $tipoId)?->esResguardoComplementario());

        if (!$esResguardo || !$esComplementario) {
            return null;
        }

        $principalId = (int) ($datos['pedido_principal_id'] ?? 0);
        if ($principalId < 1) {
            throw new \InvalidArgumentException('Seleccione el pedido principal a complementar.');
        }

        $principal = PedidoBma::with(['estatus', 'tipoOperacionEnvio'])->find($principalId);
        if (!$principal) {
            throw new \InvalidArgumentException('El pedido principal no existe.');
        }

        $this->validarPrincipalParaComplemento($principal, $this->resolverClienteId($datos));

        return $principal;
    }

    /** Reglas de vínculo: padre no es complemento; mismo cliente. Resguardo abierto pendiente de liberación sí es válido. */
    public function validarPrincipalParaComplemento(PedidoBma $principal, ?int $clienteId): void
    {
        if ($principal->esComplemento()) {
            throw new \InvalidArgumentException('No se puede complementar un pedido que ya es complemento.');
        }

        if ($clienteId && (int) $principal->cliente_id !== $clienteId) {
            throw new \InvalidArgumentException('El pedido principal debe ser del mismo cliente.');
        }
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
