<?php

namespace App\Support\ControlPedidos;

class CamposIncorrectosPedidoBma
{
    public const ALLOWLIST = [
        'domicilio',
        'destinatario',
        'telefono',
        'paqueteria',
        'tipo_guia',
        'referencia',
        'codigo_postal',
        'ciudad_estado',
        'numero_rastreo',
        'guia_pdf',
    ];

    /** Campos de envío / guía que invalidan la guía capturada. */
    public const INVALIDAN_GUIA = [
        'domicilio',
        'destinatario',
        'telefono',
        'paqueteria',
        'tipo_guia',
        'referencia',
        'codigo_postal',
        'ciudad_estado',
        'numero_rastreo',
        'guia_pdf',
    ];

    public const ETIQUETAS = [
        'domicilio' => 'Domicilio / dirección',
        'destinatario' => 'Destinatario',
        'telefono' => 'Teléfono',
        'paqueteria' => 'Paquetería',
        'tipo_guia' => 'Tipo de guía',
        'referencia' => 'Referencias',
        'codigo_postal' => 'Código postal',
        'ciudad_estado' => 'Ciudad / estado',
        'numero_rastreo' => 'Número de guía',
        'guia_pdf' => 'PDF de guía',
    ];

    public static function filtrar(array $campos): array
    {
        return array_values(array_unique(array_intersect($campos, self::ALLOWLIST)));
    }

    public static function invalidanGuia(array $campos): bool
    {
        return count(array_intersect($campos, self::INVALIDAN_GUIA)) > 0;
    }
}
