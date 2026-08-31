import React, { useState } from 'react';
import { CheckCircle2, Flag } from 'lucide-react';
import { BTN_SECONDARY, badgeAdminEstado, puedeAccionesAdmin, labelAdminEstado } from './pagosPedidosStyles';
import ModalReportarErrorAdminPagos from './ModalReportarErrorAdminPagos';
import { confirmarExhibicionAdmin, confirmarPedidoAdmin, mergeAdminEnDetalle } from './accionesAdminPagos';

const BTN_OK = `${BTN_SECONDARY} !border-emerald-500/40 !text-emerald-700 dark:!text-emerald-300`;
const BTN_ERR = `${BTN_SECONDARY} !border-amber-500/40 !text-amber-800 dark:!text-amber-200`;

export function BadgeAdminEstado({ resumen, resumenLabel }) {
    if (!resumen) return null;
    return (
        <span className={badgeAdminEstado(resumen)}>
            {resumenLabel || labelAdminEstado(resumen)}
        </span>
    );
}

export function AccionesAdminPedido({
    auth,
    cierreId,
    adminResumen,
    pedidoTieneError,
    onActualizado,
    onRecargarLista,
    compacto = false,
}) {
    const [modalError, setModalError] = useState(false);
    const [cargando, setCargando] = useState(false);
    const [aviso, setAviso] = useState(null);

    const { puedeConfirmar, puedeReportar } = puedeAccionesAdmin(auth);
    const pendiente = adminResumen === 'pendiente' || adminResumen === 'parcial';
    const bloqueado = pedidoTieneError || adminResumen === 'con_error' || adminResumen === 'confirmado';

    if (!puedeConfirmar && !puedeReportar) return null;

    const confirmar = async () => {
        setCargando(true);
        setAviso(null);
        try {
            const resp = await confirmarPedidoAdmin(cierreId);
            onActualizado?.(resp);
            setAviso('Pedido confirmado por Administración.');
            onRecargarLista?.();
        } catch (e) {
            setAviso(e.message);
        } finally {
            setCargando(false);
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-2" onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>
            {puedeConfirmar && pendiente && !pedidoTieneError && (
                <button type="button" className={compacto ? `${BTN_OK} !py-1.5 !px-2.5 text-[11px]` : BTN_OK} disabled={cargando} onClick={confirmar}>
                    <CheckCircle2 className="w-4 h-4 shrink-0" aria-hidden="true" />
                    <span>{compacto ? 'Confirmar' : 'Confirmar pedido'}</span>
                </button>
            )}
            {puedeReportar && !bloqueado && (
                <button type="button" className={compacto ? `${BTN_ERR} !py-1.5 !px-2.5 text-[11px]` : BTN_ERR} disabled={cargando} onClick={() => setModalError(true)}>
                    <Flag className="w-4 h-4 shrink-0" aria-hidden="true" />
                    <span>{compacto ? 'Error' : 'Reportar error'}</span>
                </button>
            )}
            {aviso && <span className="text-[11px] theme-text-muted">{aviso}</span>}
            <ModalReportarErrorAdminPagos
                abierto={modalError}
                onCerrar={() => setModalError(false)}
                cierreId={cierreId}
                titulo="Reportar error en pedido"
                subtitulo="Se notificará a la vendedora y a la auxiliar del departamento."
                onExito={(resp) => {
                    onActualizado?.(resp);
                    onRecargarLista?.();
                }}
            />
        </div>
    );
}

export function AccionesAdminExhibicion({
    auth,
    cierreId,
    exhibicion,
    pedidoTieneError,
    onActualizado,
    onRecargarLista,
}) {
    const [modalError, setModalError] = useState(false);
    const [cargando, setCargando] = useState(false);

    const { puedeConfirmar, puedeReportar } = puedeAccionesAdmin(auth);
    const estado = exhibicion?.admin_estado || 'pendiente';
    const pendiente = estado === 'pendiente' && !pedidoTieneError;

    if (!puedeConfirmar && !puedeReportar) return null;
    if (!pendiente && estado !== 'con_error') {
        return (
            <span className={badgeAdminEstado(estado)}>
                {exhibicion.admin_estado_label || labelAdminEstado(estado)}
            </span>
        );
    }

    const confirmar = async () => {
        setCargando(true);
        try {
            const resp = await confirmarExhibicionAdmin(cierreId, exhibicion.id);
            onActualizado?.(resp);
            onRecargarLista?.();
        } catch (e) {
            window.alert(e.message);
        } finally {
            setCargando(false);
        }
    };

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {estado === 'con_error' && (
                <span className={badgeAdminEstado('con_error')}>Error reportado</span>
            )}
            {puedeConfirmar && pendiente && (
                <button type="button" className={`${BTN_OK} !py-1 !px-2 text-[10px]`} disabled={cargando} onClick={confirmar}>
                    Confirmar
                </button>
            )}
            {puedeReportar && pendiente && (
                <button type="button" className={`${BTN_ERR} !py-1 !px-2 text-[10px]`} disabled={cargando} onClick={() => setModalError(true)}>
                    Error
                </button>
            )}
            <ModalReportarErrorAdminPagos
                abierto={modalError}
                onCerrar={() => setModalError(false)}
                cierreId={cierreId}
                itemId={exhibicion.id}
                titulo={`Reportar error — exhibición #${exhibicion.numero_exhibicion}`}
                subtitulo={`${exhibicion.banco || 'Sin banco'} · ${exhibicion.forma_pago_label || ''}`}
                onExito={(resp) => {
                    onActualizado?.(resp);
                    onRecargarLista?.();
                }}
            />
        </div>
    );
}

export function useAdminDetalleUpdater(cacheDetalle, onCacheDetalle, cierreId) {
    return (resp) => {
        const prev = cacheDetalle[cierreId];
        if (prev && onCacheDetalle) {
            onCacheDetalle(cierreId, mergeAdminEnDetalle(prev, resp));
        }
    };
}
