import React, { useState, useEffect, useCallback } from 'react';
import { useForm } from '@inertiajs/react';
import { Layers, Trash2, Palette, Edit2 } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    INPUT_CLASS,
    THEME_BTN_PRIMARY,
    BTN_ICON_DANGER,
    EstadoVacio,
    ModalPersonalizacion,
    ModalConfirmarEliminar,
    CampoFormulario,
    OpcionBinaria,
} from './personalizacionShared';

const FUENTES = ['inter', 'montserrat', 'poppins', 'nunito', 'roboto', 'mono'];
const ACCENT_COLORS = { rosa: '#ec4899', azul: '#3b82f6', verde: '#10b981', amarillo: '#f59e0b' };
const LAYOUTS = [
    { id: 'floating_left', label: 'Flotante izquierda' },
    { id: 'floating_right', label: 'Flotante derecha' },
    { id: 'fixed', label: 'Barra fija' },
];

export default function TablaTemas({ catalogo = {}, fondos_opciones = [], registrarAbrir }) {
    const datos = catalogo?.data ?? [];
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const fondoOpciones = fondos_opciones.length > 0
        ? fondos_opciones
        : ['blob', 'stacked', 'polygon', 'wave'].map((v) => ({ value: v, label: v }));

    const formDefaults = {
        nombre: '',
        slug: '',
        modo: 'dark',
        color_nombre: 'rosa',
        color_hex: '#ec4899',
        fondo_base: fondoOpciones[0]?.value || 'blob',
        fuente_principal: 'inter',
        escala_fuente: 1,
        layout_sidebar: 'floating_left',
        efecto_cristal: true,
        sonido: true,
        activo: true,
        orden: 0,
    };

    const { data, setData, post, put, processing, reset } = useForm(formDefaults);

    const abrirNuevo = useCallback(() => {
        setItemActual(null);
        reset();
        setData(formDefaults);
        setModalAbierto(true);
    }, [reset, setData]);

    useEffect(() => {
        if (!registrarAbrir) return undefined;
        registrarAbrir.current = abrirNuevo;
        return () => {
            if (registrarAbrir.current === abrirNuevo) {
                registrarAbrir.current = null;
            }
        };
    }, [registrarAbrir, abrirNuevo]);

    const abrirEditar = (item) => {
        const cfg = item.configuracion || {};
        setItemActual(item);
        setData({
            nombre: item.name || item.nombre,
            slug: item.slug,
            modo: cfg.modo || item.modo || 'dark',
            color_nombre: cfg.color_nombre || item.colorNombre || 'rosa',
            color_hex: cfg.color_hex || item.colorHex || '#ec4899',
            fondo_base: cfg.fondo_base || item.bg || 'blob',
            fuente_principal: cfg.fuente_principal || item.font || 'inter',
            escala_fuente: cfg.escala_fuente ?? item.escala ?? 1,
            layout_sidebar: cfg.layout_sidebar || item.layout || 'floating_left',
            efecto_cristal: cfg.efecto_cristal ?? item.glass ?? true,
            sonido: cfg.sonido ?? item.sound ?? true,
            activo: item.activo ?? true,
            orden: item.orden ?? 0,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: () => { setModalAbierto(false); reset(); } };
        if (itemActual) {
            put(route('admin.personalizacion.temas.update', itemActual.id), opts);
        } else {
            post(route('admin.personalizacion.temas.store'), opts);
        }
    };

    const confirmDelete = () => {
        post(route('admin.personalizacion.temas.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const accentHexActual = () => {
        if (data.color_hex?.startsWith('#')) return data.color_hex;
        return ACCENT_COLORS[data.color_nombre] || ACCENT_COLORS.rosa;
    };

    const esAcentoSeleccionado = (name, hex) =>
        data.color_nombre === name || (data.color_hex || '').toLowerCase() === hex.toLowerCase();

    const handleAccentPick = (nameOrHex, isCustom = false) => {
        if (isCustom || String(nameOrHex).startsWith('#')) {
            const hex = String(nameOrHex).startsWith('#') ? nameOrHex : `#${nameOrHex}`;
            const matched = Object.entries(ACCENT_COLORS).find(([, h]) => h.toLowerCase() === hex.toLowerCase());
            setData({
                ...data,
                color_hex: hex,
                color_nombre: matched ? matched[0] : hex,
            });
            return;
        }
        setData({
            ...data,
            color_nombre: nameOrHex,
            color_hex: ACCENT_COLORS[nameOrHex] || data.color_hex,
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando tema_" />

            {datos.length === 0 ? (
                <EstadoVacio
                    icon={Layers}
                    titulo="Sin temas en catálogo"
                    mensaje="Crea plantillas visuales que los colaboradores podrán aplicar desde su perfil."
                    accionLabel="Nuevo tema"
                    onAccion={abrirNuevo}
                />
            ) : (
                <div className="p-4 md:p-8 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
                    {datos.map((item) => {
                        const hex = item.colorHex || '#ec4899';
                        const isDark = item.modo === 'dark';
                        const isInactive = item.activo === false;

                        return (
                            <div
                                key={item.id}
                                className={`relative p-5 md:p-6 rounded-[1.75rem] md:rounded-[2rem] flex flex-col items-start gap-4 border-2 transition-all hover:shadow-xl ${isInactive ? 'opacity-60' : 'hover:-translate-y-0.5'}`}
                                style={{
                                    backgroundColor: isDark ? '#111113' : '#f8f9fa',
                                    borderColor: `${hex}55`,
                                }}
                            >
                                <div
                                    className="absolute -top-6 -right-6 w-24 h-24 rounded-full opacity-20 group-hover:opacity-40 transition-opacity blur-2xl"
                                    style={{ backgroundColor: hex }}
                                />
                                <div className="w-full flex justify-between items-center relative z-10">
                                    <div
                                        className="w-7 h-7 rounded-full shadow-lg group-hover:scale-110 transition-transform ring-2 ring-white/20"
                                        style={{ backgroundColor: hex }}
                                    />
                                    <span
                                        className="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-full"
                                        style={{ backgroundColor: `${hex}22`, color: hex }}
                                    >
                                        {item.modo}
                                    </span>
                                </div>
                                <div className="relative z-10 w-full min-w-0">
                                    <span
                                        className="text-sm font-black block mb-1 truncate"
                                        style={{ color: isDark ? '#ffffff' : '#111113' }}
                                    >
                                        {item.name || item.nombre}
                                    </span>
                                    <span
                                        className="text-[11px] font-bold block truncate"
                                        style={{ color: isDark ? 'rgba(255,255,255,0.5)' : 'rgba(0,0,0,0.45)' }}
                                    >
                                        {item.slug}
                                    </span>
                                    {isInactive && (
                                        <span className="inline-flex mt-2 items-center px-2 py-0.5 rounded-full text-[8px] font-black uppercase bg-red-500/20 text-red-500 dark:text-red-400">
                                            Inactivo en perfil
                                        </span>
                                    )}
                                </div>
                                <div className="relative z-10 w-full flex gap-2 pt-3 mt-auto border-t border-black/10 dark:border-white/10">
                                    <button
                                        type="button"
                                        onClick={() => abrirEditar(item)}
                                        className="flex-1 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest theme-btn-secondary border theme-border flex items-center justify-center gap-1.5"
                                    >
                                        <Edit2 className="w-3.5 h-3.5" /> Editar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setItemActual(item); setModalEliminar(true); }}
                                        className={`${BTN_ICON_DANGER} shrink-0`}
                                    >
                                        <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white transition-colors" />
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <ModalPersonalizacion
                abierto={modalAbierto}
                onClose={() => setModalAbierto(false)}
                titulo={itemActual ? 'Editar tema' : 'Nuevo tema'}
                tamano="max-w-2xl"
            >
                <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-8 space-y-6">
                        <div className="theme-form-zone grid grid-cols-1 sm:grid-cols-2 gap-5 md:gap-6 p-5 md:p-6">
                            <CampoFormulario label="Nombre del tema" className="sm:col-span-2">
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={INPUT_CLASS} required />
                            </CampoFormulario>
                            <CampoFormulario label="Modo">
                                <OpcionBinaria
                                    value={data.modo}
                                    onChange={(v) => setData('modo', v)}
                                    opciones={[
                                        { value: 'light', label: 'Claro' },
                                        { value: 'dark', label: 'Oscuro' },
                                    ]}
                                />
                            </CampoFormulario>
                            <CampoFormulario label="Layout sidebar">
                                <select value={data.layout_sidebar} onChange={(e) => setData('layout_sidebar', e.target.value)} className={INPUT_CLASS}>
                                    {LAYOUTS.map((l) => <option key={l.id} value={l.id}>{l.label}</option>)}
                                </select>
                            </CampoFormulario>
                            <CampoFormulario label="Color de énfasis" className="sm:col-span-2">
                                    <div className="flex flex-wrap gap-4 items-center p-4 rounded-2xl theme-element border theme-border">
                                        {Object.entries(ACCENT_COLORS).map(([name, hex]) => (
                                            <button
                                                key={name}
                                                type="button"
                                                onClick={() => handleAccentPick(name)}
                                                className={`w-8 h-8 sm:w-10 sm:h-10 rounded-full transition-all outline-none ${esAcentoSeleccionado(name, hex) ? 'ring-4 ring-offset-4 dark:ring-offset-[#141414] shadow-lg scale-110' : 'opacity-40 hover:opacity-100 hover:scale-110 hover:shadow-md'}`}
                                                style={{ backgroundColor: hex, '--tw-ring-color': hex }}
                                                title={name}
                                            />
                                        ))}
                                        <div className="w-[2px] h-8 theme-border border-l mx-2 rounded-full shrink-0" />
                                        <label className="relative w-10 h-10 rounded-full border-2 border-dashed theme-border flex items-center justify-center cursor-pointer hover:scale-110 hover:shadow-md hover:border-[var(--color-primario)] transition-all overflow-hidden theme-element" title="Elegir color personalizado">
                                            <Palette className="w-4 h-4 theme-text-main z-10 pointer-events-none" />
                                            <input
                                                type="color"
                                                value={accentHexActual()}
                                                className="absolute inset-0 opacity-0 cursor-pointer w-full h-full z-20"
                                                onChange={(e) => handleAccentPick(e.target.value, true)}
                                            />
                                        </label>
                                        <span className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-auto tabular-nums">
                                            {accentHexActual()}
                                        </span>
                                    </div>
                            </CampoFormulario>
                            <CampoFormulario label="Fondo base">
                                <select value={data.fondo_base} onChange={(e) => setData('fondo_base', e.target.value)} className={INPUT_CLASS}>
                                    {fondoOpciones.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                                </select>
                            </CampoFormulario>
                            <CampoFormulario label="Tipografía">
                                <select value={data.fuente_principal} onChange={(e) => setData('fuente_principal', e.target.value)} className={INPUT_CLASS}>
                                    {FUENTES.map((f) => <option key={f} value={f}>{f}</option>)}
                                </select>
                            </CampoFormulario>
                            <CampoFormulario label="Escala fuente">
                                <input type="number" step="0.0625" min="0.875" max="1.5" value={data.escala_fuente} onChange={(e) => setData('escala_fuente', Number(e.target.value))} className={INPUT_CLASS} />
                            </CampoFormulario>
                            <CampoFormulario label="Orden">
                                <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', Number(e.target.value))} className={INPUT_CLASS} />
                            </CampoFormulario>
                        </div>

                        <div className="flex flex-col sm:flex-row flex-wrap gap-4 sm:gap-6 px-1">
                                <div className="flex items-center gap-3">
                                    <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.efecto_cristal} onClick={() => setData('efecto_cristal', !data.efecto_cristal)}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                    <span className="text-[10px] font-black uppercase theme-text-muted">Efecto cristal</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.sonido} onClick={() => setData('sonido', !data.sonido)}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                    <span className="text-[10px] font-black uppercase theme-text-muted">Sonido alertas</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.activo} onClick={() => setData('activo', !data.activo)}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                    <span className="text-[10px] font-black uppercase theme-text-muted">Activo en perfil</span>
                                </div>
                        </div>
                    </div>
                    <div className="gelia-modal-footer p-5 md:p-8">
                        <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full`}>
                            {processing ? 'Guardando...' : 'Guardar tema'}
                        </button>
                    </div>
                </form>
            </ModalPersonalizacion>

            <ModalConfirmarEliminar
                abierto={modalEliminar}
                onClose={() => setModalEliminar(false)}
                onConfirm={confirmDelete}
                titulo="Eliminar tema"
                mensaje={`¿Eliminar el tema «${itemActual?.name || itemActual?.nombre}»? Los usuarios que lo tengan aplicado conservarán su configuración local hasta que cambien de tema.`}
            />
        </div>
    );
}
