import React, { useMemo, useState } from 'react';
import { Loader2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { BTN_SECONDARY, THEME_INPUT, THEME_SELECT } from './resguardosStyles';
import { titularResguardo, etiquetaRetiroResguardo } from './resguardosUtils';
import PanelPedidoRevisionResguardo from './PanelPedidoRevisionResguardo';
import BotonesCapturaEvidencia from './BotonesCapturaEvidencia';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

function crearBultosIniciales(resguardo) {
    const pendientes = resguardo?.bultos_pendientes_custodia || [];
    return pendientes.map((bulto) => ({
        folio: bulto.folio,
        tipo: bulto.tipo || 'caja',
        condicion: bulto.condicion || 'bueno',
        piezas: bulto.piezas || 1,
        key: `bulto-${bulto.id}`,
    }));
}

export default function FormularioCustodiaBultos({
    resguardo,
    almacenes = [],
    catalogos = {},
    enviando = false,
    error = null,
    onEnviar,
}) {
    const [almacenId, setAlmacenId] = useState(almacenes.length === 1 ? String(almacenes[0].id) : '');
    const [bultos, setBultos] = useState(() => crearBultosIniciales(resguardo));
    const [evidencias, setEvidencias] = useState([]);
    const [confirmar, setConfirmar] = useState(false);
    const [erroresLocales, setErroresLocales] = useState({});

    useToastAlCambiar(error, 'error');

    const tiposBulto = catalogos.tipos_bulto || {};
    const condiciones = catalogos.condiciones_bulto || {};
    const titular = titularResguardo(resguardo);
    const etiquetaRetiro = etiquetaRetiroResguardo(resguardo);

    const previews = useMemo(() => evidencias.map((archivo) => ({
        archivo,
        url: URL.createObjectURL(archivo),
    })), [evidencias]);

    const actualizarBulto = (indice, campo, valor) => {
        setBultos((prev) => prev.map((bulto, i) => (i === indice ? { ...bulto, [campo]: valor } : bulto)));
    };

    const validar = () => {
        const errores = {};
        if (!almacenId) {
            errores.almacen_id = 'Selecciona el almacén de custodia.';
        }
        bultos.forEach((bulto, indice) => {
            if (!String(bulto.condicion || '').trim()) {
                errores[`bultos.${indice}.condicion`] = 'Indica la condición del bulto.';
            }
            const piezas = Number(bulto.piezas);
            if (!Number.isFinite(piezas) || piezas < 1) {
                errores[`bultos.${indice}.piezas`] = 'Las piezas deben ser al menos 1.';
            }
        });
        return errores;
    };

    const solicitarConfirmacion = (e) => {
        e.preventDefault();
        const errores = validar();
        setErroresLocales(errores);
        if (Object.keys(errores).length > 0) return;
        setConfirmar(true);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        await onEnviar({
            almacenId: Number(almacenId),
            bultos,
            evidencias,
        });
    };

    const agregarEvidencias = (archivos) => {
        const imagenes = Array.from(archivos || []).filter((f) => f.type.startsWith('image/'));
        if (imagenes.length === 0) return;
        setEvidencias((prev) => [...prev, ...imagenes]);
    };

    const quitarEvidencia = (indice) => {
        setEvidencias((prev) => prev.filter((_, i) => i !== indice));
    };

    return (
        <form onSubmit={solicitarConfirmacion} className="space-y-6">
            <div className={`${geliaCardClass()} p-5 space-y-3`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Datos del resguardo</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Cliente</p>
                        <p className="font-bold theme-text-main m-0 mt-1">{titular}</p>
                    </div>
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Quién retira</p>
                        <p className="font-bold theme-text-main m-0 mt-1">{etiquetaRetiro}</p>
                    </div>
                </div>
            </div>

            <PanelPedidoRevisionResguardo resguardo={resguardo} className="!p-4 sm:!p-5" />

            {(resguardo.bultos_empaque_cedis?.length ?? 0) > 0 && (
                <div className={`${geliaCardClass()} p-5`}>
                    <ChipEvidenciasBultosEmpaque
                        bultos={resguardo.bultos_empaque_cedis}
                        folio={resguardo.snapshot_folio}
                    />
                </div>
            )}

            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <label className="space-y-1.5 block">
                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Almacén de custodia</span>
                    <select
                        value={almacenId}
                        onChange={(e) => setAlmacenId(e.target.value)}
                        className={THEME_SELECT}
                        required
                        disabled={enviando}
                    >
                        <option value="">Seleccionar…</option>
                        {almacenes.map((almacen) => (
                            <option key={almacen.id} value={almacen.id}>
                                {almacen.codigo} — {almacen.nombre}
                            </option>
                        ))}
                    </select>
                    {erroresLocales.almacen_id && (
                        <p className="text-xs text-red-600 m-0">{erroresLocales.almacen_id}</p>
                    )}
                </label>
            </div>

            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Revisión de bultos</h2>
                <p className="text-sm theme-text-muted m-0">
                    Verifica los datos prellenados desde CEDIS. Ajusta condición y piezas si es necesario.
                </p>
                <div className="space-y-4">
                    {bultos.map((bulto, indice) => (
                        <div key={bulto.key} className="rounded-2xl border theme-border p-4 space-y-3">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Bulto {indice + 1}: {bulto.folio}
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label className="space-y-1.5">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo</span>
                                    <select
                                        value={bulto.tipo}
                                        onChange={(e) => actualizarBulto(indice, 'tipo', e.target.value)}
                                        className={THEME_SELECT}
                                        disabled={enviando}
                                    >
                                        {Object.entries(tiposBulto).map(([valor, etiqueta]) => (
                                            <option key={valor} value={valor}>{etiqueta}</option>
                                        ))}
                                    </select>
                                </label>
                                <label className="space-y-1.5">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Condición</span>
                                    <select
                                        value={bulto.condicion}
                                        onChange={(e) => actualizarBulto(indice, 'condicion', e.target.value)}
                                        className={THEME_SELECT}
                                        disabled={enviando}
                                    >
                                        {Object.entries(condiciones).map(([valor, etiqueta]) => (
                                            <option key={valor} value={valor}>{etiqueta}</option>
                                        ))}
                                    </select>
                                </label>
                                <label className="space-y-1.5">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Piezas</span>
                                    <input
                                        type="number"
                                        min={1}
                                        value={bulto.piezas}
                                        onChange={(e) => actualizarBulto(indice, 'piezas', e.target.value)}
                                        className={THEME_INPUT}
                                        required
                                        disabled={enviando}
                                    />
                                </label>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Evidencia fotográfica (opcional)</h2>
                <BotonesCapturaEvidencia onAgregar={agregarEvidencias} deshabilitado={enviando} />
                {previews.length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        {previews.map((item, indice) => (
                            <div key={`${item.archivo.name}-${indice}`} className="relative rounded-2xl overflow-hidden border theme-border">
                                <img src={item.url} alt={`Evidencia ${indice + 1}`} className="w-full h-28 object-cover" />
                                <button
                                    type="button"
                                    onClick={() => quitarEvidencia(indice)}
                                    className="absolute top-2 right-2 px-2 py-1 rounded-lg bg-black/60 text-white text-[10px] font-black uppercase"
                                    disabled={enviando}
                                >
                                    Quitar
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <button
                type="submit"
                disabled={enviando || bultos.length === 0}
                className={`${THEME_BTN_PRIMARY} w-full min-h-[48px] inline-flex items-center justify-center gap-2`}
            >
                {enviando ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                Confirmar custodia
            </button>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar custodia"
                mensaje={`Se registrarán ${bultos.length} bulto(s) en custodia tras la revisión.`}
                etiquetaConfirmar="Sí, confirmar custodia"
                variante="primary"
                onClose={() => setConfirmar(false)}
                onConfirm={confirmarEnvio}
            />
        </form>
    );
}
