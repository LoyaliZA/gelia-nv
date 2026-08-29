import React, { useEffect, useState } from 'react';
import { Download } from 'lucide-react';
import { BTN_SECONDARY } from './pagosPedidosStyles';
import ModalExportarPagosPedidos from './ModalExportarPagosPedidos';
import {
    getStoredPagosPedidosReporteJobId,
    PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT,
    PAGOS_PEDIDOS_REPORTE_STARTED_EVENT,
} from '../../../../utils/pagosPedidosReporteTracker';

export default function MenuExportarPagos({
    filtrosQuery,
    puedeCsv,
    puedePdf,
    bancos,
    formasPago,
    departamentos,
    vendedores,
    almacenes,
    origenesPedido,
    onAvisoSegundoPlano,
    jobSeguimientoExterno,
    onLimpiarSeguimiento,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [jobSeguimiento, setJobSeguimiento] = useState(null);
    const [jobActivo, setJobActivo] = useState(() => Boolean(getStoredPagosPedidosReporteJobId()));

    useEffect(() => {
        const sync = () => setJobActivo(Boolean(getStoredPagosPedidosReporteJobId()));
        window.addEventListener(PAGOS_PEDIDOS_REPORTE_STARTED_EVENT, sync);
        window.addEventListener(PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT, sync);
        return () => {
            window.removeEventListener(PAGOS_PEDIDOS_REPORTE_STARTED_EVENT, sync);
            window.removeEventListener(PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT, sync);
        };
    }, []);

    useEffect(() => {
        if (jobSeguimientoExterno) {
            setJobSeguimiento(jobSeguimientoExterno);
            setModalAbierto(true);
        }
    }, [jobSeguimientoExterno]);

    const abrirModal = () => {
        setJobSeguimiento(null);
        onLimpiarSeguimiento?.();
        setModalAbierto(true);
    };

    const cerrarModal = ({ enGeneracion = false } = {}) => {
        setModalAbierto(false);
        setJobSeguimiento(null);
        onLimpiarSeguimiento?.();
        if (enGeneracion) {
            onAvisoSegundoPlano?.();
        }
    };

    if (!puedeCsv && !puedePdf) {
        return (
            <p className="text-[11px] font-bold theme-text-muted uppercase tracking-widest m-0 pt-4 border-t theme-border">
                Exportación no habilitada para su usuario. Solicite acceso a CSV/PDF con administración.
            </p>
        );
    }

    return (
        <>
            <div className="pt-4 border-t theme-border space-y-2">
                <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0">
                    Reporte administrativo
                </p>
                <button
                    type="button"
                    onClick={abrirModal}
                    className={BTN_SECONDARY}
                >
                    <Download className="w-4 h-4 shrink-0" />
                    {jobActivo ? 'Generando reporte…' : 'Generar reporte…'}
                </button>
            </div>

            <ModalExportarPagosPedidos
                abierto={modalAbierto}
                onCerrar={cerrarModal}
                jobIdSeguimiento={jobSeguimiento}
                filtrosConsulta={filtrosQuery}
                bancos={bancos}
                formasPago={formasPago}
                departamentos={departamentos}
                vendedores={vendedores}
                almacenes={almacenes}
                origenesPedido={origenesPedido}
                puedeCsv={puedeCsv}
                puedePdf={puedePdf}
            />
        </>
    );
}
