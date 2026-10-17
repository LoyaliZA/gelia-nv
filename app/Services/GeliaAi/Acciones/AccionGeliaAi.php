<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;

interface AccionGeliaAi
{
    public function id(): string;

    public function permiso(): string;

    /** Descripción para la tool proponer_{id}. */
    public function descripcion(): string;

    /**
     * JSON Schema (object) de los argumentos que la IA puede enviar.
     *
     * @return array<string, mixed>
     */
    public function parametros(): array;

    /**
     * Valida y normaliza sin ejecutar. Nunca lanza por datos de entrada.
     *
     * @param  array<string, mixed>  $args
     * @param  list<array<string, mixed>>  $archivos  metadatos de archivos disponibles en la conversación
     * `detalle` alimenta la tarjeta de confirmación: operacion, almacen?, archivos (nombre, rol?, filas?), columnas?, efecto, confirmar (texto del botón).
     *
     * @return array{ok: true, payload: array<string, mixed>, resumen: string, advertencias: list<string>, detalle: array<string, mixed>}|array{ok: false, error_code: string, error: string, faltantes?: list<array{dato: string, detalle: string}>, opciones?: list<mixed>}
     */
    public function preparar(User $user, array $args, array $archivos = []): array;

    /**
     * Ejecuta un payload ya confirmado. Vuelve a validar con preparar().
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, accion: string, reporte: array<string, mixed>}
     */
    public function ejecutar(User $user, array $payload): array;
}
