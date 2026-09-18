import React, { useState } from 'react';
import axios from 'axios';
import { ScanLine, Loader2, AlertTriangle, CheckCircle2 } from 'lucide-react';
import ModalEscanearCodigo from '../../../../Components/Escanner/ModalEscanearCodigo';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import { THEME_INPUT } from './resguardosStyles';
import { extraerFolioEscaneado } from './recepcionFisicaUtils';
import { confirmarRecepcionGerente } from './recepcionGerenteApi';

export default function BusquedaRapidaRecepcion({ puedeRecibir = false, onRecepcionExito }) {
    const [codigo, setCodigo] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [exito, setExito] = useState(null);
    const [modalEscaneoAbierto, setModalEscaneoAbierto] = useState(false);

    if (!puedeRecibir) return null;

    const buscarYConfirmar = async (valor) => {
        const folio = extraerFolioEscaneado(valor);
        if (!folio) {
            setExito(null);
            setMensaje('Ingresa o escanea un folio, remisión o código de barras.');
            return;
        }

        setBuscando(true);
        setMensaje(null);
        setExito(null);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.listado'), {
                params: { bandeja: 'por_recibir', q: folio, per_page: 5 },
                headers: { Accept: 'application/json' },
            });

            const coincidencias = (data.resguardos?.data || []).filter(
                (r) => r.estado === 'pendiente_recepcion',
            );

            if (coincidencias.length === 0) {
                setMensaje('No se encontró un resguardo pendiente de recepción con ese código.');
                return;
            }

            if (coincidencias.length > 1) {
                setMensaje('Hay varios resguardos con ese criterio. Refina la búsqueda o elige uno del listado.');
                return;
            }

            const resguardo = coincidencias[0];
            const resultado = await confirmarRecepcionGerente(resguardo);

            if (!resultado.ok) {
                setMensaje(resultado.error);
                return;
            }

            setCodigo('');
            setExito(`Recepción confirmada: ${resguardo.snapshot_folio || `#${resguardo.id}`}`);
            onRecepcionExito?.();
        } catch {
            setMensaje('No se pudo buscar el resguardo. Intenta de nuevo.');
        } finally {
            setBuscando(false);
        }
    };

    const onSubmit = (e) => {
        e.preventDefault();
        buscarYConfirmar(codigo);
    };

    return (
        <div className={`${geliaCardClass()} p-4 md:p-5 space-y-3`}>
            <div className="flex items-center gap-2">
                <ScanLine className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} aria-hidden />
                <h2 className="text-xs font-black uppercase tracking-widest theme-text-main m-0">Recepción rápida</h2>
            </div>

            <form onSubmit={onSubmit} className="flex flex-col lg:flex-row gap-2 lg:items-stretch">
                <input
                    type="text"
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    placeholder="Folio, remisión o código de barras"
                    aria-label="Buscar y confirmar resguardo"
                    disabled={buscando}
                    className={`${THEME_INPUT} flex-1 min-w-0 min-h-[48px]`}
                    autoComplete="off"
                />
                <button
                    type="button"
                    onClick={() => setModalEscaneoAbierto(true)}
                    disabled={buscando}
                    className={`${THEME_BTN_SECONDARY} min-h-[48px] px-4 inline-flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-widest shrink-0`}
                >
                    <ScanLine className="w-4 h-4" aria-hidden />
                    Escanear
                </button>
                <button
                    type="submit"
                    disabled={buscando || !codigo.trim()}
                    className={`${THEME_BTN_PRIMARY} min-h-[48px] px-5 text-[10px] font-black uppercase tracking-widest disabled:opacity-50 shrink-0 inline-flex items-center justify-center gap-2`}
                >
                    {buscando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : 'Confirmar recepción'}
                </button>
            </form>

            {exito && (
                <p className="text-xs font-bold text-emerald-700 dark:text-emerald-300 m-0 flex items-start gap-2">
                    <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5" aria-hidden />
                    {exito}
                </p>
            )}

            {mensaje && (
                <p className="text-xs font-bold text-amber-700 dark:text-amber-300 m-0 flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" aria-hidden />
                    {mensaje}
                </p>
            )}

            <ModalEscanearCodigo
                abierto={modalEscaneoAbierto}
                onCerrar={() => setModalEscaneoAbierto(false)}
                titulo="Escanear resguardo"
                descripcion="Apunta la cámara al código de barras o QR del folio o remisión."
                onEscaneado={(valor) => {
                    setCodigo(valor);
                    setModalEscaneoAbierto(false);
                    buscarYConfirmar(valor);
                }}
            />
        </div>
    );
}
