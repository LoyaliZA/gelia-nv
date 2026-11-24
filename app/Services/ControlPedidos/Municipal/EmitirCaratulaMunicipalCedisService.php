<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ClienteDocumento;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\User;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\Municipal\PresentadorCaratulaMunicipalCedis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * PDF a demanda de la carátula municipal para empacar en CEDIS.
 * No guarda versión oficial ni exige sucursal destino.
 */
class EmitirCaratulaMunicipalCedisService
{
    public const VARIANTE_CON = 'con_identificacion';

    public const VARIANTE_SIN = 'sin_identificacion';

    public function __construct(private RegistrarHistorialPedidoService $historial) {}

    /** @return array{contenido: string, filename: string, variante: string} */
    public function ejecutar(PedidoBma $pedido, User $usuario, string $variante): array
    {
        $variante = trim($variante);
        if (! in_array($variante, [self::VARIANTE_CON, self::VARIANTE_SIN], true)) {
            throw ValidationException::withMessages(['variante' => 'Indique si la carátula va con o sin identificación.']);
        }
        if (! ConsultaMunicipal::es($pedido)) {
            throw ValidationException::withMessages(['pedido' => 'Este pedido no es un envío municipal.']);
        }

        $pedido->loadMissing(['estatus', 'paqueteria']);
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $expediente = is_array($json['expediente'] ?? null) ? $json['expediente'] : [];
        [$nombre, $telefono] = $this->consignatarioVigente($pedido, $expediente);
        if (! $pedido->paqueteria) {
            throw ValidationException::withMessages(['paqueteria' => 'El pedido no tiene transporte definido.']);
        }

        $identificacion = null;
        if ($variante === self::VARIANTE_CON) {
            $identificacion = $this->imagenIdentificacion($pedido, $expediente);
        }

        $snapshot = [
            'destinatario_nombre' => $nombre,
            'destinatario_telefono' => $telefono,
            'transporte' => $this->lineaTransporte((string) $pedido->paqueteria->nombre),
            'modalidad_cobro' => PresentadorCaratulaMunicipalCedis::leyenda($pedido, $json),
            'variante' => $variante,
            'identificacion_data_uri' => $identificacion,
        ];
        $orientacion = $variante === self::VARIANTE_CON ? 'landscape' : 'portrait';
        $contenido = Pdf::loadView('control_pedidos.caratula_municipal_cedis', ['caratula' => $snapshot])
            ->setPaper('letter', $orientacion)
            ->output();

        if ($pedido->estatus) {
            $etiqueta = $variante === self::VARIANTE_CON ? 'con identificación' : 'sin identificación';
            $this->historial->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->estatus->id,
                $pedido->estatus->id,
                "Descarga carátula municipal {$etiqueta}.",
                AccionesHistorialPedidoBma::DESCARGA_CARATULA,
            );
        }

        $filename = $variante === self::VARIANTE_CON
            ? 'caratula-con-identificacion.pdf'
            : 'caratula-sin-identificacion.pdf';

        return ['contenido' => $contenido, 'filename' => $filename, 'variante' => $variante];
    }

    /** @param array<string, mixed> $expediente @return array{0: string, 1: string} */
    private function consignatarioVigente(PedidoBma $pedido, array $expediente): array
    {
        $consignatario = PresentadorCaratulaMunicipalCedis::consignatario($pedido, $expediente);
        $version = $consignatario?->versionVigente;
        if (! $consignatario || ! $consignatario->activo || (int) $consignatario->cliente_id !== (int) $pedido->cliente_id || ! $version || ! $version->vigente) {
            throw ValidationException::withMessages(['consignatario_id' => 'El pedido no tiene un consignatario vigente.']);
        }

        return [(string) $version->nombre, (string) $version->telefono];
    }

    /** @param array<string, mixed> $expediente */
    private function imagenIdentificacion(PedidoBma $pedido, array $expediente): string
    {
        $consignatario = PresentadorCaratulaMunicipalCedis::consignatario($pedido, $expediente);
        $documento = $consignatario?->documentoVigente;
        if (! $documento instanceof ClienteDocumento || ! $documento->ruta || ! Storage::disk('local')->exists($documento->ruta)) {
            throw ValidationException::withMessages(['identificacion' => 'Este consignatario no tiene archivo de identificación.']);
        }

        $bytes = Storage::disk('local')->get($documento->ruta);
        $mime = strtolower((string) $documento->mime_type);
        if (str_starts_with($mime, 'image/') && is_string($bytes) && $bytes !== '') {
            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        }
        if ($mime === 'application/pdf') {
            $jpeg = $this->primeraPaginaPdf($bytes);
            if ($jpeg !== null) {
                return 'data:image/jpeg;base64,'.base64_encode($jpeg);
            }
        }

        throw ValidationException::withMessages([
            'identificacion' => 'No se pudo incluir la identificación. Use la carátula sin identificación o cargue la INE como imagen.',
        ]);
    }

    private function primeraPaginaPdf(?string $bytes): ?string
    {
        if (! is_string($bytes) || $bytes === '' || ! extension_loaded('imagick')) {
            return null;
        }

        try {
            $imagen = new \Imagick();
            $imagen->setResolution(150, 150);
            $imagen->readImageBlob($bytes);
            $imagen->setIteratorIndex(0);
            $imagen->setImageFormat('jpeg');
            $jpeg = $imagen->getImageBlob();
            $imagen->clear();
            $imagen->destroy();

            return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function lineaTransporte(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return 'TRANSPORTE';
        }
        if (str_starts_with(mb_strtoupper($nombre, 'UTF-8'), 'TRANSPORTE')) {
            return $nombre;
        }

        return 'TRANSPORTE '.$nombre;
    }
}
