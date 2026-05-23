import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X, Share2, Check, AlertTriangle, FileSpreadsheet, Palette } from 'lucide-react';

export default function ModalPlantilla({ 
    show, 
    data, 
    setData, 
    onClose, 
    onSave, 
    usuarios_sistema, 
    ICONS_MAP, 
    COLUMNAS_DISPONIBLES 
}) {
    
    // Paleta oficial de Gelia NV + Colores de acento para reportes
    const GELIA_PALETTE = {
        'rosa':     '#ec4899', // Original Gelia
        'azul':     '#3b82f6', // Corporativo
        'verde':    '#10b981', // Éxito / Inventario
        'amarillo': '#f59e0b', // Alerta / Pendiente
        'purple':   '#a855f7', // Costos / Finanzas
        'indigo':   '#6366f1', // Tecnología
        'orange':   '#f97316', // Logística
    };

    useEffect(() => {
        if (show) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [show]);

    if (!show) return null;

    const handleFilenameChange = (e) => {
        const formatted = e.target.value.toUpperCase().replace(/\s+/g, '-');
        setData('nombre_archivo_salida', formatted);
    };

    const toggleArchivo = (archivo) => {
        if (archivo === 'existencias') return;
        const actuales = data.archivos_requeridos;
        const nuevas = actuales.includes(archivo) 
            ? actuales.filter(a => a !== archivo) 
            : [...actuales, archivo];
        setData('archivos_requeridos', nuevas);
    };

    const toggleColumna = (columna) => {
        const actuales = data.columnas_exportar;
        const nuevas = actuales.includes(columna) ? actuales.filter(c => c !== columna) : [...actuales, columna];
        setData('columnas_exportar', nuevas);
    };

    const toggleSharedUser = (userId) => {
        const actuales = data.shared_users;
        const nuevas = actuales.includes(userId) ? actuales.filter(id => id !== userId) : [...actuales, userId];
        setData('shared_users', nuevas);
    };

    // Obtenemos el color actual seleccionado para la vista previa
    // Si data.color no está en la paleta (porque es un nombre de icono viejo), usamos azul por defecto
    const activeColorHex = GELIA_PALETTE[data.color] || GELIA_PALETTE.azul;

    return createPortal(
        <div className="fixed inset-0 z-[150] flex items-start md:items-center justify-center bg-black/70 backdrop-blur-xl p-4 pt-20 md:pt-4 animate-fade-in" onClick={onClose}>
            <form onSubmit={onSave} className="theme-surface border border-zinc-200 dark:border-zinc-700 w-full max-w-5xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[85vh]" onClick={e => e.stopPropagation()}>
                
                <div className="p-8 border-b theme-border flex justify-between items-center theme-element shrink-0">
                    <h2 className="text-lg font-black uppercase tracking-widest theme-text-main italic flex items-center gap-3">
                        <Palette className="w-6 h-6" style={{ color: activeColorHex }} />
                        {data.id ? 'Modificar Plantilla_' : 'Nueva Plantilla_'}
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"><X className="w-6 h-6" /></button>
                </div>
                
                <div className="p-6 md:p-8 overflow-y-auto custom-scrollbar flex-1 grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-10">
                    
                    {/* COLUMNA IZQUIERDA: General, Color e Icono */}
                    <div className="space-y-8">
                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">Título de Lista</label>
                            <input type="text" required value={data.titulo_lista} onChange={e => setData('titulo_lista', e.target.value)} className="w-full theme-surface border theme-border rounded-2xl p-4 theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm" style={{ '--tw-ring-color': activeColorHex }} placeholder="Ej: Reporte Operativo Mensual" />
                        </div>

                        {/* NUEVO: Selector de Color de Identidad */}
                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">Color de Identidad (Identificador de Celda)</label>
                            <div className="flex flex-wrap gap-3 p-1">
                                {Object.entries(GELIA_PALETTE).map(([name, hex]) => (
                                    <button
                                        key={name}
                                        type="button"
                                        onClick={() => setData('color', name)}
                                        className={`w-10 h-10 rounded-full border-4 transition-all hover:scale-110 shadow-sm ${data.color === name ? 'border-white dark:border-zinc-500 scale-110 ring-2 ring-offset-2 dark:ring-offset-zinc-900' : 'border-transparent'}`}
                                        style={{ backgroundColor: hex, '--tw-ring-color': hex }}
                                        title={name}
                                    />
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">Icono Representativo</label>
                            <div className="grid grid-cols-4 gap-3">
                                {Object.keys(ICONS_MAP).filter(k => !Object.keys(GELIA_PALETTE).includes(k)).map(iconName => {
                                    const IconComponent = ICONS_MAP[iconName];
                                    // Guardamos el icono en un campo temporal o lo manejamos según tu lógica de listados
                                    // Para no romper el controlador, asumiremos que 'color' guarda el color y el icono se deriva o se guarda junto.
                                    const isSelected = data.icono_personalizado === iconName; 
                                    return (
                                        <button 
                                            key={iconName} type="button" onClick={() => setData('icono_personalizado', iconName)}
                                            className={`p-4 border-[1.5px] rounded-2xl flex items-center justify-center transition-all outline-none ${isSelected ? 'shadow-md scale-105' : 'theme-element theme-border theme-text-muted hover:theme-text-main hover:bg-black/5'}`}
                                            style={{ 
                                                borderColor: isSelected ? activeColorHex : '', 
                                                backgroundColor: isSelected ? `color-mix(in srgb, ${activeColorHex} 15%, transparent)` : '' 
                                            }}
                                        >
                                            <IconComponent className="w-6 h-6" style={{ color: isSelected ? activeColorHex : '' }} />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">Sufijo de Exportación Excel</label>
                            <div className="flex items-center">
                                <input type="text" required value={data.nombre_archivo_salida} onChange={handleFilenameChange} className="w-full theme-surface border theme-border border-r-0 rounded-l-2xl p-4 theme-text-main text-sm font-bold outline-none uppercase focus:ring-2 transition-all font-mono shadow-sm" style={{ '--tw-ring-color': activeColorHex }} placeholder="EJ-MI-REPORTE" />
                                <span className="theme-element border theme-border rounded-r-2xl p-4 text-[11px] font-black theme-text-muted uppercase tracking-widest shadow-sm">
                                    -[FECHA].xlsx
                                </span>
                            </div>
                        </div>

                        <div className="p-6 theme-surface rounded-[2rem] border theme-border space-y-4 shadow-sm">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest">Archivos Requeridos</h4>
                            <div className="space-y-3">
                                <label className="flex items-center gap-3 cursor-not-allowed opacity-80">
                                    <input type="checkbox" checked={true} disabled className="w-5 h-5 rounded" style={{ accentColor: activeColorHex }} />
                                    <span className="text-xs theme-text-main font-bold">Existencias <span className="text-[10px] text-zinc-400 uppercase tracking-widest ml-1 hidden sm:inline">(Obligatorio)</span></span>
                                </label>
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" checked={data.archivos_requeridos.includes('precios')} onChange={() => toggleArchivo('precios')} className="w-5 h-5 rounded" style={{ accentColor: '#10b981' }} />
                                    <span className="text-xs theme-text-main group-hover:text-emerald-500 font-bold transition-colors">Precios</span>
                                </label>
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" checked={data.archivos_requeridos.includes('costos')} onChange={() => toggleArchivo('costos')} className="w-5 h-5 rounded" style={{ accentColor: '#a855f7' }} />
                                    <span className="text-xs theme-text-main group-hover:text-purple-500 font-bold transition-colors">Costos</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* COLUMNA DERECHA: Columnas y Reglas */}
                    <div className="space-y-8 flex flex-col h-full">
                        
                        <div className="flex-1 min-h-[300px]">
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">Mapeo de Columnas (Orden Requerido)</label>
                            <div className="grid grid-cols-2 gap-3 max-h-[350px] overflow-y-auto custom-scrollbar pr-2">
                                {COLUMNAS_DISPONIBLES.map(col => {
                                    const index = data.columnas_exportar.indexOf(col);
                                    const isSelected = index !== -1;
                                    return (
                                        <label key={col} className={`relative flex items-center gap-3 p-3 rounded-xl border-[1.5px] cursor-pointer select-none transition-all ${isSelected ? 'theme-surface shadow-sm' : 'theme-element theme-border hover:border-zinc-400 dark:hover:border-zinc-500'}`} style={{ borderColor: isSelected ? activeColorHex : '' }}>
                                            <input type="checkbox" checked={isSelected} onChange={() => toggleColumna(col)} className="w-4 h-4 rounded shrink-0" style={{ accentColor: activeColorHex }} />
                                            <span className={`text-[10px] font-black uppercase tracking-widest truncate pr-6 ${isSelected ? 'theme-text-main' : 'theme-text-muted'}`}>{col}</span>
                                            {isSelected && <span className="absolute right-3 top-1/2 -translate-y-1/2 text-white text-[9px] font-black w-5 h-5 rounded-full flex items-center justify-center shadow-md" style={{ backgroundColor: activeColorHex }}>{index + 1}</span>}
                                        </label>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="p-6 theme-surface rounded-[2rem] border theme-border space-y-5 shadow-sm">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest">Reglas de Exclusión</h4>
                            <label className="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" checked={data.solo_con_existencia} onChange={e => setData('solo_con_existencia', e.target.checked)} className="w-5 h-5 rounded shrink-0" style={{ accentColor: '#f97316' }} />
                                <span className="text-xs theme-text-main group-hover:text-orange-500 font-black uppercase tracking-widest transition-colors">Omitir Existencias en Cero</span>
                            </label>
                            <label className="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" checked={data.filtro_relojes} onChange={e => setData('filtro_relojes', e.target.checked)} className="w-5 h-5 rounded shrink-0" style={{ accentColor: '#a855f7' }} />
                                <span className="text-xs theme-text-main group-hover:text-purple-500 font-black uppercase tracking-widest transition-colors">Solo Filtro de Relojes ('R')</span>
                            </label>
                        </div>

                        <div className="p-6 theme-element rounded-[2rem] border theme-border space-y-4">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest flex items-center gap-2"><Share2 className="w-4 h-4" style={{ color: activeColorHex }} /> Acceso Compartido</h4>
                            <div className="max-h-32 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 custom-scrollbar pr-2">
                                {usuarios_sistema.map(u => (
                                    <label key={u.id} className="flex items-center gap-3 cursor-pointer theme-surface p-3 rounded-xl border border-transparent hover:border-zinc-300 dark:hover:border-white/10 transition-all shadow-sm">
                                        <input type="checkbox" checked={data.shared_users.includes(u.id)} onChange={() => toggleSharedUser(u.id)} className="w-4 h-4 rounded shrink-0" style={{ accentColor: activeColorHex }} />
                                        <span className="text-[11px] theme-text-main font-bold uppercase truncate">{u.name}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="p-6 md:p-8 border-t theme-border theme-element flex justify-end gap-4 items-center shrink-0">
                    <button type="button" onClick={onClose} className="text-[11px] font-black uppercase tracking-widest px-6 py-4 theme-text-muted hover:theme-text-main transition-colors outline-none rounded-2xl hover:bg-black/5 dark:hover:bg-white/5">Cancelar</button>
                    <button type="submit" className="text-[11px] font-black uppercase tracking-widest px-8 py-4 text-white rounded-2xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all outline-none flex items-center gap-2" style={{ backgroundColor: activeColorHex }}>
                        <Check className="w-4 h-4 shrink-0" /> Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>,
        document.body
    );
}