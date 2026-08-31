import React, { useCallback, useState } from 'react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import ModalConfirmarAccion from '@/Pages/ControlPedidos/Partials/ModalConfirmarAccion';
import {
    BTN_OK,
    BTN_ERR,
    BTN_ICON,
    GRUPO_ACCIONES,
    badgeAdminEstado,
    puedeAccionesAdmin,
    labelAdminEstado,
} from './pagosPedidosStyles';
import { PEDIDO_FOOTER_ADMIN } from './GridFilasPagosPedidos';
import { EtiquetaRevisionAdmin } from './EtiquetaEstadoCategorizado';
import ModalReportarErrorAdminPagos from './ModalReportarErrorAdminPagos';
import { confirmarExhibicionAdmin, confirmarPedidoAdmin, mergeAdminEnDetalle } from './accionesAdminPagos';

const LABEL_APROBAR_PEDIDO = 'Aprobar pedido';
const LABEL_ERROR_PEDIDO = 'Marcar con error';
const LABEL_APROBAR_EXHIBICION = 'Aprobar exhibición';
const LABEL_ERROR_EXHIBICION = 'Marcar con error';

export function BadgeAdminEstado({ resumen, resumenLabel, categorizado = false }) {
    if (!resumen) return null;
    const label = resumenLabel || labelAdminEstado(resumen);

    if (categorizado) {
        return <EtiquetaRevisionAdmin resumen={resumen} resumenLabel={label} />;
    }

    return (
        <span className={badgeAdminEstado(resumen)}>
            {label}
        </span>
    );
}

