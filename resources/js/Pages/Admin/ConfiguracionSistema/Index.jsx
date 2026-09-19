import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm } from '@inertiajs/react';
import {
    Settings, Plus, Edit2, Trash2, Mail, Check, X, AlertCircle, Shield,
    Server, KeyRound, Store, Clock, Bell, FolderOpen, Layers, Search,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import {
    geliaCardClass,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
} from '../../../utils/geliaTheme';
import {
    agruparPorCategoria,
    esBooleanoVerdadero,
    esValorSecreto,
    etiquetaCategoria,
    filtrarConfiguraciones,
    formatearValorJson,
    resumenValorJson,
    tituloConfiguracion,
    tituloUsaDescripcion,
} from './configDisplay';

const SECCION_SISTEMA = 'sistema';
const MIN_ITEMS_PARA_SUBCATEGORIAS = 6;

const normalizarBooleanoParaEdicion = (valor) => (esBooleanoVerdadero(valor) ? 'true' : 'false');

const grupoSlug = (nombre) => (nombre || 'general')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

const iconoGrupo = (nombre) => {
    const clave = (nombre || '').toLowerCase();
    if (clave.includes('mail') || clave.includes('correo')) return Mail;
    if (clave.includes('sesion')) return Clock;
    if (clave.includes('webauthn') || clave.includes('passkey')) return KeyRound;
    if (clave.includes('tiendanube') || clave.includes('woo')) return Store;
    if (clave.includes('push') || clave.includes('notif')) return Bell;
    if (clave.includes('segur')) return Shield;
    return FolderOpen;
};

const SimpleModal = ({ show, onClose, title, children }) => {
    if (!show || typeof document === 'undefined') return null;
    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full modal-pop`} onClick={e => e.stopPropagation()}>
                <div className="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                    <h2 className="text-xl font-bold theme-text-main">{title}</h2>
                    <button type="button" onClick={onClose} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full"><X className="w-5 h-5"/></button>
                </div>
                <div className="p-6 max-h-[80vh] overflow-y-auto">{children}</div>
            </div>
        </div>,
        document.body,
    );
};

const BadgeValor = ({ config }) => {
    if (config.tipo === 'boolean') {
        const activo = esBooleanoVerdadero(config.valor);
        return (
            <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest shrink-0 ${
                activo
                    ? 'bg-green-500/15 text-green-600 dark:text-green-400'
                    : 'bg-gray-500/15 text-gray-500 dark:text-gray-400'
            }`}>
                {activo ? <Check className="w-3 h-3" /> : <X className="w-3 h-3" />}
                {activo ? 'Activado' : 'Desactivado'}
            </span>
        );
    }

    if (config.tipo === 'json') {
        return (
            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-widest bg-blue-500/10 text-blue-600 dark:text-blue-400 shrink-0">
                JSON
            </span>
        );
    }

    if (esValorSecreto(config)) {
        return (
            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-mono theme-text-muted bg-black/5 dark:bg-white/5 shrink-0">
                ••••••••
            </span>
        );
    }

    const valor = config.valor;
    if (!valor) {
        return (
            <span className="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-widest theme-text-muted bg-black/5 dark:bg-white/5 shrink-0">
                Vacío
            </span>
        );
    }

    const texto = String(valor);
    const truncado = texto.length > 36 ? `${texto.slice(0, 33)}…` : texto;

    return (
        <span className="inline-flex items-center max-w-[12rem] px-2.5 py-1 rounded-lg text-xs font-semibold theme-text-main bg-black/5 dark:bg-white/5 truncate shrink-0" title={texto}>
            {truncado}
        </span>
    );
};

