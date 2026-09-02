import React, { useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { ScanLine, Loader2, AlertTriangle } from 'lucide-react';
import InputConEscanner from '../../../../Components/Escanner/InputConEscanner';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { THEME_INPUT } from './resguardosStyles';
import { extraerFolioEscaneado } from './recepcionFisicaUtils';

export default function BusquedaRapidaRecepcion({ puedeRecibir = false }) {
    const [codigo, setCodigo] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [mensaje, setMensaje] = useState(null);

    if (!puedeRecibir) return null;

    const buscar = async (valor) => {
        const folio = extraerFolioEscaneado(valor);
        if (!folio) {
            setMensaje('Ingresa o escanea un folio, QR o código de barras.');
            return;
        }

        setBuscando(true);
        setMensaje(null);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.listado'), {
                params: { bandeja: 'por_recibir', q: folio, per_page: 5 },
                headers: { Accept: 'application/json' },
            });

            const coincidencias = (data.resguardos?.data || []).filter(
                (r) => r.estado === 'pendiente_recepcion',
            );

            if (coincidencias.length === 1) {
                router.visit(route('punto_venta.resguardos.recepcion.create', coincidencias[0].id));
                return;
            }

            if (coincidencias.length === 0) {
                setMensaje('No se encontró un resguardo pendiente de recepción con ese código.');
                return;
            }

            setMensaje('Hay varios resguardos con ese criterio. Refina la búsqueda o elige uno del listado.');
        } catch {
            setMensaje('No se pudo buscar el resguardo. Intenta de nuevo.');
        } finally {
            setBuscando(false);
        }
    };

    const onSubmit = (e) => {
        e.preventDefault();
        buscar(codigo);
    };

    return (
        <div className={`${geliaCardClass()} p-4 md:p-5 space-y-3`}>
            <div className="flex items-center gap-2">
                <ScanLine className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                <div>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Recepción rápida</h2>
                    <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">
                        Escanea o escribe folio, QR o código de barras para abrir el formulario.
                    </p>
                </div>
            </div>

            <form onSubmit={onSubmit} className="flex flex-col sm:flex-row gap-2">
                <InputConEscanner
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    label="resguardo"
                    className={THEME_INPUT}
                    inputProps={{
                        placeholder: 'Folio, remisión o código escaneado…',
                        'aria-label': 'Buscar resguardo para recepción',
                        disabled: buscando,
                    }}
                />
                <button
                    type="submit"
                    disabled={buscando || !codigo.trim()}
                    className={`${THEME_BTN_PRIMARY} min-h-[48px] px-5 text-[10px] font-black uppercase tracking-widest disabled:opacity-50 shrink-0`}
                >
                    {buscando ? <Loader2 className="w-4 h-4 animate-spin mx-auto" /> : 'Abrir recepción'}
                </button>
            </form>

            {mensaje && (
                <p className="text-xs font-bold text-amber-700 dark:text-amber-300 m-0 flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                    {mensaje}
                </p>
            )}
        </div>
    );
}
