import React, { useEffect, useRef } from 'react';
import { AlertTriangle } from 'lucide-react';
import { CircleCheckIcon } from '@animateicons/react/lucide/circle-check-icon';
import { CloudIcon } from '@animateicons/react/lucide/cloud-icon';
import { HardDriveIcon } from '@animateicons/react/lucide/hard-drive-icon';
import { LoaderCircleIcon } from '@animateicons/react/lucide/loader-circle-icon';
import { SaveIcon } from '@animateicons/react/lucide/save-icon';
import { XIcon } from '@animateicons/react/lucide/x-icon';
import { etiquetaEstatusPedido } from '../pedidosBmaStyles';

/**
 * Encabezado siempre visible del formulario progresivo.
 * "Guardado" solo tras confirmación del servidor.
 */
export default function EncabezadoPedido({
    titulo = 'Pedido',
    folio = null,
    clienteNombre = null,
    estatus = null,
    esResguardo = false,
    requiereLogistica = true,
    complemento = null,
    estadoGuardado = null, // 'guardando' | 'guardado' | 'pendiente' | 'error' | 'conflicto' | null
    onGuardarBorrador = null,
    guardandoBorrador = false,
    borradorConfirmado = false,
    borradorDeshabilitado = false,
    onClose,
}) {
    const labelGuardado = estadoGuardado === 'guardando'
        ? 'Guardando…'
        : estadoGuardado === 'guardado'
            ? 'Guardado'
            : estadoGuardado === 'pendiente'
                ? 'Cambios pendientes'
                : estadoGuardado === 'conflicto'
                    ? 'Cambio en otra sesión'
                    : estadoGuardado === 'error'
                        ? 'Error al guardar'
                        : null;

    return (
        <div className="gelia-pedidos-captura-cabecera border-b theme-border">
            <div className="gelia-pedidos-captura-identidad space-y-1">
                <h2 className="text-base md:text-lg font-bold theme-text-main m-0 break-words">
                    {titulo}{folio ? ` · ${folio}` : ''}
                    {esResguardo ? ' · Resguardo' : ''}
                </h2>
                {clienteNombre && (
                    <p className="text-sm font-medium theme-text-main m-0 break-words">{clienteNombre}</p>
                )}
                {complemento && (
                    <p className="text-sm theme-text-muted m-0 break-words">{complemento}</p>
                )}
                <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium theme-text-muted">
                    {estatus && (
                        <span aria-label={`Estado: ${etiquetaEstatusPedido(estatus, { esResguardo, requiereLogistica })}`}>
                            {etiquetaEstatusPedido(estatus, { esResguardo, requiereLogistica })}
                        </span>
                    )}
                    {labelGuardado && (
                        <span
                            className="inline-flex items-center gap-1"
                            role="status"
                            aria-live="polite"
                        >
                            {estadoGuardado === 'error' || estadoGuardado === 'conflicto'
                                ? <AlertTriangle className="w-3 h-3" />
                                : estadoGuardado === 'guardando'
                                    ? <CloudIcon size={14} className="motion-safe:animate-pulse" aria-hidden="true" />
                                    : <HardDriveIcon size={14} aria-hidden="true" />}
                            {labelGuardado}
                        </span>
                    )}
                </div>
            </div>
            <div className="gelia-pedidos-captura-controles">
                {onGuardarBorrador && (
                    <BotonBorrador
                        guardando={guardandoBorrador}
                        confirmado={borradorConfirmado}
                        deshabilitado={borradorDeshabilitado}
                        onClick={onGuardarBorrador}
                    />
                )}
                <button
                    type="button"
                    onClick={onClose}
                    className={onGuardarBorrador
                        ? 'registrar-tienda-icono-borde'
                        : 'p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted hover:theme-text-main shrink-0'}
                    aria-label="Cerrar"
                >
                    <XIcon size={18} />
                </button>
            </div>
        </div>
    );
}

export function BotonBorrador({ guardando, confirmado, deshabilitado, onClick }) {
    const paloma = useRef(null);
    const estado = guardando ? 'guardando' : confirmado ? 'guardado' : 'listo';
    const leyenda = guardando ? 'Guardando…' : confirmado ? 'Borrador guardado' : 'Guardar borrador';

    useEffect(() => {
        if (confirmado) paloma.current?.startAnimation();
    }, [confirmado]);

    return (
        <button
            type="button"
            className="registrar-tienda-borrador"
            data-estado={estado}
            onClick={onClick}
            disabled={guardando || deshabilitado}
            aria-label={leyenda}
        >
            {guardando
                ? <LoaderCircleIcon size={18} isAnimated={false} className="motion-safe:animate-spin" aria-hidden="true" />
                : confirmado
                    ? <CircleCheckIcon ref={paloma} size={18} isAnimated={false} aria-hidden="true" />
                    : <SaveIcon size={18} aria-hidden="true" />}
            <span className="registrar-tienda-borrador-leyenda">{leyenda}</span>
        </button>
    );
}
