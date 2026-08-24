import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { QrCode, X, Loader2 } from 'lucide-react';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../Partials/pedidosBmaStyles';

export default function ModalSesionEvidenciaTienda({ abierto, onCerrar, tareaId }) {
    const [cargando, setCargando] = useState(false);
    const [sesion, setSesion] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!abierto || !tareaId) return;
        setCargando(true);
        setError('');
        axios.post(route('control_pedidos.tienda.sesion_evidencia.store', tareaId))
            .then(({ data }) => setSesion(data))
            .catch((e) => setError(e.response?.data?.message || 'No se pudo generar el QR'))
            .finally(() => setCargando(false));
    }, [abierto, tareaId]);

    const cancelar = async () => {
        if (tareaId) {
            try { await axios.post(route('control_pedidos.tienda.sesion_evidencia.cancelar', tareaId)); } catch (_) { /* noop */ }
        }
        setSesion(null);
        onCerrar?.();
    };

    const promover = async () => {
        try {
            await axios.post(route('control_pedidos.tienda.sesion_evidencia.promover', tareaId));
            onCerrar?.();
        } catch (e) {
            setError(e.response?.data?.message || 'No se pudieron importar las fotos');
        }
    };

    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`}>
            <div className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 space-y-4`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between gap-2">
                    <h2 className="font-black text-lg flex items-center gap-2 m-0">
                        <QrCode className="w-5 h-5" /> Evidencia con celular
                    </h2>
                    <button type="button" onClick={cancelar} aria-label="Cerrar" className="p-2 min-h-[44px] min-w-[44px]">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                {cargando && (
                    <div className="flex justify-center py-8">
                        <Loader2 className="w-8 h-8 animate-spin" />
                    </div>
                )}
                {error && <p className="text-sm text-red-600 m-0">{error}</p>}
                {sesion?.qr_data_uri && (
                    <div className="text-center space-y-3">
                        <img src={sesion.qr_data_uri} alt="QR evidencia" className="mx-auto w-56 h-56 bg-white p-2 rounded-xl" />
                        <p className="text-xs theme-text-muted break-all m-0">{sesion.url}</p>
                        <p className="text-xs m-0">Expira: {new Date(sesion.expira_en).toLocaleTimeString('es-MX')}</p>
                        <button type="button" className={`${BTN_PRIMARY} w-full min-h-[44px]`} onClick={promover}>
                            Importar fotos del celular
                        </button>
                    </div>
                )}
                <button type="button" className={`${BTN_SECONDARY} w-full min-h-[44px]`} onClick={cancelar}>
                    Cerrar
                </button>
            </div>
        </div>,
        document.body
    );
}
