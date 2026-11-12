<?php

namespace App\Services\Clientes;

use App\Models\CambioListaImportacionCliente;
use App\Models\Cliente;
use App\Services\Escalonamiento\EscalonamientoAutoridad;
use App\Services\Solicitudes\EscalonamientoService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProcesarFilaClienteAction
{
    public function __construct(private ResolucionListaWizerp $resolucionLista, private MontoWizerp $montos) {}

    public function ejecutar(
        array $data, $listas, array $mapaVendedoras, Collection $clientesPorNumero,
        array &$historialBatch, bool $importaCodigoLista, int &$marcadosInactivos,
        array &$alertasLimiteExcedido, ?int $importacionClienteId = null, ?int $usuarioId = null,
        bool $persistir = true,
    ): ?array {
        $numero = trim($data['numero_cliente'] ?? '');
        if (! preg_match('/^[0-9]{1,255}$/D', $numero)) {
            $this->error('numero_cliente', 'Se requiere un número de cliente formado por dígitos.');
        }
        $cliente = $clientesPorNumero->get($numero);
        $nuevo = $cliente === null;
        $monto = $this->montos->interpretar($data['monto_venta_actual'] ?? null);
        $credito = $this->montos->interpretar($data['monto_credito_autorizado'] ?? null, 'monto_credito_autorizado');
        $codigo = trim($data['codigo_lista'] ?? '');
        $listaCsv = $codigo !== '' && $importaCodigoLista ? $this->resolucionLista->resolverListaId($listas, $codigo) : null;
        if ($importaCodigoLista && $codigo !== '' && ! $listaCsv) {
            $this->error('codigo_lista', 'Código de lista desconocido.');
        }
        // Un código vacío no es una baja ni cambia la lista. El cero tampoco inactiva.
        // Una baja legítima requiere una señal explícita del ERP, que este archivo no trae.
        $estado = null;
        if ($importaCodigoLista && $codigo !== '') {
            $estado = false;
        }
        if ($nuevo && trim($data['nombre'] ?? '') === '') {
            $this->error('nombre', 'El nombre es obligatorio para un cliente nuevo.');
        }
        if ($nuevo && $estado === null) {
            $this->error('codigo_lista', 'Un cliente nuevo requiere información de Wizerp que determine su estado comercial.');
        }
        foreach (['dias_credito', 'dias_cheque_postfechado'] as $campo) {
            if (($data[$campo] ?? '') !== '' && (! ctype_digit($data[$campo]) || bccomp($data[$campo], '2147483647', 0) > 0)) {
                $this->error($campo, 'Se requiere un entero no negativo de hasta 2147483647.');
            }
        }
        $cliente ??= new Cliente(['numero_cliente' => $numero]);
        $antes = $cliente->getAttributes();
        // El estado comercial siempre viene del ERP; monto/lista tienen autoridad independiente.
        $gobernado = app(EscalonamientoAutoridad::class)->estaActiva();
        $pg = $listas->firstWhere('nombre', 'PUBLICO GENERAL');
        if (! $pg) {
            $this->error('codigo_lista', 'Falta la lista técnica PUBLICO GENERAL.');
        }
        if ($nuevo) {
            $cliente->monto_venta_actual = '0.00';
            $cliente->lista_actual_id = $pg->id;
            $cliente->es_heredado = false;
        }
        if ($estado !== null) {
            $cliente->es_inactivo = $estado;
        }
        if ($monto !== null && ! $gobernado) {
            $cliente->monto_venta_actual = $monto;
        }
        $codigoVacio = $importaCodigoLista && $codigo === '';
        if (! $cliente->lista_bloqueada && ! $gobernado && ! $codigoVacio) {
            if ($estado === true) {
                $cliente->lista_actual_id = $pg->id;
            } elseif ($listaCsv) {
                $cliente->lista_actual_id = $listaCsv;
            }
            $base = $listas->firstWhere('id', $cliente->lista_actual_id);
            if ($estado !== true && $monto !== null && bccomp($monto, '0', 2) > 0
                && ! $this->resolucionLista->nombreExcluyeEscalonamiento($base?->nombre)) {
                $calculada = app(EscalonamientoService::class)->resolverListaPorAcumuladoNeto((float) $monto, $listas);
                if ($calculada && $base && bccomp((string) $calculada->monto_requerido, (string) $base->monto_requerido, 2) > 0) {
                    $cliente->lista_actual_id = $calculada->id;
                }
            }
        }
        foreach (['nombre', 'direccion_fiscal', 'colonia_fiscal', 'municipio_fiscal', 'estado_fiscal', 'pais_fiscal',
            'direccion_contacto', 'colonia_contacto', 'municipio_contacto', 'estado_contacto', 'pais_contacto', 'cp_contacto',
            'telefono', 'parte_relacional', 'variable_contable', 'rfc', 'codigo_postal', 'regimen_fiscal',
            'correo_electronico', 'uso_factura', 'nombre_razon_social'] as $campo) {
            if (trim($data[$campo] ?? '') !== '') {
                $cliente->$campo = trim($data[$campo]);
            }
        }
        if (($data['es_heredado'] ?? '') !== '') {
            $cliente->es_heredado = in_array(strtoupper(trim($data['es_heredado'])), ['1', 'SI', 'SÍ', 'TRUE', 'VERDADERO', 'X', 'YES'], true);
        }
        if (($data['vendedor_id'] ?? '') !== '') {
            $vendedor = $mapaVendedoras[strtoupper(trim($data['vendedor_id']))] ?? null;
            if ($vendedor) {
                $cliente->vendedor_id = $vendedor;
            }
        }
        if ($credito !== null) {
            $cliente->monto_credito_autorizado = $credito;
        }
        foreach (['dias_credito', 'dias_cheque_postfechado'] as $campo) {
            if (($data[$campo] ?? '') !== '') {
                $cliente->$campo = (int) $data[$campo];
            }
        }
        $montoAnterior = (string) ($antes['monto_venta_actual'] ?? '0');
        if (! $nuevo && bccomp($montoAnterior, (string) $cliente->monto_venta_actual, 2) !== 0) {
            $historialBatch[] = [
                'cliente_id' => $cliente->id, 'usuario_id' => $usuarioId,
                'monto_anterior' => $montoAnterior, 'monto_nuevo' => $cliente->monto_venta_actual,
                'diferencia_aplicada' => bcsub((string) $cliente->monto_venta_actual, $montoAnterior, 2),
                'origen' => RegistrarHistorialMontoClienteService::ORIGEN_CARGA_MASIVA,
                'importacion_cliente_id' => $importacionClienteId, 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        if ($persistir && $cliente->isDirty()) {
            $cliente->save();
        }
        $clientesPorNumero->put($numero, $cliente);
        if ($estado === true && ($nuevo || ! ($antes['es_inactivo'] ?? false))) {
            $marcadosInactivos++;
        }
        $limite = (float) $cliente->monto_credito_autorizado;
        $deuda = (float) ($cliente->facturaCobranzaActiva?->monto ?? 0);
        if ($limite > 0 && $deuda > $limite) {
            $alertasLimiteExcedido[] = ['cliente' => $cliente, 'monto_actual' => $deuda, 'limite' => $limite];
        }
        $listaAnterior = $listas->firstWhere('id', $antes['lista_actual_id'] ?? null);
        $listaNueva = $listas->firstWhere('id', $cliente->lista_actual_id);
        if (! $nuevo && $listaAnterior && $listaNueva && $listaAnterior->id !== $listaNueva->id) {
            $comparacion = bccomp((string) $listaNueva->monto_requerido, (string) $listaAnterior->monto_requerido, 2);

            return ['numero_cliente' => $numero, 'nombre' => $cliente->nombre,
                'lista_anterior' => $listaAnterior->nombre, 'lista_nueva' => $listaNueva->nombre,
                'tipo_cambio' => $comparacion > 0 ? CambioListaImportacionCliente::TIPO_ASCENSO : ($comparacion < 0 ? CambioListaImportacionCliente::TIPO_DESCENSO : CambioListaImportacionCliente::TIPO_CAMBIO),
                'codigo_lista' => $importaCodigoLista ? $codigo : null, 'monto_nuevo' => $cliente->monto_venta_actual];
        }

        return null;
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
