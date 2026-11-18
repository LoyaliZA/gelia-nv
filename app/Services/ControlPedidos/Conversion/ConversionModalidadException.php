<?php

namespace App\Services\ControlPedidos\Conversion;

class ConversionModalidadException extends \RuntimeException
{
    public function __construct(
        public readonly string $codigo,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function enCurso(): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO,
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::MENSAJE_RESERVA,
            409,
        );
    }

    public static function enCursoAlCerrar(): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_CONVERSION_EN_CURSO,
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::MENSAJE_RESERVA,
            422,
        );
    }

    public static function contextoObsoleto(): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_CONTEXTO_OBSOLETO,
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::MENSAJE_CONTEXTO_OBSOLETO,
            409,
        );
    }

    public static function vistaAtrasada(): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_VISTA_ATRASADA,
            'La consulta cambió. Actualice para continuar.',
            409,
        );
    }

    public static function consultaYaCerrada(): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA,
            'La consulta ya se cerró. El cambio de modalidad no está disponible.',
            422,
        );
    }

    public static function versionObsoleta(): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_VERSION_OBSOLETA,
            'El pedido cambió. Actualice el seguimiento antes de continuar.',
            409,
        );
    }

    public static function destinoInactivo(?string $motivo = null): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_DESTINO_INACTIVO,
            $motivo ?: 'El destino seleccionado no está habilitado.',
            422,
        );
    }

    public static function noElegible(?string $motivo = null): self
    {
        return new self(
            \App\Support\ControlPedidos\Conversion\CodigosConversionModalidad::CODIGO_NO_ELEGIBLE,
            $motivo ?: 'Este pedido no admite el cambio de modalidad.',
            422,
        );
    }

    public function render($request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $this->getMessage(),
                'codigo' => $this->codigo,
            ], $this->status);
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
