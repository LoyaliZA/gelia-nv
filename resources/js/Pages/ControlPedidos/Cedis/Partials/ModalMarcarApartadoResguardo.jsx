import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, PackageCheck, Loader2 } from 'lucide-react';
import { THEME_TEXTAREA, THEME_LABEL } from '../../../../utils/geliaTheme';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, BTN_PRIMARY, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import GaleriaEvidenciasPedido from '../../Partials/GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import usePedidoDialog from '../../Partials/usePedidoDialog';

export default function ModalMarcarApartadoResguardo({ abierto, onClose, pedido }) {
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [detalle, setDetalle] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [confirmarCierre, setConfirmarCierre] = useState(false);
    const [galeria, setGaleria] = useState(null);
    const previewsRef = useRef(previews);
    previewsRef.current = previews;

    useEffect(() => {
        if (!abierto) return;
        previewsRef.current.forEach((p) => URL.revokeObjectURL(p.url));
        setArchivos([]);
        setPreviews([]);
        setDetalle('');
        setError('');
        setProcesando(false);
        setConfirmarCierre(false);
        setGaleria(null);
    }, [abierto, pedido?.id]);
    useEffect(() => () => previewsRef.current.forEach((p) => URL.revokeObjectURL(p.url)), []);

    const pedirCerrar = () => {
        if (procesando) return;
        if (archivos.length || detalle.trim()) setConfirmarCierre(true);
        else onClose();
    };
    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido), onClose: pedirCerrar, bloqueado: procesando });
    const enviar = (event) => {
        event.preventDefault();
        if (procesando) return;
        if (archivos.length === 0) {
            setError('Adjunta al menos una foto de las piezas apartadas.');
            document.getElementById('apartado-evidencias')?.focus();
            return;
        }
        setProcesando(true);
        setError('');
        router.post(route('control_pedidos.cedis.marcar_resguardo_apartado', pedido.id), { evidencias: archivos, detalle: detalle.trim() || null }, {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => setError(errors.evidencias || errors['evidencias.0'] || errors.detalle || 'No se pudo registrar el apartado. Revisa los archivos e intenta otra vez.'),
            onFinish: () => setProcesando(false),
        });
    };
    if (!abierto || !pedido) return null;
    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-center py-4`}>
                <form {...dialog} aria-labelledby="apartado-titulo" onSubmit={enviar} className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog-workspace max-w-xl w-full`}>
                    <header className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <h2 id="apartado-titulo" className="text-lg font-semibold theme-text-main m-0 flex items-center gap-2">
                                <PackageCheck className="w-5 h-5 theme-text-primario" aria-hidden="true" /> Confirmar separación
                            </h2>
                            <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-2" />
                            <p className="text-sm theme-text-muted mt-2 m-0">{pedido.cliente?.nombre || 'Pedido en resguardo'}</p>
                        </div>
                        <button type="button" disabled={procesando} onClick={pedirCerrar} className="p-2 min-h-[44px] min-w-[44px] rounded-xl theme-element border theme-border theme-text-main inline-flex items-center justify-center" aria-label="Cerrar separación">
                            <X className="w-5 h-5" aria-hidden="true" />
                        </button>
                    </header>
                    <div className="gelia-modal-body p-5 space-y-4">
                        <p className="text-sm theme-text-muted m-0">Confirma que las piezas están separadas y listas para resguardo. Ventas recibirá las fotos y tu nota.</p>
                        <section className="gelia-pedidos-seccion">
                            <GaleriaEvidenciasPedido id="apartado-evidencias" label="Fotos de las piezas apartadas" obligatorio soloImagenes maxArchivos={8} maxMb={5}
                                archivos={archivos} previews={previews} disabled={procesando} error={error}
                                onChange={(files, images) => { setArchivos(files); setPreviews(images); setError(''); }}
                                onVer={(documentos, indice) => setGaleria({ documentos, indice })} />
                        </section>
                        <section className="gelia-pedidos-seccion">
                            <label htmlFor="detalle-apartado" className={THEME_LABEL}>Ubicación o nota para Ventas (opcional)</label>
                            <textarea id="detalle-apartado" name="detalle" autoComplete="off" disabled={procesando} value={detalle} onChange={(event) => setDetalle(event.target.value)} rows={3} maxLength={2000}
                                placeholder="Ej. Rack B-3, 2 cajas con el folio del pedido…" className={`${THEME_TEXTAREA} w-full mt-2 py-3 resize-y min-h-[100px]`} />
                            <p className="text-xs theme-text-muted m-0 mt-2">Indica dónde se encuentran las piezas para facilitar su identificación.</p>
                        </section>
                    </div>
                    <footer className="gelia-modal-footer gelia-pedidos-apartado-footer p-4 sm:p-5 flex flex-wrap gap-2 items-center">
                        <span className="text-xs theme-text-muted flex-1 tabular-nums whitespace-nowrap">{previews.length} de 8 fotos</span>
                        <button type="button" disabled={procesando} onClick={pedirCerrar} className={BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={procesando} aria-busy={procesando} className={`${BTN_PRIMARY} inline-flex items-center justify-center gap-2`}>
                            {procesando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden="true" /> : <PackageCheck className="w-4 h-4" aria-hidden="true" />}
                            {procesando ? 'Guardando…' : 'Confirmar apartado'}
                        </button>
                    </footer>
                </form>
            </div>
            <ModalConfirmarAccion abierto={confirmarCierre} titulo="Descartar separación" mensaje="Las fotos y la nota todavía no se han registrado. ¿Quieres descartarlas?"
                etiquetaConfirmar="Descartar" variante="danger" onClose={() => setConfirmarCierre(false)} onConfirm={() => { setConfirmarCierre(false); onClose(); }} />
            <ModalVistaPreviaDocumento abierto={Boolean(galeria)} documentos={galeria?.documentos} indice={galeria?.indice || 0} onClose={() => setGaleria(null)} />
        </>, document.body,
    );
}
