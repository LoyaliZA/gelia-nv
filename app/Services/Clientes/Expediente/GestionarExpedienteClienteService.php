<?php

namespace App\Services\Clientes\Expediente;

use App\Models\Cliente;
use App\Models\ClienteConsignatario;
use App\Models\ClienteConsignatarioVersion;
use App\Models\ClienteDocumento;
use App\Models\ClienteExpedienteEvento;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;

class GestionarExpedienteClienteService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function listar(Cliente $cliente, User $usuario, bool $historico, ?PedidoBma $pedido = null): array
    {
        $this->autorizar($usuario, 'clientes.expediente.ver', $cliente, $pedido);
        $verHistorico = $historico && $usuario->can('clientes.expediente.ver_historico');
        $filas = ClienteConsignatario::query()
            ->where('cliente_id', $cliente->id)
            ->when($verHistorico, fn ($query) => $query->withTrashed())
            ->with(['versionVigente', 'documentoVigente'])
            ->orderBy('id')
            ->get();

        return $filas->map(function (ClienteConsignatario $fila) use ($verHistorico) {
            $vigente = $fila->versionVigente;
            $documento = $fila->documentoVigente;

            return [
                'id' => $fila->id,
                'activo' => $fila->activo && $fila->deleted_at === null,
                'version' => $vigente ? [
                    'id' => $vigente->id,
                    'nombre' => $vigente->nombre,
                    'telefono' => $vigente->telefono,
                    'version' => $vigente->version,
                ] : null,
                'documento' => $documento ? $this->resumenDocumento($documento) : null,
                'documentos_historicos' => $verHistorico ? $fila->documentos()->withTrashed()->orderByDesc('version')->get()->map(fn ($doc) => $this->resumenDocumento($doc))->all() : [],
                'versiones' => $verHistorico ? $fila->versiones()->orderBy('version')->get(['id', 'nombre', 'telefono', 'version', 'vigente']) : [],
            ];
        })->values()->all();
    }

    public function crear(Cliente $cliente, User $usuario, string $nombre, string $telefono, ?PedidoBma $pedido = null): ClienteConsignatario
    {
        $this->autorizar($usuario, 'clientes.expediente.editar', $cliente, $pedido);
        $nombre = trim($nombre);
        $telefono = trim($telefono);
        $this->validarDatos($nombre, $telefono);

        return DB::transaction(function () use ($cliente, $usuario, $nombre, $telefono) {
            $consignatario = ClienteConsignatario::query()->create([
                'cliente_id' => $cliente->id,
                'activo' => true,
            ]);
            $consignatario->versiones()->create([
                'nombre' => $nombre,
                'telefono' => $telefono,
                'vigente' => true,
                'version' => 1,
                'creado_por_id' => $usuario->id,
            ]);
            $this->evento($cliente->id, $consignatario->id, null, 'alta', $usuario->id, ['nombre' => $nombre]);

            return $consignatario->load('versionVigente');
        });
    }

    public function editarDatos(ClienteConsignatario $consignatario, User $usuario, string $nombre, string $telefono, ?PedidoBma $pedido = null): ClienteConsignatarioVersion
    {
        $consignatario->loadMissing('cliente');
        $this->autorizar($usuario, 'clientes.expediente.editar', $consignatario->cliente, $pedido);
        $nombre = trim($nombre);
        $telefono = trim($telefono);
        $this->validarDatos($nombre, $telefono);

        return DB::transaction(function () use ($consignatario, $usuario, $nombre, $telefono) {
            $consignatario = ClienteConsignatario::query()->lockForUpdate()->findOrFail($consignatario->id);
            $anterior = $consignatario->versionVigente()->lockForUpdate()->first();
            if (! $anterior) {
                throw ValidationException::withMessages(['consignatario' => 'El consignatario no tiene datos vigentes.']);
            }
            if ($anterior->nombre === $nombre && $anterior->telefono === $telefono) {
                return $anterior;
            }
            $anterior->update(['vigente' => false]);
            $nueva = $consignatario->versiones()->create([
                'nombre' => $nombre,
                'telefono' => $telefono,
                'vigente' => true,
                'version' => $anterior->version + 1,
                'anterior_id' => $anterior->id,
                'creado_por_id' => $usuario->id,
            ]);
            $this->evento($consignatario->cliente_id, $consignatario->id, null, 'cambio', $usuario->id, [
                'version_id' => $nueva->id,
                'anterior_id' => $anterior->id,
            ]);

            return $nueva;
        });
    }

    public function guardarDocumento(ClienteConsignatario $consignatario, User $usuario, UploadedFile $archivo, ?PedidoBma $pedido = null): ClienteDocumento
    {
        $consignatario->loadMissing('cliente');
        $this->autorizar($usuario, 'clientes.expediente.documentos', $cliente = $consignatario->cliente, $pedido);
        $this->validarArchivo($archivo);

        return DB::transaction(function () use ($consignatario, $usuario, $archivo, $cliente) {
            $consignatario = ClienteConsignatario::query()->lockForUpdate()->findOrFail($consignatario->id);
            $anterior = $consignatario->documentos()->withTrashed()->orderByDesc('version')->first();
            $version = ($anterior?->version ?? 0) + 1;
            $hash = hash_file('sha256', $archivo->getRealPath());
            try {
                $ruta = $archivo->store("clientes/expediente/{$cliente->id}/{$consignatario->id}", 'local');
            } catch (FilesystemException $error) {
                report($error);
                throw ValidationException::withMessages(['archivo' => 'No se pudo guardar la identificación. Los datos del consignatario se conservan; vuelva a intentarlo.']);
            }
            if (!is_string($ruta) || $ruta === '') {
                throw ValidationException::withMessages(['archivo' => 'No se pudo guardar la identificación. Los datos del consignatario se conservan; vuelva a intentarlo.']);
            }
            if ($anterior) {
                $anterior->update(['vigente' => false]);
            }
            $documento = $consignatario->documentos()->create([
                'tipo' => ClienteDocumento::TIPO_IDENTIFICACION,
                'ruta' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => (string) $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => $hash,
                'vigente' => true,
                'version' => $version,
                'anterior_id' => $anterior?->id,
                'creado_por_id' => $usuario->id,
            ]);
            $this->evento($cliente->id, $consignatario->id, $documento->id, $anterior ? 'sustitucion' : 'alta_documento', $usuario->id, [
                'version' => $version,
                'anterior_id' => $anterior?->id,
            ]);

            return $documento;
        });
    }

    public function eliminarConsignatario(ClienteConsignatario $consignatario, User $usuario, ?PedidoBma $pedido = null): void
    {
        $consignatario->loadMissing('cliente');
        $this->autorizar($usuario, 'clientes.expediente.eliminar', $consignatario->cliente, $pedido);
        DB::transaction(function () use ($consignatario, $usuario) {
            $consignatario = ClienteConsignatario::query()->lockForUpdate()->findOrFail($consignatario->id);
            $consignatario->update(['activo' => false]);
            $consignatario->delete();
            $this->evento($consignatario->cliente_id, $consignatario->id, null, 'baja', $usuario->id, []);
        });
    }

    public function eliminarDocumento(ClienteDocumento $documento, User $usuario, ?PedidoBma $pedido = null): void
    {
        $documento->loadMissing('consignatario.cliente');
        $cliente = $documento->consignatario->cliente;
        $this->autorizar($usuario, 'clientes.expediente.eliminar', $cliente, $pedido);
        DB::transaction(function () use ($documento, $usuario, $cliente) {
            ClienteConsignatario::query()->lockForUpdate()->findOrFail($documento->consignatario_id);
            $documento->refresh();
            $documento->update(['vigente' => false]);
            $documento->delete();
            $this->evento($cliente->id, $documento->consignatario_id, $documento->id, 'baja_documento', $usuario->id, []);
        });
    }

    public function puedeDescargar(User $usuario, ClienteDocumento $documento, ?PedidoBma $pedido = null): bool
    {
        $documento->loadMissing('consignatario.cliente');
        $cliente = $documento->consignatario?->cliente;
        if (! $cliente || ! $usuario->can('clientes.expediente.ver_identificacion')) {
            return false;
        }
        if (! $this->alcance($usuario, $cliente, $pedido)) {
            return false;
        }
        if ($documento->vigente && $documento->deleted_at === null && $documento->consignatario->activo && ! $documento->consignatario->trashed()) {
            return true;
        }
        if ($usuario->can('clientes.expediente.ver_historico')) {
            return true;
        }

        return $pedido && (int) data_get($pedido->solicitud_preparacion_json, 'expediente.documento_id') === (int) $documento->id
            && VisibilidadPedidoBma::puedeConsultar($usuario, $pedido);
    }

    public function rutaPrivada(ClienteDocumento $documento): string
    {
        return $documento->ruta;
    }

    public function autorizar(User $usuario, string $permiso, Cliente $cliente, ?PedidoBma $pedido = null): void
    {
        if (! $usuario->can($permiso) || ! $this->alcance($usuario, $cliente, $pedido)) {
            throw ValidationException::withMessages(['expediente' => 'No tiene permiso para esta operación del expediente.']);
        }
    }

    private function alcance(User $usuario, Cliente $cliente, ?PedidoBma $pedido): bool
    {
        if ($pedido) {
            if ((int) $pedido->cliente_id !== (int) $cliente->id) {
                return false;
            }

            return VisibilidadPedidoBma::puedeConsultar($usuario, $pedido)
                || VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido);
        }

        if ($usuario->can('clientes.ver')) {
            return true;
        }

        return (int) $cliente->vendedor_id === (int) $usuario->id;
    }

    private function validarDatos(string $nombre, string $telefono): void
    {
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['nombre' => 'Indique el nombre del consignatario.']);
        }
        if ($telefono === '' || mb_strlen($telefono) > 40) {
            throw ValidationException::withMessages(['telefono' => 'Indique un teléfono válido.']);
        }
    }

    private function validarArchivo(UploadedFile $archivo): void
    {
        if (! $archivo->isValid() || $archivo->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['archivo' => 'El archivo debe ser válido y de hasta 10 MB.']);
        }
        $mime = (string) $archivo->getMimeType();
        if (! in_array($mime, self::MIMES, true)) {
            throw ValidationException::withMessages(['archivo' => 'Solo se acepta imagen o PDF.']);
        }
    }

    /** @return array<string, mixed> */
    private function resumenDocumento(ClienteDocumento $documento): array
    {
        return [
            'id' => $documento->id,
            'tipo' => $documento->tipo,
            'nombre_original' => $documento->nombre_original,
            'version' => $documento->version,
            'vigente' => $documento->vigente,
        ];
    }

    /** @param array<string, mixed> $detalle */
    private function evento(int $clienteId, ?int $consignatarioId, ?int $documentoId, string $accion, int $usuarioId, array $detalle): void
    {
        ClienteExpedienteEvento::query()->create([
            'cliente_id' => $clienteId,
            'consignatario_id' => $consignatarioId,
            'documento_id' => $documentoId,
            'accion' => $accion,
            'usuario_id' => $usuarioId,
            'detalle' => $detalle,
        ]);
    }
}
