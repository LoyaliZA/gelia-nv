<?php

namespace App\Support\Facturas;

use App\Models\CatalogoBanco;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class BancoFacturacion
{
    public static function configurado(): bool
    {
        return Schema::hasColumn('solicitudes_facturas', 'catalogo_banco_id')
            && Schema::hasColumn('catalogo_bancos', 'facturacion_habilitada');
    }

    /** A draft can retain a restricted bank so the seller can correct the payment. */
    public static function validar(?int $bancoId, ?int $departamentoId, bool $enviar = false, bool $requerido = false): void
    {
        if (! $bancoId) {
            if ($requerido) {
                throw ValidationException::withMessages(['catalogo_banco_id' => 'Seleccione el banco que recibió el pago.']);
            }
            return;
        }

        if (! self::configurado()) {
            throw ValidationException::withMessages(['catalogo_banco_id' => 'La configuración de bancos para Facturas está pendiente de actualización. Contacte a administración.']);
        }
        $banco = CatalogoBanco::find($bancoId);
        $disponible = $banco && $banco->activo && ($banco->compartido || ($departamentoId && $banco->departamentos()
            ->where('departamento_id', $departamentoId)->where('activo', true)->exists()));
        if (! $disponible) {
            throw ValidationException::withMessages(['catalogo_banco_id' => 'Este banco no está disponible para el departamento de la solicitud.']);
        }
        if ($enviar && ! $banco->facturacion_habilitada) {
            throw ValidationException::withMessages(['catalogo_banco_id' => 'No se puede facturar el pago recibido en '.$banco->nombre.'. '.($banco->motivo_bloqueo_facturacion ?: 'Solicite una alternativa de pago antes de enviar la solicitud.')]);
        }
    }
}
