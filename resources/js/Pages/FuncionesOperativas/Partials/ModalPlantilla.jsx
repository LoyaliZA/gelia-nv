import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Share2, Check, Palette, Mail, UserPlus, Trash2 } from 'lucide-react';

export default function ModalPlantilla({
    show,
    data,
    setData,
    onClose,
    onSave,
    usuarios_sistema,
    ICONS_MAP,
    COLUMNAS_DISPONIBLES,
}) {
    const GELIA_PALETTE = {
        rosa: '#ec4899',
        azul: '#3b82f6',
        verde: '#10b981',
        amarillo: '#f59e0b',
        purple: '#a855f7',
        indigo: '#6366f1',
        orange: '#f97316',
    };

    const [busquedaDest, setBusquedaDest] = useState('');
    const [nuevoExterno, setNuevoExterno] = useState({ nombre: '', email: '' });

    useEffect(() => {
        if (show) {
            document.body.style.overflow = 'hidden';
            setBusquedaDest('');
            setNuevoExterno({ nombre: '', email: '' });
        } else {
            document.body.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
        };
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
            ? actuales.filter((a) => a !== archivo)
            : [...actuales, archivo];
        setData('archivos_requeridos', nuevas);
    };

    const toggleColumna = (columna) => {
        const actuales = data.columnas_exportar;
        const nuevas = actuales.includes(columna)
            ? actuales.filter((c) => c !== columna)
            : [...actuales, columna];
        setData('columnas_exportar', nuevas);
    };

    const toggleSharedUser = (userId) => {
        const actuales = data.shared_users || [];
        const nuevas = actuales.includes(userId)
            ? actuales.filter((id) => id !== userId)
            : [...actuales, userId];
        setData('shared_users', nuevas);
    };

    const toggleDestinatarioUser = (userId) => {
        const actuales = data.destinatarios_user_ids || [];
        const nuevas = actuales.includes(userId)
            ? actuales.filter((id) => id !== userId)
            : [...actuales, userId];
        setData('destinatarios_user_ids', nuevas);
    };

    const agregarExterno = () => {
        const email = nuevoExterno.email.trim().toLowerCase();
        const nombre = nuevoExterno.nombre.trim();
        if (!email || !email.includes('@')) return;
        const actuales = data.destinatarios_externos || [];
        if (actuales.some((e) => e.email.toLowerCase() === email)) return;
        setData('destinatarios_externos', [...actuales, { nombre: nombre || email, email }]);
        setNuevoExterno({ nombre: '', email: '' });
    };

    const activeColorHex = GELIA_PALETTE[data.color] || GELIA_PALETTE.azul;
    const destinatariosUserIds = data.destinatarios_user_ids || [];
    const destinatariosExternos = data.destinatarios_externos || [];
    const filteredDestUsers = (usuarios_sistema || []).filter(
        (u) =>
            u.name?.toLowerCase().includes(busquedaDest.toLowerCase()) ||
            u.email?.toLowerCase().includes(busquedaDest.toLowerCase())
    );

    return createPortal(
        <div
            className="fixed inset-0 z-[150] flex items-start md:items-center justify-center bg-black/70 backdrop-blur-xl p-4 pt-20 md:pt-4 animate-fade-in"
            onClick={onClose}
        >
            <form
                onSubmit={onSave}
                className="theme-surface border border-zinc-200 dark:border-zinc-700 w-full max-w-5xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col max-h-[85vh]"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-8 border-b theme-border flex justify-between items-center theme-element shrink-0">
                    <h2 className="text-lg font-black uppercase tracking-widest theme-text-main italic flex items-center gap-3">
                        <Palette className="w-6 h-6" style={{ color: activeColorHex }} />
                        {data.id ? 'Modificar Plantilla_' : 'Nueva Plantilla_'}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"
                    >
                        <X className="w-6 h-6" />
                    </button>
                </div>

                <div className="p-6 md:p-8 overflow-y-auto custom-scrollbar flex-1 grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-10">
                    <div className="space-y-8">
                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">
                                Título de Lista
                            </label>
                            <input
                                type="text"
                                required
                                value={data.titulo_lista}
                                onChange={(e) => setData('titulo_lista', e.target.value)}
                                className="w-full theme-surface border theme-border rounded-2xl p-4 theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm"
                                style={{ '--tw-ring-color': activeColorHex }}
                                placeholder="Ej: Reporte Operativo Mensual"
                            />
                        </div>

                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">
                                Color de Identidad
                            </label>
                            <div className="flex flex-wrap gap-3 p-1">
                                {Object.entries(GELIA_PALETTE).map(([name, hex]) => (
                                    <button
                                        key={name}
                                        type="button"
                                        onClick={() => setData('color', name)}
                                        className={`w-10 h-10 rounded-full border-4 transition-all hover:scale-110 shadow-sm ${
                                            data.color === name
                                                ? 'border-white dark:border-zinc-500 scale-110 ring-2 ring-offset-2 dark:ring-offset-zinc-900'
                                                : 'border-transparent'
                                        }`}
                                        style={{ backgroundColor: hex, '--tw-ring-color': hex }}
                                        title={name}
                                    />
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">
                                Icono Representativo
                            </label>
                            <div className="grid grid-cols-4 gap-3">
                                {Object.keys(ICONS_MAP)
                                    .filter((k) => !Object.keys(GELIA_PALETTE).includes(k))
                                    .map((iconName) => {
                                        const IconComponent = ICONS_MAP[iconName];
                                        const isSelected = data.icono_personalizado === iconName;
                                        return (
                                            <button
                                                key={iconName}
                                                type="button"
                                                onClick={() => setData('icono_personalizado', iconName)}
                                                className={`p-4 border-[1.5px] rounded-2xl flex items-center justify-center transition-all outline-none ${
                                                    isSelected
                                                        ? 'shadow-md scale-105'
                                                        : 'theme-element theme-border theme-text-muted hover:theme-text-main hover:bg-black/5'
                                                }`}
                                                style={{
                                                    borderColor: isSelected ? activeColorHex : '',
                                                    backgroundColor: isSelected
                                                        ? `color-mix(in srgb, ${activeColorHex} 15%, transparent)`
                                                        : '',
                                                }}
                                            >
                                                <IconComponent
                                                    className="w-6 h-6"
                                                    style={{ color: isSelected ? activeColorHex : '' }}
                                                />
                                            </button>
                                        );
                                    })}
                            </div>
                        </div>

                        <div>
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">
                                Sufijo de Exportación Excel
                            </label>
                            <div className="flex items-center">
                                <input
                                    type="text"
                                    required
                                    value={data.nombre_archivo_salida}
                                    onChange={handleFilenameChange}
                                    className="w-full theme-surface border theme-border border-r-0 rounded-l-2xl p-4 theme-text-main text-sm font-bold outline-none uppercase focus:ring-2 transition-all font-mono shadow-sm"
                                    style={{ '--tw-ring-color': activeColorHex }}
                                    placeholder="EJ-MI-REPORTE"
                                />
                                <span className="theme-element border theme-border rounded-r-2xl p-4 text-[11px] font-black theme-text-muted uppercase tracking-widest shadow-sm">
                                    -[FECHA].xlsx
                                </span>
                            </div>
                        </div>

                        <div className="p-6 theme-surface rounded-[2rem] border theme-border space-y-4 shadow-sm">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest">
                                Archivos Requeridos
                            </h4>
                            <div className="space-y-3">
                                <label className="flex items-center gap-3 cursor-not-allowed opacity-80">
                                    <input
                                        type="checkbox"
                                        checked={true}
                                        disabled
                                        className="w-5 h-5 rounded"
                                        style={{ accentColor: activeColorHex }}
                                    />
                                    <span className="text-xs theme-text-main font-bold">Existencias</span>
                                </label>
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <input
                                        type="checkbox"
                                        checked={data.archivos_requeridos.includes('precios')}
                                        onChange={() => toggleArchivo('precios')}
                                        className="w-5 h-5 rounded"
                                        style={{ accentColor: '#10b981' }}
                                    />
                                    <span className="text-xs theme-text-main group-hover:text-emerald-500 font-bold transition-colors">
                                        Precios
                                    </span>
                                </label>
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <input
                                        type="checkbox"
                                        checked={data.archivos_requeridos.includes('costos')}
                                        onChange={() => toggleArchivo('costos')}
                                        className="w-5 h-5 rounded"
                                        style={{ accentColor: '#a855f7' }}
                                    />
                                    <span className="text-xs theme-text-main group-hover:text-purple-500 font-bold transition-colors">
                                        Costos
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-8 flex flex-col h-full">
                        <div className="flex-1 min-h-[300px]">
                            <label className="block text-[10px] font-black theme-text-muted mb-3 uppercase tracking-widest">
                                Mapeo de Columnas (Orden Requerido)
                            </label>
                            <div className="grid grid-cols-2 gap-3 max-h-[350px] overflow-y-auto custom-scrollbar pr-2">
                                {COLUMNAS_DISPONIBLES.map((col) => {
                                    const index = data.columnas_exportar.indexOf(col);
                                    const isSelected = index !== -1;
                                    return (
                                        <label
                                            key={col}
                                            className={`relative flex items-center gap-3 p-3 rounded-xl border-[1.5px] cursor-pointer select-none transition-all ${
                                                isSelected
                                                    ? 'theme-surface shadow-sm'
                                                    : 'theme-element theme-border hover:border-zinc-400 dark:hover:border-zinc-500'
                                            }`}
                                            style={{ borderColor: isSelected ? activeColorHex : '' }}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => toggleColumna(col)}
                                                className="w-4 h-4 rounded shrink-0"
                                                style={{ accentColor: activeColorHex }}
                                            />
                                            <span
                                                className={`text-[10px] font-black uppercase tracking-widest truncate pr-6 ${
                                                    isSelected ? 'theme-text-main' : 'theme-text-muted'
                                                }`}
                                            >
                                                {col}
                                            </span>
                                            {isSelected && (
                                                <span
                                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-white text-[9px] font-black w-5 h-5 rounded-full flex items-center justify-center shadow-md"
                                                    style={{ backgroundColor: activeColorHex }}
                                                >
                                                    {index + 1}
                                                </span>
                                            )}
                                        </label>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="p-6 theme-surface rounded-[2rem] border theme-border space-y-5 shadow-sm">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest">
                                Reglas de Exclusión
                            </h4>
                            <label className="flex items-center gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    checked={data.solo_con_existencia}
                                    onChange={(e) => setData('solo_con_existencia', e.target.checked)}
                                    className="w-5 h-5 rounded shrink-0"
                                    style={{ accentColor: '#f97316' }}
                                />
                                <span className="text-xs theme-text-main group-hover:text-orange-500 font-black uppercase tracking-widest transition-colors">
                                    Omitir Existencias en Cero
                                </span>
                            </label>
                            <label className="flex items-center gap-3 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    checked={data.filtro_relojes}
                                    onChange={(e) => setData('filtro_relojes', e.target.checked)}
                                    className="w-5 h-5 rounded shrink-0"
                                    style={{ accentColor: '#a855f7' }}
                                />
                                <span className="text-xs theme-text-main group-hover:text-purple-500 font-black uppercase tracking-widest transition-colors">
                                    Solo Filtro de Relojes ('R')
                                </span>
                            </label>
                        </div>

                        <div className="p-6 theme-element rounded-[2rem] border theme-border space-y-4">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest flex items-center gap-2">
                                <Share2 className="w-4 h-4" style={{ color: activeColorHex }} /> Acceso
                                Compartido
                            </h4>
                            <div className="max-h-32 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 custom-scrollbar pr-2">
                                {usuarios_sistema.map((u) => (
                                    <label
                                        key={u.id}
                                        className="flex items-center gap-3 cursor-pointer theme-surface p-3 rounded-xl border border-transparent hover:border-zinc-300 dark:hover:border-white/10 transition-all shadow-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={(data.shared_users || []).includes(u.id)}
                                            onChange={() => toggleSharedUser(u.id)}
                                            className="w-4 h-4 rounded shrink-0"
                                            style={{ accentColor: activeColorHex }}
                                        />
                                        <span className="text-[11px] theme-text-main font-bold uppercase truncate">
                                            {u.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div className="p-6 theme-surface rounded-[2rem] border theme-border space-y-4 shadow-sm">
                            <h4 className="text-[11px] font-black theme-text-main uppercase tracking-widest flex items-center gap-2">
                                <Mail className="w-4 h-4" style={{ color: activeColorHex }} /> Destinatarios
                                de Entrega (opcional)
                            </h4>
                            <p className="text-[10px] theme-text-muted font-bold">
                                Se precargan al generar y compartir esta plantilla. Independiente del acceso
                                compartido.
                            </p>
                            <input
                                type="text"
                                placeholder="Buscar usuario..."
                                value={busquedaDest}
                                onChange={(e) => setBusquedaDest(e.target.value)}
                                className="w-full px-3 py-2 text-xs theme-surface border theme-border rounded-xl theme-text-main outline-none"
                            />
                            <div className="max-h-32 overflow-y-auto space-y-2 custom-scrollbar pr-2">
                                {filteredDestUsers.map((u) => (
                                    <label
                                        key={`dest-${u.id}`}
                                        className="flex items-center gap-3 cursor-pointer theme-element p-3 rounded-xl border border-transparent hover:border-zinc-300 dark:hover:border-white/10"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={destinatariosUserIds.includes(u.id)}
                                            onChange={() => toggleDestinatarioUser(u.id)}
                                            className="w-4 h-4 rounded shrink-0"
                                            style={{ accentColor: activeColorHex }}
                                        />
                                        <span className="text-[11px] theme-text-main font-bold truncate">
                                            {u.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <input
                                    type="text"
                                    placeholder="Nombre externo"
                                    value={nuevoExterno.nombre}
                                    onChange={(e) =>
                                        setNuevoExterno((p) => ({ ...p, nombre: e.target.value }))
                                    }
                                    className="px-3 py-2 text-xs theme-surface border theme-border rounded-xl theme-text-main outline-none"
                                />
                                <input
                                    type="email"
                                    placeholder="correo@ejemplo.com"
                                    value={nuevoExterno.email}
                                    onChange={(e) =>
                                        setNuevoExterno((p) => ({ ...p, email: e.target.value }))
                                    }
                                    className="px-3 py-2 text-xs theme-surface border theme-border rounded-xl theme-text-main outline-none"
                                />
                            </div>
                            <button
                                type="button"
                                onClick={agregarExterno}
                                className="text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-xl text-white flex items-center gap-2"
                                style={{ backgroundColor: activeColorHex }}
                            >
                                <UserPlus className="w-3 h-3" /> Agregar externo
                            </button>
                            {destinatariosExternos.map((ext) => (
                                <div
                                    key={ext.email}
                                    className="flex items-center justify-between gap-2 p-2 rounded-xl theme-element border theme-border"
                                >
                                    <div className="min-w-0">
                                        <p className="text-[11px] font-bold theme-text-main truncate">
                                            {ext.nombre}
                                        </p>
                                        <p className="text-[10px] theme-text-muted truncate">{ext.email}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'destinatarios_externos',
                                                destinatariosExternos.filter((e) => e.email !== ext.email)
                                            )
                                        }
                                        className="p-1 text-red-500 outline-none"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="p-6 md:p-8 border-t theme-border theme-element flex justify-end gap-4 items-center shrink-0">
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-[11px] font-black uppercase tracking-widest px-6 py-4 theme-text-muted hover:theme-text-main transition-colors outline-none rounded-2xl hover:bg-black/5 dark:hover:bg-white/5"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        className="text-[11px] font-black uppercase tracking-widest px-8 py-4 text-white rounded-2xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all outline-none flex items-center gap-2"
                        style={{ backgroundColor: activeColorHex }}
                    >
                        <Check className="w-4 h-4 shrink-0" /> Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>,
        document.body
    );
}
