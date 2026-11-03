import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Camera, QrCode, X, Loader2 } from 'lucide-react';
import usePedidoDialog from '../../Partials/usePedidoDialog';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../Partials/pedidosBmaStyles';
import { compressImageToWebp, validateImageSource } from '../../../../utils/compressImage';
import { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';

export default function ModalSesionEvidenciaTienda({ abierto, onCerrar, tareaId, onImportado = null }) {
    const [cargando, setCargando] = useState(false);
    const [sesion, setSesion] = useState(null);
    const [error, setError] = useState('');
    const [fotos, setFotos] = useState([]);
    const [conectado, setConectado] = useState(false);
    const [subiendo, setSubiendo] = useState(false);
    const vistas = useRef(new Set());
    const reflejando = useRef(false);
    const alReflejar = useRef(onImportado);
    alReflejar.current = onImportado;
    const movil = esDispositivoCampo();

    useEffect(() => {
        if (!abierto || !tareaId) return undefined;
        let vivo = true;
        setCargando(true);
        setError('');
        setFotos([]);
        setConectado(false);
        vistas.current = new Set();
        axios.post(route('control_pedidos.tienda.sesion_evidencia.store', tareaId))
            .then(({ data }) => {
                if (!vivo) return;
                setSesion(data);
            })
            .catch((e) => {
                if (!vivo) return;
                setError(e.response?.data?.message || 'No se pudo generar el QR');
            })
            .finally(() => { if (vivo) setCargando(false); });
        return () => { vivo = false; };
    }, [abierto, tareaId]);

    useEffect(() => {
        if (!abierto || !tareaId || !sesion) return undefined;
        let vivo = true;
        const sincronizar = async () => {
            try {
                const { data } = await axios.get(route('control_pedidos.tienda.sesion_evidencia.show', tareaId));
                if (!vivo) return;
                const remotas = data.sesion?.fotos || [];
                setFotos(remotas);
                setConectado(data.sesion?.estado === 'activa');
                const nuevas = remotas.filter((foto) => foto.id && !vistas.current.has(foto.id));
                if (!nuevas.length || reflejando.current) return;
                reflejando.current = true;
                try {
                    await axios.post(route('control_pedidos.tienda.sesion_evidencia.reflejar', tareaId));
                    nuevas.forEach((foto) => vistas.current.add(foto.id));
                    await alReflejar.current?.();
                } finally {
                    reflejando.current = false;
                }
            } catch {
                /* La sesión pudo cerrarse mientras se recuperaba la conexión. */
            }
        };
        // shortcut: sondeo cada 2s, pasar a broadcast si el retraso se nota
        sincronizar();
        const t = window.setInterval(sincronizar, 2000);
        return () => { vivo = false; window.clearInterval(t); };
    }, [abierto, tareaId, sesion]);

    const tomar = async (file) => {
        if (!file || !sesion?.codigo || subiendo) return;
        const aviso = validateImageSource(file, 'Foto');
        if (aviso) { setError(aviso); return; }
        setSubiendo(true);
        setError('');
        try {
            const comprimida = await compressImageToWebp(file);
            const form = new FormData();
            form.append('foto', comprimida);
            await axios.post(`/tienda-evidencia/${sesion.codigo}/fotos`, form);
        } catch (e) {
            setError(e.response?.data?.message || e.response?.data?.errors?.foto?.[0] || 'No se pudo enviar la foto.');
        } finally {
            setSubiendo(false);
        }
    };

    const cancelar = async () => {
        if (tareaId) {
            try { await axios.post(route('control_pedidos.tienda.sesion_evidencia.cancelar', tareaId)); } catch (_) { /* noop */ }
        }
        setSesion(null);
        onCerrar?.();
    };

    const promover = async () => {
        setCargando(true);
        try {
            await axios.post(route('control_pedidos.tienda.sesion_evidencia.promover', tareaId));
            await onImportado?.();
            onCerrar?.();
        } catch (e) {
            setError(e.response?.data?.message || 'No se pudieron importar las fotos');
        } finally {
            setCargando(false);
        }
    };

    const dialog = usePedidoDialog({ abierto, onClose: cancelar, bloqueado: cargando || subiendo });

    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`}>
            <div {...dialog} aria-label="Evidencia con celular" className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 space-y-4`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between gap-2">
                    <h2 className="font-black text-lg flex items-center gap-2 m-0">
                        <QrCode className="w-5 h-5" /> Evidencia con celular
                    </h2>
                    <button type="button" disabled={cargando || subiendo} onClick={cancelar} aria-label="Cerrar" className="p-2 min-h-[44px] min-w-[44px]">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                {cargando && !sesion && (
                    <div className="flex justify-center py-8">
                        <Loader2 className="w-8 h-8 animate-spin" />
                    </div>
                )}
                {error && <p role="alert" className="text-sm font-semibold theme-text-peligro m-0">{error}</p>}
                {sesion?.qr_data_uri && (
                    <div className="text-center space-y-3">
                        <p role="status" className={`text-sm font-semibold m-0 ${conectado ? 'theme-text-exito' : 'theme-text-muted'}`}>
                            {conectado ? 'Celular conectado' : 'Esperando conexión del celular'}
                        </p>
                        {movil ? (
                            <label className={`${BTN_PRIMARY} w-full min-h-[48px] inline-flex items-center justify-center gap-2 cursor-pointer ${subiendo ? 'pointer-events-none opacity-60' : ''}`}>
                                {subiendo ? <Loader2 className="w-5 h-5 animate-spin" aria-hidden="true" /> : <Camera className="w-5 h-5" aria-hidden="true" />}
                                {subiendo ? 'Enviando…' : 'Tomar foto con la cámara'}
                                <input type="file" accept="image/*" capture="environment" className="sr-only" disabled={subiendo} aria-label="Tomar foto con la cámara"
                                    onChange={(e) => { tomar(e.target.files?.[0]); e.target.value = ''; }} />
                            </label>
                        ) : (
                            <>
                                {/* ponytail: style-exception QR legible en cualquier tema */}
                                <img src={sesion.qr_data_uri} alt="QR evidencia" className="mx-auto w-56 h-56 bg-white p-2 rounded-xl border theme-border" />
                                <p className="text-xs theme-text-muted m-0">Escanee el QR. Las fotos aparecen aquí y quedan en la tarea.</p>
                            </>
                        )}
                        <p className="text-xs theme-text-muted break-all m-0">{sesion.url}</p>
                        <p className="text-xs m-0">Expira: {new Date(sesion.expira_en).toLocaleTimeString('es-MX')}</p>
                        {fotos.length > 0 && (
                            <div className="grid grid-cols-3 gap-2" aria-label="Fotos recibidas del celular">
                                {fotos.map((foto) => (
                                    <img key={foto.id} src={foto.url} alt={foto.nombre || 'Evidencia del celular'} width="120" height="120" className="w-full aspect-square object-cover rounded-xl border theme-border" />
                                ))}
                            </div>
                        )}
                        <p className="text-xs theme-text-muted m-0">{fotos.length} {fotos.length === 1 ? 'foto en este equipo' : 'fotos en este equipo'}</p>
                        <button type="button" disabled={cargando || subiendo} className={`${BTN_PRIMARY} w-full min-h-[44px]`} onClick={promover}>
                            Terminar conexión
                        </button>
                    </div>
                )}
                <button type="button" disabled={cargando || subiendo} className={`${BTN_SECONDARY} w-full min-h-[44px]`} onClick={cancelar}>
                    Cerrar
                </button>
            </div>
        </div>,
        document.body
    );
}