export function AccionesAdminPedido({
    auth,
    cierreId,
    adminResumen,
    pedidoTieneError,
    exhibicionesPendientes = 0,
    onActualizado,
    onRecargarLista,
}) {
    const [modalError, setModalError] = useState(false);
    const [modalConfirmar, setModalConfirmar] = useState(false);
    const [cargando, setCargando] = useState(false);
    const [aviso, setAviso] = useState(null);

    const { puedeConfirmar, puedeReportar } = puedeAccionesAdmin(auth);
    const pendiente = adminResumen === 'pendiente' || adminResumen === 'parcial';
    const revisionCerrada = adminResumen === 'confirmado' || adminResumen === 'con_error' || pedidoTieneError;
    const bloqueado = revisionCerrada;
    const pendientesCount = Number(exhibicionesPendientes ?? 0);

    if (!puedeConfirmar && !puedeReportar) return null;

    const confirmar = async () => {
        setCargando(true);
        setAviso(null);
        try {
            const resp = await confirmarPedidoAdmin(cierreId);
            onActualizado?.(resp);
            setAviso('Pedido aprobado por Administración.');
        } catch (e) {
            setAviso(e.message);
        } finally {
            setCargando(false);
            setModalConfirmar(false);
        }
    };

    const mensajeConfirmacion = pendientesCount === 1
        ? 'Se aprobará 1 exhibición pendiente de este pedido.'
        : `Se aprobarán ${pendientesCount.toLocaleString('es-MX')} exhibiciones pendientes de este pedido.`;

    const muestraAprobar = puedeConfirmar && pendiente && !pedidoTieneError;
    const muestraError = puedeReportar && !bloqueado;

    if (!muestraAprobar && !muestraError) return null;

    return (
        <div className={GRUPO_ACCIONES} onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>
            {puedeConfirmar && pendiente && !pedidoTieneError && (
                <button type="button" className={BTN_OK} disabled={cargando} onClick={() => setModalConfirmar(true)}>
                    <CheckCircle2 className={BTN_ICON} aria-hidden="true" />
                    <span>{LABEL_APROBAR_PEDIDO}</span>
                </button>
            )}
            {puedeReportar && !bloqueado && (
                <button type="button" className={BTN_ERR} disabled={cargando} onClick={() => setModalError(true)}>
                    <AlertTriangle className={BTN_ICON} aria-hidden="true" />
                    <span>{LABEL_ERROR_PEDIDO}</span>
                </button>
            )}
            {aviso && <span className="text-[11px] theme-text-muted">{aviso}</span>}
            <ModalConfirmarAccion
                abierto={modalConfirmar}
                titulo="Aprobar pedido completo"
                mensaje={`${mensajeConfirmacion} Esta acción no se puede deshacer desde aquí.`}
                etiquetaConfirmar="Aprobar exhibiciones pendientes"
                variante="primary"
                onClose={() => setModalConfirmar(false)}
                onConfirm={confirmar}
            />
            <ModalReportarErrorAdminPagos
                abierto={modalError}
                onCerrar={() => setModalError(false)}
                cierreId={cierreId}
                titulo="Reportar error en pedido"
                subtitulo="Se notificará a la vendedora y a la auxiliar del departamento."
                onExito={(resp) => {
                    onActualizado?.(resp);
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
    const [modalConfirmar, setModalConfirmar] = useState(false);
    const [cargando, setCargando] = useState(false);

    const { puedeConfirmar, puedeReportar } = puedeAccionesAdmin(auth);
    const estado = exhibicion?.admin_estado || 'pendiente';
    const pendiente = estado === 'pendiente' && !pedidoTieneError;

    if (!puedeConfirmar && !puedeReportar) return null;

    if (!pendiente && estado !== 'con_error') {
        return <EtiquetaRevisionAdmin resumen={estado} />;
    }

    if (estado === 'con_error') {
        return <EtiquetaRevisionAdmin resumen="con_error" />;
    }

    const confirmar = async () => {
        setCargando(true);
        try {
            const resp = await confirmarExhibicionAdmin(cierreId, exhibicion.id);
            onActualizado?.(resp);
        } catch (e) {
            window.alert(e.message);
        } finally {
            setCargando(false);
            setModalConfirmar(false);
        }
    };

    return (
        <div className="flex flex-col gap-2.5 min-w-0">
            <EtiquetaRevisionAdmin resumen="pendiente" />
            <div className={GRUPO_ACCIONES}>
                {puedeConfirmar && (
                    <button type="button" className={BTN_OK} disabled={cargando} onClick={() => setModalConfirmar(true)}>
                        <CheckCircle2 className={BTN_ICON} aria-hidden="true" />
                        <span>{LABEL_APROBAR_EXHIBICION}</span>
                    </button>
                )}
                {puedeReportar && (
                    <button type="button" className={BTN_ERR} disabled={cargando} onClick={() => setModalError(true)}>
                        <AlertTriangle className={BTN_ICON} aria-hidden="true" />
                        <span>{LABEL_ERROR_EXHIBICION}</span>
                    </button>
                )}
            </div>
            <ModalConfirmarAccion
                abierto={modalConfirmar}
                titulo="Aprobar exhibición"
                mensaje={`Se aprobará la exhibición #${exhibicion.numero_exhibicion} (${exhibicion.banco || 'sin banco'} · ${exhibicion.forma_pago_label || 'pago'}). Esta acción no se puede deshacer desde aquí.`}
                etiquetaConfirmar="Aprobar exhibición"
                variante="primary"
                onClose={() => setModalConfirmar(false)}
                onConfirm={confirmar}
            />
            <ModalReportarErrorAdminPagos
                abierto={modalError}
                onCerrar={() => setModalError(false)}
                cierreId={cierreId}
                itemId={exhibicion.id}
                titulo={`Reportar error — exhibición #${exhibicion.numero_exhibicion}`}
                subtitulo={`${exhibicion.banco || 'Sin banco'} · ${exhibicion.forma_pago_label || ''}`}
                onExito={(resp) => {
                    onActualizado?.(resp);
                }}
            />
        </div>
    );
}

export function PieRevisionAdminPedido({
    auth,
    cierreId,
    adminResumen,
    adminResumenLabel,
    adminRevisadoPor,
    adminRevisadoAt,
    pedidoTieneError,
    exhibicionesRevisadas,
    exhibicionesTotal,
    exhibicionesPendientes,
    onActualizado,
    onRecargarLista,
}) {
    const { puedeConfirmar, puedeReportar } = puedeAccionesAdmin(auth);
    const muestraAcciones = puedeConfirmar || puedeReportar;
    const revisionAbierta = (adminResumen === 'pendiente' || adminResumen === 'parcial') && !pedidoTieneError;

    if (!adminResumen && !muestraAcciones) return null;

    return (
        <div
            className={PEDIDO_FOOTER_ADMIN}
            onClick={(e) => e.stopPropagation()}
            onKeyDown={(e) => e.stopPropagation()}
        >
            <EtiquetaRevisionAdmin
                resumen={adminResumen || 'pendiente'}
                resumenLabel={adminResumenLabel}
                exhibicionesRevisadas={exhibicionesRevisadas}
                exhibicionesTotal={exhibicionesTotal}
                revisadoPor={adminRevisadoPor}
                revisadoAt={adminRevisadoAt}
                inline
            />
            {muestraAcciones && revisionAbierta && (
                <div className="flex flex-col sm:flex-row flex-wrap justify-stretch sm:justify-end gap-2 w-full sm:w-auto shrink-0 sm:self-center [&_button]:w-full [&_button]:sm:w-auto">
                    <AccionesAdminPedido
                        auth={auth}
                        cierreId={cierreId}
                        adminResumen={adminResumen}
                        pedidoTieneError={pedidoTieneError}
                        exhibicionesPendientes={exhibicionesPendientes}
                        onActualizado={onActualizado}
                        onRecargarLista={onRecargarLista}
                    />
                </div>
            )}
        </div>
    );
}

export function useAdminDetalleUpdater(onCacheDetalle, cierreId) {
    return useCallback((resp) => {
        if (!onCacheDetalle) return;
        onCacheDetalle(cierreId, (prev) => {
            if (!prev) return prev;
            return mergeAdminEnDetalle(prev, resp);
        });
    }, [onCacheDetalle, cierreId]);
}
