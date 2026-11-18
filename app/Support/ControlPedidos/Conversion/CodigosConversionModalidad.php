<?php

namespace App\Support\ControlPedidos\Conversion;

/**
 * Claves congeladas de M-02. No renombrar. Oleada 0.
 */
final class CodigosConversionModalidad
{
    public const ORIGEN_TIENDA = 'tienda';

    public const DESTINO_TIENDA = 'tienda';

    public const DESTINO_MUNICIPAL = 'municipal';

    public const DESTINO_NACIONAL = 'nacional';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_BORRADOR = 'borrador';

    public const PROCEDENCIA_NUEVA = 'nueva';

    public const PROCEDENCIA_HEREDADA_PARCIAL = 'heredada_parcial';

    public const PROCEDENCIA_HEREDADA_COMPLETA = 'heredada_completa';

    public const CONSULTA_ORIGEN_TIENDA = 'tienda';

    public const CONSULTA_ORIGEN_CEDIS = 'cedis';

    public const CONSULTA_ORIGEN_NINGUNA = 'ninguna';

    public const CASO_SIN_CONSULTA = 'sin_consulta';

    public const CASO_PARCIAL = 'parcial';

    public const CASO_RESPONDIDA_ABIERTA = 'respondida_abierta';

    public const ACCION_UI_CONTINUAR = 'continuar';

    public const ACCION_RESERVA_ADQUIRIDA = 'conversion_reserva_adquirida';

    public const ACCION_RESERVA_LIBERADA = 'conversion_reserva_liberada';

    public const ACCION_CONFIRMADA = 'conversion_modalidad';

    public const CODIGO_CONVERSION_EN_CURSO = 'conversion_en_curso';

    public const CODIGO_CONTEXTO_OBSOLETO = 'contexto_obsoleto';

    public const CODIGO_VISTA_ATRASADA = 'vista_atrasada';

    public const CODIGO_CONSULTA_YA_CERRADA = 'consulta_ya_cerrada';

    public const CODIGO_VERSION_OBSOLETA = 'conversion_version_obsoleta';

    public const CODIGO_DESTINO_INACTIVO = 'destino_inactivo';

    public const CODIGO_NO_ELEGIBLE = 'no_elegible';

    public const RELACION_TIPO_EVIDENCIA = 'conversion_peticion_cliente';

    public const CONTEXTO_PUBLICACION = 'conversion';

    public const HISTORIAL_ACCION = 'cambio_modalidad_conversion';

    public const MENSAJE_RESERVA = 'Hay un cambio de modalidad en curso';

    public const MENSAJE_CONTEXTO_OBSOLETO = 'La consulta ya no corresponde a esta modalidad. Actualice para continuar en envío municipal.';

    public const ETIQUETA_HISTORIAL = 'Cambio a envío municipal';

    public const ETIQUETA_ACCION_UI = 'Cambiar modalidad';

    public const ETIQUETA_DESTINO_MUNICIPAL = 'Envío municipal';

    public const ETIQUETA_DESTINO_TIENDA = 'Tienda';

    public const ETIQUETA_DESTINO_NACIONAL = 'Envío nacional';

    public const FUENTE_T03 = 't03';

    public const ESTADO_T03_PENDIENTE = 'pendiente_conexion';

    public const FISICA_SIN_MOVIMIENTO = 'sin_movimiento';

    public const FISICA_INSTRUCCION_ABIERTA = 'instruccion_abierta';

    public const FISICA_CUSTODIA_EXCEPCION = 'custodia_excepcion';
}