const FilaConfiguracion = ({ config, onEdit, onDelete }) => {
    const titulo = tituloConfiguracion(config);
    const descripcion = (config.descripcion || '').trim();
    const mostrarDescripcion = descripcion && !tituloUsaDescripcion(config) && descripcion !== titulo;

    return (
        <div className="px-4 py-3 md:px-5 md:py-3.5 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
            <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h3 className="text-sm md:text-base font-bold theme-text-main leading-snug m-0">
                            {titulo}
                        </h3>
                        <BadgeValor config={config} />
                    </div>

                    {mostrarDescripcion && (
                        <p className="text-xs theme-text-muted mt-1 mb-0 leading-relaxed">{descripcion}</p>
                    )}

                    {config.tipo === 'json' && (
                        <details className="mt-2 group">
                            <summary className="text-[10px] font-bold uppercase tracking-widest theme-text-muted cursor-pointer select-none list-none flex items-center gap-1">
                                <span className="group-open:rotate-90 transition-transform">›</span>
                                Ver valor JSON ({resumenValorJson(config.valor)})
                            </summary>
                            <pre className="mt-2 p-3 rounded-xl border theme-border bg-black/5 dark:bg-black/40 text-xs font-mono overflow-x-auto whitespace-pre-wrap break-all theme-text-main">
                                {formatearValorJson(config.valor)}
                            </pre>
                        </details>
                    )}

                    <details className="mt-1.5">
                        <summary className="text-[10px] font-bold uppercase tracking-widest theme-text-muted/70 cursor-pointer select-none list-none">
                            Referencia técnica
                        </summary>
                        <p className="text-[11px] font-mono theme-text-muted mt-1 mb-0 break-all">{config.clave}</p>
                    </details>
                </div>

                <div className="flex items-center gap-1 shrink-0">
                    <button type="button" onClick={() => onEdit(config)} className="p-2 rounded-xl text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="Editar">
                        <Edit2 className="w-4 h-4" />
                    </button>
                    <button type="button" onClick={() => onDelete(config.id)} className="p-2 rounded-xl text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Eliminar">
                        <Trash2 className="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    );
};

const PanelSistema = ({ activeCardClass, sessionDriver }) => (
    <section className={`${activeCardClass} overflow-hidden`}>
        <div className="p-5 md:p-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-black/20">
            <h2 className="text-xl font-black tracking-tight theme-text-main flex items-center gap-2">
                <div className="w-2 h-6 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                Infraestructura del sistema
            </h2>
            <p className="text-sm theme-text-muted mt-2">
                Parámetros definidos en el entorno de despliegue y no editables desde esta pantalla.
            </p>
        </div>

        <div className="p-5 md:p-6 flex items-start gap-4 border-b border-amber-500/20 bg-amber-500/5">
            <Shield className="w-6 h-6 text-amber-600 shrink-0 mt-0.5" />
            <div>
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main">Driver de sesiones (solo lectura)</h3>
                <p className="text-sm theme-text-muted mt-1">
                    El almacenamiento de sesiones se define en <code className="text-xs">SESSION_DRIVER</code> del archivo <code className="text-xs">.env</code>.
                    Valor actual: <strong className="font-mono">{sessionDriver}</strong>.
                    {sessionDriver !== 'database' && (
                        <span className="block mt-1 text-amber-600 font-bold">
                            Para habilitar el listado de sesiones y la auditoría de accesos, configure SESSION_DRIVER=database en producción.
                        </span>
                    )}
                </p>
            </div>
        </div>
    </section>
);

const PanelGrupo = ({ grupoName, configuraciones, activeCardClass, onEdit, onDelete }) => {
    const [busqueda, setBusqueda] = useState('');
    const [subcategoriaActiva, setSubcategoriaActiva] = useState('todas');

    const categorias = useMemo(() => agruparPorCategoria(configuraciones), [configuraciones]);
    const mostrarSubcategorias = configuraciones.length >= MIN_ITEMS_PARA_SUBCATEGORIAS
        && Object.keys(categorias).length >= 2;

    const subcategorias = useMemo(() => {
        if (!mostrarSubcategorias) {
            return [];
        }

        const items = Object.entries(categorias).map(([id, itemsCategoria]) => ({
            id,
            label: etiquetaCategoria(id),
            count: itemsCategoria.length,
        }));

        return [
            { id: 'todas', label: 'Todas', count: configuraciones.length },
            ...items,
        ];
    }, [categorias, configuraciones.length, mostrarSubcategorias]);

    useEffect(() => {
        setBusqueda('');
        setSubcategoriaActiva('todas');
    }, [grupoName]);

    const configuracionesVisibles = useMemo(() => {
        const base = subcategoriaActiva === 'todas'
            ? configuraciones
            : categorias[subcategoriaActiva] ?? [];

        return filtrarConfiguraciones(base, busqueda);
    }, [busqueda, categorias, configuraciones, subcategoriaActiva]);

    return (
        <section className={`${activeCardClass} overflow-hidden flex flex-col max-h-[calc(100vh-10rem)]`}>
            <div className="p-5 md:p-6 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-black/20 shrink-0">
                <h2 className="text-xl font-black tracking-tight theme-text-main flex items-center gap-2">
                    <div className="w-2 h-6 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                    {grupoName || 'GENERAL'}
                </h2>
                <p className="text-sm theme-text-muted mt-2">
                    {configuraciones.length} variable{configuraciones.length === 1 ? '' : 's'} en este grupo
                </p>
            </div>

            <div className="p-4 md:px-5 border-b theme-border shrink-0 space-y-3">
                <div className="theme-field-with-icon">
                    <Search className="theme-field-icon" aria-hidden />
                    <input
                        type="search"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar por nombre, descripción o clave…"
                        className="theme-input w-full pr-4 py-2.5 normal-case tracking-normal font-bold text-sm"
                    />
                </div>

                {mostrarSubcategorias && (
                    <div className={GELIA_SEGMENT_TABS_SCROLL}>
                        <div
                            className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`}
                            role="tablist"
                            aria-label={`Subcategorías de ${grupoName}`}
                        >
                            {subcategorias.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={subcategoriaActiva === item.id}
                                    onClick={() => setSubcategoriaActiva(item.id)}
                                    className="gelia-segment-btn whitespace-nowrap gap-1"
                                    data-active={subcategoriaActiva === item.id}
                                >
                                    {item.label}
                                    <span className="opacity-70">({item.count})</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto custom-scrollbar divide-y divide-gray-100 dark:divide-gray-800">
                {configuracionesVisibles.length === 0 ? (
                    <div className="p-10 text-center theme-text-muted">
                        <AlertCircle className="w-8 h-8 mx-auto mb-3 opacity-40" />
                        <p className="text-sm font-bold m-0">No hay configuraciones que coincidan con la búsqueda.</p>
                    </div>
                ) : (
                    configuracionesVisibles.map((config) => (
                        <FilaConfiguracion
                            key={config.id}
                            config={config}
                            onEdit={onEdit}
                            onDelete={onDelete}
                        />
                    ))
                )}
            </div>
        </section>
    );
};

export default function ConfiguracionSistema({ auth, configuracionesGrupos, configuracionesRaw, sessionDriver = 'database' }) {
    const activeCardClass = geliaCardClass('relative z-10');

    const [modalConfig, setModalConfig] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [modalTestMail, setModalTestMail] = useState(false);

    const gruposOrdenados = useMemo(
        () => Object.keys(configuracionesGrupos).sort((a, b) => (a || '').localeCompare(b || '', 'es')),
        [configuracionesGrupos],
    );

    const navegacion = useMemo(() => {
        const items = [
            { id: SECCION_SISTEMA, label: 'Sistema', icon: Server, count: null, grupoKey: null },
        ];

        gruposOrdenados.forEach((grupoName) => {
            items.push({
                id: grupoSlug(grupoName),
                label: grupoName || 'GENERAL',
                icon: iconoGrupo(grupoName),
                count: configuracionesGrupos[grupoName]?.length ?? 0,
                grupoKey: grupoName,
            });
        });

        return items;
    }, [gruposOrdenados, configuracionesGrupos]);

    const resolverSeccionInicial = () => {
        if (typeof window === 'undefined') return SECCION_SISTEMA;
        const hash = window.location.hash.replace('#', '');
        if (hash && navegacion.some((item) => item.id === hash)) return hash;
        return SECCION_SISTEMA;
    };

    const [seccionActiva, setSeccionActiva] = useState(resolverSeccionInicial);

    useEffect(() => {
        const hash = window.location.hash.replace('#', '');
        if (hash && navegacion.some((item) => item.id === hash) && hash !== seccionActiva) {
            setSeccionActiva(hash);
        }
    }, [navegacion, seccionActiva]);

    const cambiarSeccion = (id) => {
        setSeccionActiva(id);
        const url = `${window.location.pathname}${window.location.search}#${id}`;
        window.history.replaceState(null, '', url);
    };

    const seccionActual = navegacion.find((item) => item.id === seccionActiva) ?? navegacion[0];
    const grupoActivo = seccionActual?.grupoKey ?? null;

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        id: null,
        clave: '',
        valor: '',
        tipo: 'string',
        descripcion: '',
        grupo: '',
    });

    const { data: dataMail, setData: setDataMail, post: postMail, processing: processingMail, reset: resetMail, errors: errorsMail } = useForm({
        email: auth.user.email || '',
    });

    const openModalCreate = () => {
        setIsEditing(false);
        reset();
        if (grupoActivo) {
            setData('grupo', grupoActivo);
        }
        setModalConfig(true);
    };

    const openModalEdit = (configuracion) => {
        setIsEditing(true);
        const valorEdit = configuracion.tipo === 'boolean'
            ? normalizarBooleanoParaEdicion(configuracion.valor)
            : (configuracion.valor || '');
        setData({
            id: configuracion.id,
            clave: configuracion.clave,
            valor: valorEdit,
            tipo: configuracion.tipo,
            descripcion: configuracion.descripcion || '',
            grupo: configuracion.grupo || '',
        });
        setModalConfig(true);
    };

    const openModalTestMail = () => {
        resetMail();
        setDataMail('email', auth.user.email || '');
        setModalTestMail(true);
    };

    const submitConfig = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('admin.configuracion_sistema.update', data.id), {
                onSuccess: () => {
                    setModalConfig(false);
                    reset();
                }
            });
        } else {
            post(route('admin.configuracion_sistema.store'), {
                onSuccess: () => {
                    setModalConfig(false);
                    reset();
                }
            });
        }
    };

    const handleDelete = (id) => {
        if (confirm('¿Estás seguro de eliminar esta configuración? Esto podría romper el sistema si es requerida.')) {
            destroy(route('admin.configuracion_sistema.destroy', id));
        }
    };

    const submitTestMail = (e) => {
        e.preventDefault();
        postMail(route('admin.configuracion_sistema.test_mail'), {
            onSuccess: () => {
                setModalTestMail(false);
            }
        });
    };

    const renderBotonNav = (item, compact = false) => {
        const activa = seccionActiva === item.id;
        const Icon = item.icon;

        return (
            <button
                key={item.id}
                type="button"
                onClick={() => cambiarSeccion(item.id)}
                aria-current={activa ? 'page' : undefined}
                className={`w-full flex items-center gap-3 rounded-xl text-left transition-all ${
                    compact ? 'px-3 py-2.5' : 'px-3 py-3'
                } ${
                    activa
                        ? 'text-white shadow-sm'
                        : 'theme-text-muted hover:theme-text-main hover:bg-black/[0.03] dark:hover:bg-white/[0.04]'
                }`}
                style={activa ? { backgroundColor: 'var(--color-primario)' } : {}}
            >
                <Icon className="w-4 h-4 shrink-0" />
                <span className={`flex-1 min-w-0 truncate ${compact ? 'text-[10px]' : 'text-xs'} font-black uppercase tracking-widest`}>
                    {item.label}
                </span>
                {item.count !== null && (
                    <span className={`text-[10px] font-black px-2 py-0.5 rounded-full shrink-0 ${
                        activa ? 'bg-white/20 text-white' : 'bg-black/5 dark:bg-white/10 theme-text-muted'
                    }`}>
                        {item.count}
                    </span>
                )}
            </button>
        );
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Configuración del Sistema | GELIANV" />

            <GeliaPageShell className="p-4 md:p-8 space-y-8 relative">
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Core del Sistema_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            Variables <span style={{ color: 'var(--color-primario)' }}>Globales</span>
                        </h1>
                        <p className="text-sm theme-text-muted flex items-center gap-2">
                            <Settings className="w-4 h-4" />
                            Sobreescritura de .env en tiempo de ejecución
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <button onClick={openModalTestMail} type="button" className={`${THEME_BTN_SECONDARY} flex items-center gap-2 w-full sm:w-auto justify-center`}>
                            <Mail className="w-4 h-4" /> Probar Mail
                        </button>
                        <button onClick={openModalCreate} type="button" className={`${THEME_BTN_PRIMARY} flex items-center gap-2 w-full sm:w-auto justify-center`}>
                            <Plus className="w-4 h-4" /> Agregar Variable
                        </button>
                    </div>
                </header>

                <div className={`flex flex-col gap-6 lg:gap-8 items-start ${navegacion.length > 1 ? 'lg:flex-row' : ''}`}>
                    {navegacion.length > 1 && (
                        <div className={`lg:hidden w-full ${GELIA_SEGMENT_TABS_SCROLL}`}>
                            <div
                                className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`}
                                role="tablist"
                                aria-label="Secciones de configuración"
                            >
                                {navegacion.map((item) => {
                                    const activa = seccionActiva === item.id;
                                    const Icon = item.icon;
                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={activa}
                                            onClick={() => cambiarSeccion(item.id)}
                                            className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                            data-active={activa}
                                        >
                                            <Icon className="w-3.5 h-3.5 shrink-0" />
                                            {item.label}
                                            {item.count !== null && (
                                                <span className="opacity-70">({item.count})</span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {navegacion.length > 1 && (
                        <nav
                            aria-label="Submenú de configuración"
                            className={`hidden lg:flex lg:flex-col w-full lg:w-56 xl:w-64 shrink-0 ${activeCardClass} p-3 gap-1 sticky top-4 max-h-[calc(100vh-8rem)] overflow-y-auto custom-scrollbar`}
                        >
                            <div className="flex items-center gap-2 px-3 py-2 mb-1">
                                <Layers className="w-3.5 h-3.5 theme-text-muted" />
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    Secciones
                                </p>
                            </div>
                            {navegacion.map((item) => renderBotonNav(item))}
                        </nav>
                    )}

                    <div className="flex-1 min-w-0 w-full space-y-6">
                        {seccionActiva === SECCION_SISTEMA ? (
                            <PanelSistema activeCardClass={activeCardClass} sessionDriver={sessionDriver} />
                        ) : grupoActivo ? (
                            <PanelGrupo
                                grupoName={grupoActivo}
                                configuraciones={configuracionesGrupos[grupoActivo] ?? []}
                                activeCardClass={activeCardClass}
                                onEdit={openModalEdit}
                                onDelete={handleDelete}
                            />
                        ) : (
                            <div className={`${activeCardClass} p-12 text-center text-gray-500`}>
                                <AlertCircle className="w-12 h-12 mx-auto mb-4 opacity-50" />
                                <h3 className="text-xl font-bold mb-2 theme-text-main">Sin configuraciones</h3>
                                <p>No se ha registrado ninguna variable global aún.</p>
                            </div>
                        )}
                    </div>
                </div>
            </GeliaPageShell>

            <SimpleModal
                show={modalConfig}
                onClose={() => setModalConfig(false)}
                title={isEditing ? 'Editar Variable Global' : 'Nueva Variable Global'}
            >
                <form onSubmit={submitConfig} className="space-y-4">
                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Clave (Formato con puntos, ej. mail.host)</label>
                        <input
                            type="text"
                            value={data.clave}
                            onChange={e => setData('clave', e.target.value)}
                            className={THEME_INPUT}
                            disabled={isEditing}
                            required
                        />
                        {errors.clave && <p className="text-red-500 text-xs mt-1">{errors.clave}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Grupo (Ej. Mail, General, WebPush)</label>
                        <input
                            type="text"
                            value={data.grupo}
                            onChange={e => setData('grupo', e.target.value)}
                            className={THEME_INPUT}
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Tipo de Dato</label>
                        <select
                            value={data.tipo}
                            onChange={e => setData('tipo', e.target.value)}
                            className={THEME_SELECT}
                        >
                            <option value="string">String (Texto)</option>
                            <option value="integer">Integer (Número)</option>
                            <option value="boolean">Boolean (Verdadero/Falso)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Valor</label>
                        {data.tipo === 'boolean' ? (
                        <select
                            value={data.tipo === 'boolean' ? normalizarBooleanoParaEdicion(data.valor) : data.valor}
                            onChange={e => setData('valor', e.target.value)}
                            className={THEME_SELECT}
                        >
                                <option value="true">Activado</option>
                                <option value="false">Desactivado</option>
                            </select>
                        ) : (
                            <textarea
                                value={data.valor}
                                onChange={e => setData('valor', e.target.value)}
                                className={THEME_TEXTAREA}
                                rows={3}
                            />
                        )}
                        {errors.valor && <p className="text-red-500 text-xs mt-1">{errors.valor}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Nombre visible para operadores</label>
                        <input
                            type="text"
                            value={data.descripcion}
                            onChange={e => setData('descripcion', e.target.value)}
                            className={THEME_INPUT}
                            placeholder="Descripción breve que verán en esta pantalla"
                        />
                    </div>

                    <div className="flex justify-end gap-3 pt-4 mt-6">
                        <button type="button" onClick={() => setModalConfig(false)} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processing} className={THEME_BTN_PRIMARY}>{isEditing ? 'Guardar Cambios' : 'Crear Variable'}</button>
                    </div>
                </form>
            </SimpleModal>

            <SimpleModal
                show={modalTestMail}
                onClose={() => setModalTestMail(false)}
                title="Probar Configuración de Correo"
            >
                <form onSubmit={submitTestMail} className="space-y-4">
                    <p className="text-sm theme-text-muted mb-4">
                        Se enviará un correo de prueba utilizando la configuración actual (o la global si ya sobreescribió).
                    </p>

                    <div>
                        <label className="block text-sm font-bold theme-text-main mb-1">Correo Electrónico Destino</label>
                        <input
                            type="email"
                            value={dataMail.email}
                            onChange={e => setDataMail('email', e.target.value)}
                            className={THEME_INPUT}
                            required
                        />
                        {errorsMail.email && <p className="text-red-500 text-xs mt-1">{errorsMail.email}</p>}
                    </div>

                    <div className="flex justify-end gap-3 pt-4 mt-6">
                        <button type="button" onClick={() => setModalTestMail(false)} className={THEME_BTN_SECONDARY}>Cancelar</button>
                        <button type="submit" disabled={processingMail} className={THEME_BTN_PRIMARY}>Enviar Prueba</button>
                    </div>
                </form>
            </SimpleModal>
        </AppLayout>
    );
}
