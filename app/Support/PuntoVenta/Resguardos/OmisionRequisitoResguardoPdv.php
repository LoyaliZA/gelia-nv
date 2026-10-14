<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class OmisionRequisitoResguardoPdv
{
    public const PIEZAS = 'piezas';

    public const TICKET = 'ticket';

    public const FOTO_PAQUETE = 'foto_paquete';

    /** @var list<string> */
    public const CAMPOS = [
        self::PIEZAS,
        self::TICKET,
        self::FOTO_PAQUETE,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function reglas(): array
    {
        return [
            'omisiones' => ['sometimes', 'array', 'max:3'],
            'omisiones.*.campo' => ['required', 'string', 'distinct', Rule::in(self::CAMPOS)],
            'omisiones.*.motivo' => ['required', 'string', 'min:8', 'max:500'],
        ];
    }

    /**
     * @param  list<array{campo?: string, motivo?: string}>  $omisiones
     * @param  list<string>  $faltantes
     * @return list<array{campo: string, motivo: string, actor_id: int}>
     */
    public static function resolver(array $omisiones, array $faltantes, int $actorId, string $paso): array
    {
        $normalizadas = [];
        $cubiertos = [];

        foreach (array_values($omisiones) as $indice => $omision) {
            if (! is_array($omision)) {
                throw ValidationException::withMessages([
                    "omisiones.{$indice}" => 'La omisión no es válida.',
                ]);
            }

            $campo = (string) ($omision['campo'] ?? '');
            $motivo = trim((string) ($omision['motivo'] ?? ''));

            if (! in_array($campo, self::CAMPOS, true)) {
                throw ValidationException::withMessages([
                    "omisiones.{$indice}.campo" => 'El dato a omitir no es válido.',
                ]);
            }

            if (mb_strlen($motivo) < 8) {
                throw ValidationException::withMessages([
                    "omisiones.{$indice}.motivo" => 'Indique el motivo de la omisión.',
                ]);
            }

            if (! in_array($campo, $faltantes, true)) {
                throw ValidationException::withMessages([
                    "omisiones.{$indice}.campo" => 'Ese dato ya está registrado y no se puede omitir.',
                ]);
            }

            if (isset($cubiertos[$campo])) {
                throw ValidationException::withMessages([
                    "omisiones.{$indice}.campo" => 'Ese dato ya fue omitido en esta confirmación.',
                ]);
            }

            $cubiertos[$campo] = true;
            $normalizadas[] = [
                'campo' => $campo,
                'motivo' => $motivo,
                'actor_id' => $actorId,
            ];
        }

        $mensajes = [];
        foreach ($faltantes as $campo) {
            if (! isset($cubiertos[$campo])) {
                $mensajes[self::claveError($campo)] = self::mensajeFaltante($campo, $paso);
            }
        }

        if ($mensajes !== []) {
            throw ValidationException::withMessages($mensajes);
        }

        return $normalizadas;
    }

    /**
     * @return list<string>
     */
    public static function faltantes(ResguardoPdv $resguardo, bool $exigePiezas, bool $exigeEvidencia): array
    {
        if (! EvidenciaMinimaRegistroManualPdv::esManual($resguardo)) {
            return [];
        }

        $faltantes = [];

        if ($exigePiezas && ! self::tienePiezas($resguardo)) {
            $faltantes[] = self::PIEZAS;
        }

        if ($exigeEvidencia) {
            $resguardo->unsetRelation('evidencias');
            $usos = EvidenciaMinimaRegistroManualPdv::usosPresentes($resguardo);
            if (! in_array(ResguardoPdvEvidencia::USO_TICKET, $usos, true)) {
                $faltantes[] = self::TICKET;
            }
            if (! in_array(ResguardoPdvEvidencia::USO_PAQUETE, $usos, true)) {
                $faltantes[] = self::FOTO_PAQUETE;
            }
        }

        return $faltantes;
    }

    /**
     * Un archivo enviado en la misma confirmación cubre el requisito.
     *
     * @param  list<string>  $faltantes
     * @return list<string>
     */
    public static function cubrirConArchivo(array $faltantes, string $campo, ?UploadedFile $archivo): array
    {
        if (! $archivo instanceof UploadedFile) {
            return $faltantes;
        }

        return array_values(array_filter(
            $faltantes,
            static fn (string $faltante): bool => $faltante !== $campo
        ));
    }

    public static function etiqueta(string $campo): string
    {
        return match ($campo) {
            self::PIEZAS => 'Piezas omitidas',
            self::TICKET => 'Ticket omitido',
            self::FOTO_PAQUETE => 'Foto del paquete omitida',
            default => $campo,
        };
    }

    private static function tienePiezas(ResguardoPdv $resguardo): bool
    {
        $snapshot = is_array($resguardo->snapshot_json) ? $resguardo->snapshot_json : [];
        $piezas = $snapshot['piezas'] ?? [];

        return is_array($piezas) && $piezas !== [];
    }

    private static function claveError(string $campo): string
    {
        return match ($campo) {
            self::TICKET => 'archivo_ticket',
            self::FOTO_PAQUETE => 'foto_paquete',
            default => self::PIEZAS,
        };
    }

    private static function mensajeFaltante(string $campo, string $paso): string
    {
        return match ($campo) {
            self::PIEZAS => $paso === 'custodia'
                ? 'Registre las piezas del pedido antes de confirmar la custodia.'
                : 'Registre las piezas del pedido antes de confirmar la recepción.',
            self::TICKET => 'Adjunte el ticket o indique el motivo para omitirlo.',
            self::FOTO_PAQUETE => 'Adjunte la foto del paquete o indique el motivo para omitirla.',
            default => 'Complete el dato o indique el motivo para omitirlo.',
        };
    }
}
