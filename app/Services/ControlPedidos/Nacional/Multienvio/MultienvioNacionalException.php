<?php

namespace App\Services\ControlPedidos\Nacional\Multienvio;

use RuntimeException;

class MultienvioNacionalException extends RuntimeException
{
    /** Campo de formulario al que se asocia el error en la respuesta 422. */
    public function __construct(string $message = '', public readonly string $campo = 'multienvio', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function revisionObsoleta(): self
    {
        return new self('La consulta cambió desde que se tomó la decisión; recarga la pantalla y vuelve a revisarla.', 'revision');
    }

    public static function noElegible(string $motivo): self
    {
        return new self('El pedido no es elegible para multienvío: '.$motivo);
    }

    public static function sumaNoCuadra(float $diferencia): self
    {
        return new self('La suma de los valores por caja no cuadra con el total de mercancía (diferencia '.number_format($diferencia, 2, '.', '').').', 'total_mercancia');
    }

    public static function folioRepetido(string $folio): self
    {
        return new self('El folio '.$folio.' está repetido.', 'folio_remision');
    }

    public static function cajaNoPertenece(string $uuid): self
    {
        return new self('La caja '.$uuid.' no pertenece a este pedido.');
    }

    public static function hechosPrevios(): self
    {
        return new self('El pedido ya tiene hechos operativos que impiden reorganizarlo sin regularización.');
    }

    public static function soporteNoDisponible(int $documentoId, ?\Throwable $previous = null): self
    {
        return new self('No se pudo copiar el documento de soporte '.$documentoId.' al envío; verifica que el archivo exista en el almacenamiento y vuelve a intentarlo.', 'multienvio', $previous);
    }
}
