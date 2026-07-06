import React, { useState, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm } from '@inertiajs/react';
import { Users, Info, Trash2, Plus, X, Mail, Phone, PhoneCall, List, Search, ChevronDown } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { geliaCardClass } from '@/utils/geliaTheme';

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

export default function Index({ auth, correos, telefonos, extensiones, colaboradores, usuarios, areas }) {
    const [activeTab, setActiveTab] = useState('TODO'); // TODO, CORREOS, TELEFONOS, EXTENSIONES
    const [modalType, setModalType] = useState(null); // 'correo', 'telefono', 'extension'

    const { data, setData, post, delete: destroy, reset, errors, clearErrors, processing } = useForm({
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
        setModalType(type);
    };

    const closeModal = () => {
        setModalType(null);
        reset();
    };

    const submit = (e) => {
        e.preventDefault();
        const routeName = `gestion_interna.directorio.${modalType === 'correo' ? 'correos' : modalType === 'telefono' ? 'telefonos' : 'extensiones'}.store`;
        post(route(routeName), {
            onSuccess: () => closeModal(),
        });
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
        return 'Desconocido';
    };

    const getExtensionEncargadoNombre = (item) => {
        if (item.encargado) return `${item.encargado.nombre} ${item.encargado.apellido_paterno}`;
        return 'Sin encargado';
    };

    const todoData = [
        ...correos.map(c => ({ idId: c.id, uid: `c-${c.id}`, original: c, tipo: 'correo', label: 'Correo', nombre: getColaboradorUsuarioNombre(c), detalle: c.email, secundario: '' })),
        ...telefonos.map(t => ({ idId: t.id, uid: `t-${t.id}`, original: t, tipo: 'telefono', label: 'Teléfono', nombre: getColaboradorUsuarioNombre(t), detalle: t.telefono, secundario: '' })),
        ...extensiones.map(e => ({ idId: e.id, uid: `e-${e.id}`, original: e, tipo: 'extension', label: 'Extensión', nombre: getExtensionEncargadoNombre(e), detalle: e.extension, secundario: e.area?.nombre || 'General' })),
    ].sort((a, b) => a.nombre.localeCompare(b.nombre));

    const renderTabs = () => {
        const tabs = [
            { id: 'TODO', icon: List, label: 'Todo' },
            { id: 'CORREOS', icon: Mail, label: 'Correos' },
            { id: 'TELEFONOS', icon: Phone, label: 'Teléfonos' },
            { id: 'EXTENSIONES', icon: PhoneCall, label: 'Extensiones' },
        ];

        return (
            <div className={`${geliaCardClass()} p-3 flex flex-wrap justify-center sm:justify-start gap-2 mb-6`}>
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
        );
    };

    const renderTodoTable = () => (
        <table className="w-full text-left border-collapse">
            <thead>
                <tr className="border-b-2 theme-border">
                    <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Nombre / Encargado</th>
                    <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Tipo</th>
                    <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Detalle de Contacto</th>
                    <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones</th>
                </tr>
            </thead>
            <tbody className="divide-y theme-border">
                {todoData.length === 0 ? (
                    <tr><td colSpan="4" className="py-8 text-center theme-text-muted text-sm font-medium italic">No hay contactos.</td></tr>
                ) : (
                    todoData.map(item => (
                        <tr key={item.uid} className="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                            <td className="py-4 px-4 text-sm font-bold theme-text-main">
                                {item.nombre}
                                {item.secundario && <span className="block text-xs font-medium theme-text-muted">{item.secundario}</span>}
                            </td>
                            <td className="py-4 px-4 text-xs font-bold theme-text-muted uppercase tracking-wider">
                                <span className="px-2 py-1 bg-black/5 dark:bg-white/5 rounded-md">{item.label}</span>
                            </td>
                            <td className="py-4 px-4 text-sm font-medium theme-text-main">{item.detalle}</td>
                            <td className="py-4 px-4 text-right flex justify-end gap-3">
                                <button onClick={() => handleDelete(item.idId, item.tipo)} className="p-2 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-colors">
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                    ))
                )}
            </tbody>
        </table>
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
                    <div className="overflow-x-auto animate-fade-in" key={activeTab}>
                        {activeTab === 'TODO' && renderTodoTable()}
                        
                        {activeTab === 'CORREOS' && (
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b-2 theme-border">
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Nombre</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Email</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y theme-border">
                                    {correos.map(c => (
                                        <tr key={c.id} className="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                            <td className="py-4 px-4 text-sm font-bold theme-text-main">{getColaboradorUsuarioNombre(c)}</td>
                                            <td className="py-4 px-4 text-sm font-medium theme-text-muted">{c.email}</td>
                                            <td className="py-4 px-4 text-right flex justify-end gap-3">
                                                <button onClick={() => handleDelete(c.id, 'correo')} className="p-2 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-colors">
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {activeTab === 'TELEFONOS' && (
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b-2 theme-border">
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Nombre</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Teléfono</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y theme-border">
                                    {telefonos.map(t => (
                                        <tr key={t.id} className="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                            <td className="py-4 px-4 text-sm font-bold theme-text-main">{getColaboradorUsuarioNombre(t)}</td>
                                            <td className="py-4 px-4 text-sm font-medium theme-text-muted">{t.telefono}</td>
                                            <td className="py-4 px-4 text-right flex justify-end gap-3">
                                                <button onClick={() => handleDelete(t.id, 'telefono')} className="p-2 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-colors">
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {activeTab === 'EXTENSIONES' && (
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="border-b-2 theme-border">
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Área</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Encargado</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted">Extensión</th>
                                        <th className="py-4 px-4 text-[11px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y theme-border">
                                    {extensiones.map(e => (
                                        <tr key={e.id} className="hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                            <td className="py-4 px-4 text-sm font-bold theme-text-main">{e.area?.nombre}</td>
                                            <td className="py-4 px-4 text-sm font-medium theme-text-muted">{getExtensionEncargadoNombre(e)}</td>
                                            <td className="py-4 px-4 text-sm font-medium theme-text-muted">{e.extension}</td>
                                            <td className="py-4 px-4 text-right flex justify-end gap-3">
                                                <button onClick={() => handleDelete(e.id, 'extension')} className="p-2 rounded-lg bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white transition-colors">
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
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
                                Nuevo {modalType === 'correo' ? 'Correo' : modalType === 'telefono' ? 'Teléfono' : 'Extensión'}
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
