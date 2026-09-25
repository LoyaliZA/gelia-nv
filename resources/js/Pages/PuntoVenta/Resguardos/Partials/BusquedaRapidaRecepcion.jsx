import React, { useState } from 'react';
import axios from 'axios';
import { ScanLine, Loader2 } from 'lucide-react';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';
import ModalEscanearCodigo from '../../../../Components/Escanner/ModalEscanearCodigo';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import { THEME_INPUT } from './resguardosStyles';
import { extraerFolioEscaneado } from './recepcionFisicaUtils';
import { confirmarRecepcionGerente } from './recepcionGerenteApi';

export default function BusquedaRapidaRecepcion({
    puedeRecibir = false,
    onRecepcionExito,
    variante = 'tarjeta',
    valor,
    onValor,
    onAplicarFiltro,
}) {
    const [codigoInterno, setCodigoInterno] = useState('');
    const controlado = valor !== undefined;
    const codigo = controlado ? valor : codigoInterno;
    const setCodigo = (siguiente) => {
        if (controlado) onValor?.(siguiente);
        else setCodigoInterno(siguiente);
    };
    const [buscando, setBuscando] = useState(false);
    const [mensaje, setMensaje] = useState(null);
    const [exito, setExito] = useState(null);
    const [modalEscaneoAbierto, setModalEscaneoAbierto] = useState(false);

    useToastAlCambiar(mensaje, 'info');
    useToastAlCambiar(exito, 'success');

    if (!puedeRecibir) return null;

    const buscarYConfirmar = async (captura) => {
        const folio = extraerFolioEscaneado(captura);
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
                onAplicarFiltro?.(folio);
                setMensaje('No se encontró un resguardo pendiente de recepción con ese código. El listado quedó filtrado.');
                return;
            }

            if (coincidencias.length > 1) {
                onAplicarFiltro?.(folio);
                setMensaje('Hay varios resguardos con ese criterio. El listado quedó filtrado para elegir uno.');
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

    const embebida = variante === 'embebida';
    const contenedor = embebida ? 'space-y-3' : `${geliaCardClass()} p-4 md:p-5 space-y-3`;

    return (
        <div className={contenedor}>
            {!embebida && (
                <div className="flex items-center gap-2">
                    <ScanLine className="w-4 h-4 shrink-0 text-[var(--color-primario)]" aria-hidden />
                    <h2 className="text-xs font-black uppercase tracking-widest theme-text-main m-0">Recepción rápida</h2>
                </div>
            )}

            <form onSubmit={onSubmit} className="flex flex-col lg:flex-row gap-2 lg:items-stretch">
                <input
                    type="text"
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    placeholder="Folio, remisión o código de barras"
                    aria-label="Buscar resguardo para recepción"
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
