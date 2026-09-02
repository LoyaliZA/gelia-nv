import React, { useMemo, useState } from 'react';
import { Camera, ImagePlus, Loader2, Trash2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { BTN_SECONDARY, THEME_INPUT, THEME_SELECT } from './resguardosStyles';
import { crearBultosVacios } from './recepcionFisicaUtils';

export default function FormularioRecepcionFisica({
    resguardo,
    almacenes = [],
    catalogos = {},
    enviando = false,
    progreso = 0,
    error = null,
    onEnviar,
}) {
    const [almacenId, setAlmacenId] = useState(almacenes.length === 1 ? String(almacenes[0].id) : '');
    const [bultos, setBultos] = useState(() => crearBultosVacios(resguardo.cantidad_bultos_esperada));
    const [evidencias, setEvidencias] = useState([]);
    const [confirmar, setConfirmar] = useState(false);

    const tiposBulto = catalogos.tipos_bulto || {};
    const condiciones = catalogos.condiciones_bulto || {};

    const previews = useMemo(() => evidencias.map((archivo) => ({
        archivo,
        url: URL.createObjectURL(archivo),
    })), [evidencias]);

    const actualizarBulto = (indice, campo, valor) => {
        setBultos((prev) => prev.map((bulto, i) => (i === indice ? { ...bulto, [campo]: valor } : bulto)));
    };

    const agregarEvidencias = (archivos) => {
        const imagenes = Array.from(archivos || []).filter((f) => f.type.startsWith('image/'));
        if (imagenes.length === 0) return;
        setEvidencias((prev) => [...prev, ...imagenes]);
    };

    const quitarEvidencia = (indice) => {
        setEvidencias((prev) => prev.filter((_, i) => i !== indice));
    };

    const solicitarConfirmacion = (e) => {
        e.preventDefault();
        setConfirmar(true);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        await onEnviar({
            almacenId: almacenId ? Number(almacenId) : null,
            bultos,
            evidencias,
            cantidadEsperada: resguardo.cantidad_bultos_esperada,
        });
    };

    return (
        <form onSubmit={solicitarConfirmacion} className="space-y-6">
            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Datos del resguardo</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <CampoSoloLectura label="Folio" value={resguardo.snapshot_folio || `#${resguardo.id}`} />
                    <CampoSoloLectura label="Cliente" value={resguardo.referencia_cliente} />
                    <CampoSoloLectura label="Bultos esperados" value={resguardo.cantidad_bultos_esperada} />
                    {resguardo.pedido?.folio && (
                        <CampoSoloLectura label="Pedido" value={resguardo.pedido.folio} />
                    )}
                </div>
            </div>

            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Ubicación en sucursal</h2>
                <label className="space-y-1.5 block">
                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Almacén de custodia</span>
                    <select
                        value={almacenId}
                        onChange={(e) => setAlmacenId(e.target.value)}
                        className={THEME_SELECT}
                        required
                        disabled={enviando}
                    >
                        <option value="">Seleccionar ubicación…</option>
                        {almacenes.map((almacen) => (
                            <option key={almacen.id} value={almacen.id}>
                                {almacen.codigo} — {almacen.nombre}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            <div className={`${geliaCardClass()} p-5 space-y-4`}>
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos recibidos</h2>
                <div className="space-y-4">
                    {bultos.map((bulto, indice) => (
                        <div key={bulto.key} className="rounded-2xl border theme-border p-4 space-y-3">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Bulto {indice + 1} de {bultos.length}
                            </p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label className="space-y-1.5 sm:col-span-2">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio del bulto</span>
                                    <input
                                        type="text"
                                        value={bulto.folio}
                                        onChange={(e) => actualizarBulto(indice, 'folio', e.target.value)}
                                        className={THEME_INPUT}
                                        maxLength={64}
                                        required
                                        disabled={enviando}
                                        autoComplete="off"
                                    />
                                </label>
                                <label className="space-y-1.5">
                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo</span>
                                    <select
                                        value={bulto.tipo}
                                        onChange={(e) => actualizarBulto(indice, 'tipo', e.target.value)}
                                        className={THEME_SELECT}
                                        required
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
                                        required
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
                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Evidencia fotográfica</h2>
                <div className="flex flex-wrap gap-2">
                    <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 cursor-pointer min-h-[44px]`}>
                        <Camera className="w-4 h-4" />
                        Tomar foto
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="sr-only"
                            disabled={enviando}
                            onChange={(e) => {
                                agregarEvidencias(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </label>
                    <label className={`${BTN_SECONDARY} inline-flex items-center gap-2 cursor-pointer min-h-[44px]`}>
                        <ImagePlus className="w-4 h-4" />
                        Galería
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            className="sr-only"
                            disabled={enviando}
                            onChange={(e) => {
                                agregarEvidencias(e.target.files);
                                e.target.value = '';
                            }}
                        />
                    </label>
                </div>
                {previews.length > 0 && (
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        {previews.map((item, indice) => (
                            <div key={`${item.archivo.name}-${indice}`} className="relative rounded-2xl overflow-hidden border theme-border">
                                <img src={item.url} alt={`Evidencia ${indice + 1}`} className="w-full h-28 object-cover" />
                                <button
                                    type="button"
                                    onClick={() => quitarEvidencia(indice)}
                                    className="absolute top-2 right-2 p-2 rounded-xl bg-black/60 text-white min-h-[44px] min-w-[44px]"
                                    aria-label="Quitar evidencia"
                                    disabled={enviando}
                                >
                                    <Trash2 className="w-4 h-4 mx-auto" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {error && (
                <div className={`${geliaCardClass()} p-4 border border-red-500/30`}>
                    <p className="text-sm font-semibold text-red-600 dark:text-red-300 m-0">{error}</p>
                </div>
            )}

            {enviando && (
                <div className={`${geliaCardClass()} p-4 space-y-2`}>
                    <div className="flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted">
                        <Loader2 className="w-4 h-4 animate-spin" />
                        Enviando recepción… {progreso}%
                    </div>
                    <div className="h-2 rounded-full bg-black/10 dark:bg-white/10 overflow-hidden">
                        <div
                            className="h-full transition-all duration-300"
                            style={{ width: `${progreso}%`, backgroundColor: 'var(--color-primario)' }}
                        />
                    </div>
                </div>
            )}

            <button
                type="submit"
                disabled={enviando}
                className={`${THEME_BTN_PRIMARY} w-full min-h-[48px] text-[10px] font-black uppercase tracking-widest disabled:opacity-50`}
            >
                {enviando ? 'Procesando…' : 'Confirmar recepción física'}
            </button>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar recepción"
                mensaje={`Se registrará la recepción total de ${resguardo.cantidad_bultos_esperada} bulto(s) en custodia. Esta acción no se puede deshacer.`}
                etiquetaConfirmar="Sí, recibir resguardo"
                variante="primary"
                onClose={() => setConfirmar(false)}
                onConfirm={confirmarEnvio}
            />
        </form>
    );
}

function CampoSoloLectura({ label, value }) {
    return (
        <div className="rounded-2xl border theme-border p-3 bg-black/[0.02] dark:bg-white/[0.02]">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-black theme-text-main m-0 mt-1">{value ?? '—'}</p>
        </div>
    );
}
