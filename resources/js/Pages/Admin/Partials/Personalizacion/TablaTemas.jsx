import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Layers, Trash2, Plus, X, Save, AlertTriangle, Palette, Edit2 } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

const FUENTES = ['inter', 'montserrat', 'poppins', 'nunito', 'roboto', 'mono'];
const ACCENT_COLORS = { rosa: '#ec4899', azul: '#3b82f6', verde: '#10b981', amarillo: '#f59e0b' };
const LAYOUTS = [
    { id: 'floating_left', label: 'Flotante izquierda' },
    { id: 'floating_right', label: 'Flotante derecha' },
    { id: 'fixed', label: 'Barra fija' },
];

const inputClass =
    'w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md';

export default function TablaTemas({ datos = [], fondos = [], glassEffect = true }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const fondoOpciones = fondos.length > 0
        ? fondos.filter((f) => f.activo !== false).map((f) => ({ value: f.valor, label: f.nombre }))
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

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setData(formDefaults);
        setModalAbierto(true);
    };

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

    const innerZoneClass = glassEffect
        ? 'border border-dashed border-zinc-300 dark:border-zinc-700 theme-element shadow-inner'
        : 'border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-900/60';

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando tema_" />

            <div className="p-8 md:p-10 border-b theme-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Layers className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Temas Predefinidos_
                        </h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1">
                            {datos.length} temas disponibles en perfil
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={abrirNuevo}
                    className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-[11px] tracking-widest transition-all hover:scale-105 shadow-xl text-white outline-none border border-black/10"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Plus className="w-4 h-4" /> Nuevo tema
                </button>
            </div>

            {datos.length === 0 ? (
                <div className="p-12 text-center">
                    <p className="text-sm font-bold theme-text-muted m-0">No hay temas registrados. Crea el primero con el botón superior.</p>
                </div>
            ) : (
                <div className="p-6 md:p-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {datos.map((item) => {
                        const hex = item.colorHex || '#ec4899';
                        const isDark = item.modo === 'dark';
                        const isInactive = item.activo === false;

                        return (
                            <div
                                key={item.id}
                                className={`relative p-6 rounded-[2rem] flex flex-col items-start gap-4 shadow-md group overflow-hidden border-2 transition-all hover:scale-[1.03] hover:shadow-2xl ${isInactive ? 'opacity-60' : ''}`}
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
                                        className="flex-1 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-colors outline-none shadow-sm flex items-center justify-center gap-1.5"
                                    >
                                        <Edit2 className="w-3.5 h-3.5" /> Editar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setItemActual(item); setModalEliminar(true); }}
                                        className="p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:bg-red-500 hover:border-red-500 group/del"
                                    >
                                        <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white transition-colors" />
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-2xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] max-h-[90vh] overflow-y-auto modal-pop" onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center sticky top-0 theme-surface z-10">
                            <h2 className="text-xl font-black italic uppercase theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Tema_</h2>
                            <button type="button" onClick={() => setModalAbierto(false)} className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-8 space-y-6">
                            <div className={`rounded-[1.5rem] p-6 sm:p-8 grid grid-cols-1 sm:grid-cols-2 gap-6 ${innerZoneClass}`}>
                                <div className="sm:col-span-2 space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre del tema</label>
                                    <input
                                        value={data.nombre}
                                        onChange={(e) => setData('nombre', e.target.value)}
                                        className={inputClass}
                                        required
                                        onFocus={(e) => { e.target.style.borderColor = 'var(--color-primario)'; }}
                                        onBlur={(e) => { e.target.style.borderColor = ''; }}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Modo</label>
                                    <div className="gelia-segment w-full p-1 h-12 shadow-sm">
                                        <button
                                            type="button"
                                            className="gelia-segment-btn"
                                            data-active={data.modo === 'light'}
                                            onClick={() => setData('modo', 'light')}
                                        >
                                            Claro
                                        </button>
                                        <button
                                            type="button"
                                            className="gelia-segment-btn"
                                            data-active={data.modo === 'dark'}
                                            onClick={() => setData('modo', 'dark')}
                                        >
                                            Oscuro
                                        </button>
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Layout sidebar</label>
                                    <select
                                        value={data.layout_sidebar}
                                        onChange={(e) => setData('layout_sidebar', e.target.value)}
                                        className={inputClass}
                                    >
                                        {LAYOUTS.map((l) => <option key={l.id} value={l.id}>{l.label}</option>)}
                                    </select>
                                </div>
                                <div className="sm:col-span-2 space-y-3">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Color de énfasis</label>
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
                                        <div className="w-[2px] h-8 bg-zinc-300 dark:bg-zinc-700 mx-2 rounded-full" />
                                        <label className="relative w-10 h-10 rounded-full border-2 border-dashed border-zinc-400 dark:border-zinc-500 flex items-center justify-center cursor-pointer hover:scale-110 hover:shadow-md hover:border-[var(--color-primario)] transition-all overflow-hidden bg-transparent" title="Elegir color personalizado">
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
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Fondo base</label>
                                    <select value={data.fondo_base} onChange={(e) => setData('fondo_base', e.target.value)} className={inputClass}>
                                        {fondoOpciones.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipografía</label>
                                    <select value={data.fuente_principal} onChange={(e) => setData('fuente_principal', e.target.value)} className={inputClass}>
                                        {FUENTES.map((f) => <option key={f} value={f}>{f}</option>)}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Escala fuente</label>
                                    <input type="number" step="0.0625" min="0.875" max="1.5" value={data.escala_fuente} onChange={(e) => setData('escala_fuente', Number(e.target.value))} className={inputClass} />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Orden</label>
                                    <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', Number(e.target.value))} className={inputClass} />
                                </div>
                            </div>

                            <div className="flex flex-col sm:flex-row flex-wrap gap-4 sm:gap-6">
                                <div className="flex items-center gap-3">
                                    <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.efecto_cristal} onClick={() => setData('efecto_cristal', !data.efecto_cristal)}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                    <span className="text-[10px] font-black uppercase theme-text-muted">Desenfoque (blur)</span>
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

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white flex items-center justify-center gap-2 transition-all hover:scale-105 shadow-xl disabled:opacity-60 disabled:cursor-not-allowed disabled:scale-100 outline-none border border-black/10"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-4 h-4" /> Guardar tema
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-8 space-y-6 modal-pop" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-4">
                            <AlertTriangle className="w-8 h-8 text-red-500 shrink-0" />
                            <p className="text-sm theme-text-muted m-0">¿Eliminar el tema «{itemActual?.name || itemActual?.nombre}»?</p>
                        </div>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setModalEliminar(false)}
                                className="flex-1 py-3 rounded-xl font-black uppercase text-xs theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-colors outline-none shadow-sm"
                            >
                                Cancelar
                            </button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 rounded-xl font-black uppercase text-xs text-white bg-red-500 hover:bg-red-600 transition-colors outline-none shadow-sm">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
