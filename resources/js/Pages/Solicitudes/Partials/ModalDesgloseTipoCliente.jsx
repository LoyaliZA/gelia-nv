import React from 'react';
import { Users } from 'lucide-react';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import CabeceraFlujoSolicitud from '@/Components/Solicitudes/CabeceraFlujoSolicitud';
import { DesgloseTipoCliente } from './ResumenSolicitudes';

export default function ModalDesgloseTipoCliente({
    resumen,
    filtroTipoCliente = '',
    onFiltrarTipo,
    onLimpiarTipo,
    onClose,
}) {
    const filtrar = (tipoClienteId) => {
        onFiltrarTipo?.(tipoClienteId);
        onClose?.();
    };
    const limpiar = () => {
        onLimpiarTipo?.();
        onClose?.();
    };

    return (
        <SolicitudDialog title="Tipos de cliente" onClose={onClose} className="gelia-tag-lista-overlay">
            <div className="gelia-modal-shell gelia-tag-desglose w-full max-w-3xl">
                <CabeceraFlujoSolicitud
                    icono={Users}
                    tono="primario"
                    titulo="Tipos de cliente"
                    subtitulo="Toca un tipo para filtrar la lista"
                    closeLabel="Cerrar tipos de cliente"
                />
                <div className="gelia-modal-body p-5 sm:p-6">
                    <DesgloseTipoCliente
                        resumen={resumen}
                        filtroTipoCliente={filtroTipoCliente}
                        onFiltrarTipo={filtrar}
                        onLimpiarTipo={limpiar}
                    />
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions">
                    <button type="button" data-dialog-close className="theme-btn-secondary">Cerrar</button>
                </footer>
            </div>
        </SolicitudDialog>
    );
}
