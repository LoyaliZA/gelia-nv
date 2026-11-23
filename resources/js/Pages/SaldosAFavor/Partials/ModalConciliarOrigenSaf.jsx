import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Upload, Scale } from 'lucide-react';
import SafModal from './SafModal';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA, fmtMoneda } from './safStyles';

const MAX_ARCHIVOS = 8;

function mensaje(error) {
    const datos = error?.response?.data;
    return (datos?.errors ? Object.values(datos.errors).flat()[0] : null) || datos?.message || 'No se pudo conciliar el saldo. Intente de nuevo.';
}

/**
 * Administración confirma el origen de un saldo pendiente. Solo cambia su uso; no mueve montos, reservas ni aplicaciones.
 * Quien generó el saldo no lo concilia (el servidor lo valida).
 */
export default function ModalConciliarOrigenSaf({ credito, onClose }) {
    const [uso, setUso] = useState('solo_transporte');
    const [motivo, setMotivo] = useState('');
    const [archivos, setArchivos] = useState([]);
    const [error, setError] = useState('');
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        setUso('solo_transporte');
        setMotivo('');
        setArchivos([]);
        setError('');
    }, [credito?.id]);

    const guardar = async (e) => {
        e.preventDefault();
        setError('');
        const datos = new FormData();
        datos.append('uso', uso);
        datos.append('motivo', motivo.trim());
        archivos.forEach((archivo) => datos.append('evidencias[]', archivo));
        setProcesando(true);
        try {
            await axios.post(route('saldos_favor.conciliar_origen', credito.id), datos, { headers: { Accept: 'application/json' } });
            router.reload({ preserveScroll: true });
            onClose();
        } catch (err) {
            setError(mensaje(err));
        } finally {
            setProcesando(false);
        }
    };

    return (
        <SafModal abierto={Boolean(credito)} onClose={onClose} maxWidth="max-w-lg" labelledBy="saf-conciliar-titulo">
            <form onSubmit={guardar} className="gelia-modal-body p-6 space-y-4 max-h-[min(90vh,920px)] overflow-y-auto">
                <div className="flex items-center gap-3">
                    <Scale className="w-5 h-5 theme-text-muted shrink-0" aria-hidden="true" />
                    <h2 id="saf-conciliar-titulo" className="text-lg font-black italic uppercase tracking-tight theme-text-main m-0">
                        Conciliar origen · {credito?.folio}
                    </h2>
                </div>
                <p className="text-xs theme-text-muted font-bold m-0">
                    Saldo de {fmtMoneda(credito?.monto_disponible)} pendiente de conciliar. Mientras no se confirme su origen no se aplica a ningún pedido. Esta decisión solo cambia su uso: no mueve montos, reservas ni aplicaciones.
                </p>
                <div>
                    <label className={`${THEME_LABEL} mb-2 block`} htmlFor="saf-uso">Origen comprobado *</label>
                    <select id="saf-uso" value={uso} onChange={(e) => setUso(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                        <option value="solo_transporte">Transporte pagado y no utilizado: solo gastos de envío</option>
                        <option value="general">Mercancía: uso general</option>
                    </select>
                    <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">Un origen mixto sigue pendiente; no se divide.</p>
                </div>
                <div>
                    <label className={`${THEME_LABEL} mb-2 block`} htmlFor="saf-conciliar-motivo">Cómo se comprobó *</label>
                    <textarea id="saf-conciliar-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} rows={3} maxLength={2000} className={`${THEME_TEXTAREA} w-full`} />
                    <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">Mínimo 10 caracteres.</p>
                </div>
                <div>
                    <span className={`${THEME_LABEL} mb-2 block`}>Evidencia *</span>
                    <label className="flex items-center gap-2 p-3 rounded-xl border theme-border theme-element cursor-pointer min-h-11">
                        <Upload className="w-4 h-4 theme-text-muted shrink-0" aria-hidden="true" />
                        <span className="text-xs font-bold theme-text-main break-all">
                            {archivos.length ? archivos.map((a) => a.name).join(', ') : 'Adjuntar PDF o imagen'}
                        </span>
                        <input
                            type="file"
                            multiple
                            accept="image/*,.pdf"
                            className="sr-only"
                            onChange={(e) => { setArchivos(Array.from(e.target.files || []).slice(0, MAX_ARCHIVOS)); e.target.value = ''; }}
                        />
                    </label>
                </div>
                {error && <p role="alert" className="text-[11px] text-red-500 font-bold m-0">{error}</p>}
                <div className="flex justify-end gap-3">
                    <button type="button" onClick={onClose} className={BTN_SECONDARY} disabled={procesando}>Cancelar</button>
                    <button type="submit" className={BTN_PRIMARY} disabled={procesando}>{procesando ? 'Guardando...' : 'Confirmar origen'}</button>
                </div>
            </form>
        </SafModal>
    );
}
