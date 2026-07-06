import React, { useState, useRef, useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm } from '@inertiajs/react';
import { Users, Info, Plus, X, Mail, Phone, PhoneCall, List, Search, ChevronDown, MoreVertical, Pencil, Trash2, Copy, Check } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { geliaCardClass } from '@/utils/geliaTheme';
import { RhSearchField } from '@/Pages/Rh/Partials/rhFilterFields';

const TIPO_CONFIG = {
    correo: { label: 'Correo', color: '#3b82f6', icon: Mail },
    telefono: { label: 'Teléfono', color: '#22c55e', icon: Phone },
    extension: { label: 'Extensión', color: '#a855f7', icon: PhoneCall },
};

const SearchableAreaSelect = ({ areas, value, onChange, error }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const dropdownRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const filteredAreas = areas.filter(a => a.nombre.toLowerCase().includes(search.toLowerCase()) || (a.departamento?.nombre || '').toLowerCase().includes(search.toLowerCase()));
    
    const areasByDepto = filteredAreas.reduce((acc, area) => {
        const depto = area.departamento?.nombre || 'Sin Departamento';
        if (!acc[depto]) acc[depto] = [];
        acc[depto].push(area);
        return acc;
    }, {});

    const selectedArea = areas.find(a => a.id === value);

    return (
        <div className="relative" ref={dropdownRef}>
            <div 
                onClick={() => setIsOpen(!isOpen)}
                className={`w-full theme-surface border ${error ? 'border-red-500' : 'theme-border'} rounded-lg p-3 theme-text-main text-sm font-bold flex justify-between items-center cursor-pointer transition-all hover:border-[var(--color-primario)]`}
            >
                <span>{selectedArea ? selectedArea.nombre : '-- Seleccionar Área --'}</span>
                <ChevronDown className="w-4 h-4 opacity-50" />
            </div>
            
            {isOpen && (
                <div className="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border theme-border rounded-lg shadow-xl overflow-hidden animate-fade-in">
                    <div className="p-2 border-b theme-border flex items-center gap-2">
                        <Search className="w-4 h-4 theme-text-muted" />
                        <input 
                            type="text" 
                            className="w-full bg-transparent border-none text-sm theme-text-main focus:ring-0 p-1 outline-none" 
                            placeholder="Buscar área o departamento..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            autoFocus
                        />
                    </div>
                    <div className="max-h-60 overflow-y-auto">
                        {Object.keys(areasByDepto).length === 0 ? (
                            <div className="p-3 text-sm theme-text-muted text-center italic">No se encontraron áreas.</div>
                        ) : (
                            Object.entries(areasByDepto).map(([depto, deptoAreas]) => (
                                <div key={depto}>
                                    <div className="px-3 py-1.5 bg-black/5 dark:bg-white/5 text-[10px] font-black uppercase tracking-widest theme-text-muted sticky top-0">
                                        {depto}
                                    </div>
                                    {deptoAreas.map(area => (
                                        <div 
                                            key={area.id}
                                            onClick={() => { onChange(area.id); setIsOpen(false); setSearch(''); }}
                                            className={`px-4 py-2.5 text-sm font-medium cursor-pointer hover:bg-[var(--color-primario)] hover:text-white transition-colors ${value === area.id ? 'bg-[var(--color-primario)]/10 text-[var(--color-primario)] font-bold' : 'theme-text-main'}`}
                                        >
                                            {area.nombre}
                                        </div>
                                    ))}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
            {error && <span className="text-red-500 text-xs font-bold mt-1 block">{error}</span>}
        </div>
    );
};

const DirectorioContactCard = ({ item, onEdit, onDelete, isMenuOpen, onToggleMenu, onCloseMenu }) => {
    const menuRef = useRef(null);
    const [copied, setCopied] = useState(false);
    const config = TIPO_CONFIG[item.tipo];
    const ContactIcon = config.icon;

    useEffect(() => {
        if (!isMenuOpen) return;
        const handleClickOutside = (event) => {
            if (menuRef.current && !menuRef.current.contains(event.target)) {
                onCloseMenu();
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [isMenuOpen, onCloseMenu]);

    const handleCopyDetalle = async (e) => {
        e.stopPropagation();
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(item.detalle);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = item.detalle;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // clipboard no disponible
        }
    };

    return (
        <div className="relative h-full rounded-xl border theme-border theme-surface shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 p-4 md:p-5">
            <div className="absolute top-3 right-3 z-10" ref={menuRef}>
                <button
                    type="button"
                    onClick={onToggleMenu}
                    className="p-1.5 rounded-lg hover:bg-black/5 dark:hover:bg-white/10 theme-text-muted transition-colors"
                    aria-label="Acciones"
                >
                    <MoreVertical className="w-4 h-4" />
                </button>
                {isMenuOpen && (
                    <div className="absolute right-0 top-full mt-1 z-20 min-w-[140px] py-1 rounded-xl border theme-border theme-surface shadow-xl animate-fade-in">
                        <button
                            type="button"
                            onClick={() => { onCloseMenu(); onEdit(item); }}
                            className="w-full flex items-center gap-2 px-4 py-2.5 text-sm font-bold theme-text-main hover:bg-black/5 dark:hover:bg-white/10 transition-colors text-left"
                        >
                            <Pencil className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                            Editar
                        </button>
                        <button
                            type="button"
                            onClick={() => { onCloseMenu(); onDelete(item.idId, item.tipo); }}
                            className="w-full flex items-center gap-2 px-4 py-2.5 text-sm font-bold text-red-500 hover:bg-red-500/10 transition-colors text-left"
                        >
                            <Trash2 className="w-4 h-4" />
                            Eliminar
                        </button>
                    </div>
                )}
            </div>

            <div className="flex items-start gap-3.5 min-w-0 pr-7 pb-6">
                <div
                    className="flex items-center justify-center w-12 h-12 rounded-xl shrink-0"
                    style={{ backgroundColor: `${config.color}12` }}
                >
                    <ContactIcon className="w-10 h-10 shrink-0" style={{ color: config.color }} strokeWidth={1.5} />
                </div>
                <div className="flex-1 min-w-0 pt-0.5">
                    <p className="text-sm font-black theme-text-main leading-snug truncate">
                        {item.nombre}
                    </p>
                    {item.area && (
                        <p className="text-xs font-medium theme-text-muted mt-1 truncate">{item.area}</p>
                    )}
                    <div className="group/detalle flex items-center gap-2 mt-1.5 w-fit max-w-full">
                        <span className="text-sm font-bold theme-text-main break-all leading-snug">
                            {item.detalle}
                        </span>
                        <button
                            type="button"
                            onClick={handleCopyDetalle}
                            title="Copiar"
                            aria-label="Copiar dato de contacto"
                            className={`inline-flex shrink-0 items-center justify-center p-1 rounded-md theme-text-muted hover:text-[var(--color-primario)] hover:bg-black/5 dark:hover:bg-white/10 transition-all outline-none ${copied ? 'opacity-100' : 'opacity-0 group-hover/detalle:opacity-100 focus-visible:opacity-100'}`}
                        >
                            {copied ? (
                                <Check className="w-3.5 h-3.5 text-emerald-500" />
                            ) : (
                                <Copy className="w-3.5 h-3.5" />
                            )}
                        </button>
                    </div>
                </div>
            </div>

            <span
                className="absolute bottom-3 right-3 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider"
                style={{ backgroundColor: `${config.color}18`, color: config.color }}
            >
                {config.label}
            </span>
        </div>
    );
};

export default function Index({ auth, correos, telefonos, extensiones, colaboradores, usuarios, areas }) {
    const [activeTab, setActiveTab] = useState('TODO');
    const [modalType, setModalType] = useState(null);
    const [editTarget, setEditTarget] = useState(null);
    const [searchInput, setSearchInput] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [openActionMenuId, setOpenActionMenuId] = useState(null);

    const { data, setData, post, put, delete: destroy, reset, errors, clearErrors, processing } = useForm({
        rh_colaborador_id: '',
        user_id: '',
        area_id: '',
        email: '',
        telefono: '',
        extension: '',
    });

    const openModal = (type) => {
        clearErrors();
        reset();
        setEditTarget(null);
        setModalType(type);
    };

    const openEditModal = (item) => {
        clearErrors();
        setEditTarget({ id: item.idId, tipo: item.tipo });
        setModalType(item.tipo);
        const o = item.original;
        if (item.tipo === 'correo') {
            setData({
                rh_colaborador_id: o.colaborador?.id?.toString() || '',
                user_id: o.usuario?.id?.toString() || '',
                area_id: '',
                email: o.email,
                telefono: '',
                extension: '',
            });
        } else if (item.tipo === 'telefono') {
            setData({
                rh_colaborador_id: o.colaborador?.id?.toString() || '',
                user_id: o.usuario?.id?.toString() || '',
                area_id: '',
                email: '',
                telefono: o.telefono,
                extension: '',
            });
        } else {
            setData({
                area_id: o.area?.id?.toString() || '',
                rh_colaborador_id: o.encargado?.id?.toString() || '',
                user_id: '',
                email: '',
                telefono: '',
                extension: o.extension,
            });
        }
    };

    const closeModal = () => {
        setModalType(null);
        setEditTarget(null);
        reset();
    };

    const submit = (e) => {
        e.preventDefault();
        const resource = modalType === 'correo' ? 'correos' : modalType === 'telefono' ? 'telefonos' : 'extensiones';
        const opts = { onSuccess: () => closeModal() };
        if (editTarget) {
            put(route(`gestion_interna.directorio.${resource}.update`, editTarget.id), opts);
        } else {
            post(route(`gestion_interna.directorio.${resource}.store`), opts);
        }
    };

    const handleDelete = (id, type) => {
        if (confirm('¿Estás seguro de eliminar este registro?')) {
            const routeName = `gestion_interna.directorio.${type === 'correo' ? 'correos' : type === 'telefono' ? 'telefonos' : 'extensiones'}.destroy`;
            destroy(route(routeName, id));
        }
    };

    // Helper functions for combined view
    const getColaboradorUsuarioNombre = (item) => {
        if (item.colaborador) return `${item.colaborador.nombre} ${item.colaborador.apellido_paterno}`;
        if (item.usuario) return item.usuario.name;
        return 'Sin encargado';
    };

    const getExtensionEncargadoNombre = (item) => {
        if (item.encargado) return `${item.encargado.nombre} ${item.encargado.apellido_paterno}`;
        return 'Sin encargado';
    };

    const mapCorreo = (c) => ({
        idId: c.id,
        uid: `c-${c.id}`,
        original: c,
        tipo: 'correo',
        nombre: getColaboradorUsuarioNombre(c),
        detalle: c.email,
        area: '',
    });

    const mapTelefono = (t) => ({
        idId: t.id,
        uid: `t-${t.id}`,
        original: t,
        tipo: 'telefono',
        nombre: getColaboradorUsuarioNombre(t),
        detalle: t.telefono,
        area: '',
    });

    const mapExtension = (e) => ({
        idId: e.id,
        uid: `e-${e.id}`,
        original: e,
        tipo: 'extension',
        nombre: getExtensionEncargadoNombre(e),
        detalle: e.extension,
        area: e.area?.nombre || '',
    });

    const allItems = useMemo(() => [
        ...correos.map(mapCorreo),
        ...telefonos.map(mapTelefono),
        ...extensiones.map(mapExtension),
    ].sort((a, b) => a.nombre.localeCompare(b.nombre)), [correos, telefonos, extensiones]);

    const tabItems = useMemo(() => {
        let items;
        switch (activeTab) {
            case 'CORREOS':
                items = correos.map(mapCorreo);
                break;
            case 'TELEFONOS':
                items = telefonos.map(mapTelefono);
                break;
            case 'EXTENSIONES':
                items = extensiones.map(mapExtension);
                break;
            default:
                items = allItems;
        }
        if (!appliedSearch.trim()) return items;
        const term = appliedSearch.trim().toLowerCase();
        return items.filter((item) => item.nombre.toLowerCase().includes(term));
    }, [activeTab, allItems, correos, telefonos, extensiones, appliedSearch]);

    const handleSearch = () => setAppliedSearch(searchInput);

    const renderTabs = () => {
        const tabs = [
            { id: 'TODO', icon: List, label: 'Todo' },
            { id: 'CORREOS', icon: Mail, label: 'Correos' },
            { id: 'TELEFONOS', icon: Phone, label: 'Teléfonos' },
            { id: 'EXTENSIONES', icon: PhoneCall, label: 'Extensiones' },
        ];

        return (
            <div className={`${geliaCardClass()} p-3 flex flex-wrap items-center justify-between gap-3 mb-6`}>
                <div className="flex flex-wrap justify-center sm:justify-start gap-2">
                    {tabs.map(t => {
                        const active = activeTab === t.id;
                        const Icon = t.icon;
                        return (
                            <button
                                key={t.id}
                                onClick={() => setActiveTab(t.id)}
                                className={`flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all ${active ? 'text-white shadow-lg' : 'bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 theme-text-muted'}`}
                                style={active ? { backgroundColor: 'var(--color-primario)' } : {}}
                            >
                                <Icon className="w-4 h-4" /> {t.label}
                            </button>
                        );
                    })}
                </div>
                <div className="flex gap-2 items-center w-full sm:w-auto sm:min-w-[280px] sm:max-w-md">
                    <RhSearchField
                        id="directorio-busqueda"
                        value={searchInput}
                        onChange={(e) => setSearchInput(e.target.value)}
                        onSubmit={handleSearch}
                        placeholder="Buscar por nombre..."
                    />
                    <button
                        type="button"
                        onClick={handleSearch}
                        className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white shrink-0 shadow-lg hover:shadow-xl transition-all"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Buscar
                    </button>
                </div>
            </div>
        );
    };

    const renderCardList = () => (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in" key={activeTab}>
            {tabItems.length === 0 ? (
                <div className="col-span-full py-12 text-center theme-text-muted text-sm font-medium italic">
                    {appliedSearch.trim() ? 'No se encontraron contactos con ese nombre.' : 'No hay contactos.'}
                </div>
            ) : (
                tabItems.map((item) => (
                    <DirectorioContactCard
                        key={item.uid}
                        item={item}
                        onEdit={openEditModal}
                        onDelete={handleDelete}
                        isMenuOpen={openActionMenuId === item.uid}
                        onToggleMenu={() => setOpenActionMenuId((prev) => (prev === item.uid ? null : item.uid))}
                        onCloseMenu={() => setOpenActionMenuId(null)}
                    />
                ))
            )}
        </div>
    );

    return (
        <AppLayout auth={auth}>
            <Head title="Directorio Interno" />

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                
                <header className={`${geliaCardClass()} p-6 md:p-10 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border-b-[4px] border-[var(--color-primario)]`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Gestión Interna</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">
                            DIRECTORIO <span style={{ color: 'var(--color-primario)' }}>INTERNO</span>
                        </h1>
                        <p className="theme-text-muted mt-2 text-sm font-medium">Administra y consulta los medios de contacto internos del equipo.</p>
                    </div>
                    <div className="flex gap-3 flex-wrap justify-center">
                        <button onClick={() => openModal('correo')} className="py-3 px-5 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex items-center gap-2 shadow-lg hover:shadow-xl hover:scale-[1.02] text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Correo
                        </button>
                        <button onClick={() => openModal('telefono')} className="py-3 px-5 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex items-center gap-2 shadow-lg hover:shadow-xl hover:scale-[1.02] text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Teléfono
                        </button>
                        <button onClick={() => openModal('extension')} className="py-3 px-5 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex items-center gap-2 shadow-lg hover:shadow-xl hover:scale-[1.02] text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Extensión
                        </button>
                    </div>
                </header>

                {renderTabs()}

                <div className={`${geliaCardClass()} p-6 md:p-8 overflow-hidden`}>
                    {renderCardList()}
                </div>
            </div>

            {modalType && typeof window !== 'undefined' && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-0">
                    <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={closeModal}></div>
                    <div className={`${geliaCardClass()} relative w-full max-w-lg p-6 sm:p-8 animate-fade-in`}>
                        <button onClick={closeModal} className="absolute top-4 right-4 p-2 rounded-full hover:bg-black/10 dark:hover:bg-white/10 theme-text-muted transition">
                            <X className="w-5 h-5" />
                        </button>
                        
                        <div className="flex items-center gap-3 mb-6">
                            <Users className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-xl font-black italic tracking-tight uppercase theme-text-main m-0">
                                {editTarget ? 'Editar' : 'Nuevo'} {modalType === 'correo' ? 'Correo' : modalType === 'telefono' ? 'Teléfono' : 'Extensión'}
                            </h2>
                        </div>

                        {errors.general && (
                            <div className="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-500 text-sm font-bold flex items-center gap-2">
                                <Info className="w-5 h-5" /> {errors.general}
                            </div>
                        )}
                        
                        <form onSubmit={submit} className="space-y-5">
                            {(modalType === 'correo' || modalType === 'telefono') && (
                                <>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Colaborador (RH)</label>
                                        <select 
                                            className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] outline-none transition-all cursor-pointer"
                                            value={data.rh_colaborador_id}
                                            onChange={e => setData('rh_colaborador_id', e.target.value)}
                                        >
                                            <option value="">-- Seleccionar Colaborador --</option>
                                            {colaboradores.map(c => (
                                                <option key={c.id} value={c.id}>{c.nombre} {c.apellido_paterno} {c.apellido_materno}</option>
                                            ))}
                                        </select>
                                        {errors.rh_colaborador_id && <span className="text-red-500 text-xs font-bold mt-1 block">{errors.rh_colaborador_id}</span>}
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Usuario del Sistema (Opcional)</label>
                                        <select 
                                            className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] outline-none transition-all cursor-pointer"
                                            value={data.user_id}
                                            onChange={e => setData('user_id', e.target.value)}
                                        >
                                            <option value="">-- Seleccionar Usuario --</option>
                                            {usuarios.map(u => (
                                                <option key={u.id} value={u.id}>{u.name}</option>
                                            ))}
                                        </select>
                                        {errors.user_id && <span className="text-red-500 text-xs font-bold mt-1 block">{errors.user_id}</span>}
                                    </div>
                                </>
                            )}

                            {modalType === 'extension' && (
                                <>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Área</label>
                                        <SearchableAreaSelect 
                                            areas={areas}
                                            value={data.area_id}
                                            onChange={(id) => setData('area_id', id)}
                                            error={errors.area_id}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Encargado (Opcional)</label>
                                        <select 
                                            className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] outline-none transition-all cursor-pointer"
                                            value={data.rh_colaborador_id}
                                            onChange={e => setData('rh_colaborador_id', e.target.value)}
                                        >
                                            <option value="">-- Seleccionar Encargado --</option>
                                            {colaboradores.map(c => (
                                                <option key={c.id} value={c.id}>{c.nombre} {c.apellido_paterno} {c.apellido_materno}</option>
                                            ))}
                                        </select>
                                        {errors.rh_colaborador_id && <span className="text-red-500 text-xs font-bold mt-1 block">{errors.rh_colaborador_id}</span>}
                                    </div>
                                </>
                            )}

                            {modalType === 'correo' && (
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Email</label>
                                    <input type="email" required className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] outline-none transition-all"
                                        value={data.email} onChange={e => setData('email', e.target.value)} />
                                    {errors.email && <span className="text-red-500 text-xs font-bold mt-1 block">{errors.email}</span>}
                                </div>
                            )}

                            {modalType === 'telefono' && (
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Teléfono</label>
                                    <input type="text" required className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] outline-none transition-all"
                                        value={data.telefono} onChange={e => setData('telefono', e.target.value)} />
                                    {errors.telefono && <span className="text-red-500 text-xs font-bold mt-1 block">{errors.telefono}</span>}
                                </div>
                            )}

                            {modalType === 'extension' && (
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Extensión</label>
                                    <input type="text" required className="w-full theme-surface border theme-border rounded-lg p-3 theme-text-main text-sm font-bold focus:border-[var(--color-primario)] outline-none transition-all"
                                        value={data.extension} onChange={e => setData('extension', e.target.value)} />
                                    {errors.extension && <span className="text-red-500 text-xs font-bold mt-1 block">{errors.extension}</span>}
                                </div>
                            )}

                            <div className="flex gap-4 pt-4">
                                <button type="button" onClick={closeModal} className="w-1/2 py-3 rounded-xl font-black uppercase tracking-widest text-xs transition-all bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 theme-text-main">
                                    Cancelar
                                </button>
                                <button type="submit" disabled={processing} className="w-1/2 py-3 rounded-xl font-black uppercase tracking-widest text-xs transition-all text-white shadow-lg hover:shadow-xl hover:scale-[1.02]" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    {processing ? 'Guardando...' : 'Guardar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}
        </AppLayout>
    );
}
