import { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Bell, Clock, KeyRound, Mail, Server, Settings, Shield, Sparkles, Store, Wallet,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
} from '../../../utils/geliaTheme';
import {
    ESQUEMAS,
    SECCIONES,
    agruparBloques,
    organizar,
} from './configDisplay';
import { canonJson } from './editoresJson';
import AjusteFila from './AjusteFila';
import EditorEstructurado from './EditorEstructurado';
import EditorPreparacionTienda from './EditorPreparacionTienda';

const ICONOS = {
    sistema: Server,
    sesion: Clock,
    correo: Mail,
    pedidos: Settings,
    'punto-venta': Store,
    saldos: Wallet,
    escalonamiento: Shield,
    tienda: Store,
    asistente: Sparkles,
    notificaciones: Bell,
    passkeys: KeyRound,
    otras: Settings,
};

const SECCION_SISTEMA = 'sistema';

export default function ConfiguracionSistema({ auth, configuracionesRaw, sessionDriver = 'database' }) {
    const card = geliaCardClass('relative z-10');
    const lista = useMemo(
        () => (Array.isArray(configuracionesRaw) ? configuracionesRaw : Object.values(configuracionesRaw || {})),
        [configuracionesRaw],
    );
    const porSeccion = useMemo(() => organizar(lista), [lista]);

    const navegacion = useMemo(() => SECCIONES.filter((seccion) => (
        seccion.id === SECCION_SISTEMA || (porSeccion[seccion.id] || []).length > 0
    )), [porSeccion]);

    const resolverInicial = () => {
        if (typeof window === 'undefined') return SECCION_SISTEMA;
        const hash = window.location.hash.replace('#', '');
        if (hash && navegacion.some((item) => item.id === hash)) return hash;
        return SECCION_SISTEMA;
    };

    const [seccionActiva, setSeccionActiva] = useState(resolverInicial);
    const [busqueda, setBusqueda] = useState('');
    const [guardandoId, setGuardandoId] = useState(null);
    const [avisos, setAvisos] = useState({});
    const [borradores, setBorradores] = useState({});
    const [modalTestMail, setModalTestMail] = useState(false);

    const { data: dataMail, setData: setDataMail, post: postMail, processing: processingMail, reset: resetMail, errors: errorsMail } = useForm({
        email: auth.user.email || '',
    });

    useEffect(() => {
        const hash = window.location.hash.replace('#', '');
        if (hash && navegacion.some((item) => item.id === hash) && hash !== seccionActiva) {
            setSeccionActiva(hash);
        }
    }, [navegacion, seccionActiva]);

    const cambiarSeccion = (id) => {
        setSeccionActiva(id);
        setBusqueda('');
        const url = `${window.location.pathname}${window.location.search}#${id}`;
        window.history.replaceState(null, '', url);
    };

    const guardar = (config, valor, extra = {}) => {
        setGuardandoId(config.id);
        router.put(route('admin.configuracion_sistema.update', config.id), {
            valor: valor ?? '',
            tipo: config.tipo,
            descripcion: config.descripcion || '',
            grupo: config.grupo || '',
            ...extra,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setAvisos((prev) => ({ ...prev, [config.id]: { tipo: 'ok', texto: 'Guardado' } }));
                setBorradores((prev) => {
                    const siguiente = { ...prev };
                    delete siguiente[config.id];
                    return siguiente;
                });
            },
            onError: (errores) => {
                const texto = errores.valor || errores.error || 'No se pudo guardar.';
                setAvisos((prev) => ({ ...prev, [config.id]: { tipo: 'error', texto } }));
            },
            onFinish: () => setGuardandoId(null),
        });
    };

    const itemsVisibles = useMemo(() => {
        const items = porSeccion[seccionActiva] || [];
        const termino = busqueda.trim().toLowerCase();
        if (!termino) return items;
        return items.filter(({ config, meta }) => (
            meta.titulo.toLowerCase().includes(termino)
            || meta.ayuda.toLowerCase().includes(termino)
            || (config.clave || '').toLowerCase().includes(termino)
        ));
    }, [busqueda, porSeccion, seccionActiva]);

    const abrirPrueba = () => {
        resetMail();
        setDataMail('email', auth.user.email || '');
        setModalTestMail(true);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Configuración | GELIANV" />
            <GeliaPageShell className="p-4 md:p-8 space-y-6">
                <header className={`${card} p-6 md:p-8`}>
                    <h1 className="text-2xl md:text-3xl font-semibold theme-text-main m-0">Configuración</h1>
                    <p className="text-sm theme-text-muted mt-2 mb-0 max-w-prose">
                        Active funciones y ajuste los parámetros del sistema. Cada cambio se guarda en la configuración que ya existe.
                    </p>
                </header>

                <div className="flex flex-col lg:flex-row gap-6 items-start">
                    <div className={`lg:hidden w-full ${GELIA_SEGMENT_TABS_SCROLL}`}>
                        <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1`} role="tablist" aria-label="Áreas de configuración">
                            {navegacion.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={seccionActiva === item.id}
                                    data-active={seccionActiva === item.id}
                                    className="gelia-segment-btn whitespace-nowrap"
                                    onClick={() => cambiarSeccion(item.id)}
                                >
                                    {item.titulo}
                                </button>
                            ))}
                        </div>
                    </div>

                    <nav aria-label="Áreas de configuración" className={`hidden lg:flex lg:flex-col w-64 shrink-0 ${card} p-3 gap-1 sticky top-4`}>
                        {navegacion.map((item) => {
                            const Icon = ICONOS[item.id] || Settings;
                            const activa = seccionActiva === item.id;
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    aria-current={activa ? 'page' : undefined}
                                    onClick={() => cambiarSeccion(item.id)}
                                    className={`w-full min-h-11 flex items-center gap-3 px-3 py-2 rounded-xl text-left text-sm font-medium ${activa ? 'text-white' : 'theme-text-muted hover:theme-text-main'}`}
                                    style={activa ? { backgroundColor: 'var(--color-primario)' } : undefined}
                                >
                                    <Icon className="w-4 h-4 shrink-0" aria-hidden />
                                    <span className="truncate">{item.titulo}</span>
                                </button>
                            );
                        })}
                    </nav>

                    <div className="flex-1 min-w-0 w-full space-y-4">
                        {seccionActiva === SECCION_SISTEMA ? (
                            <PanelSistema card={card} sessionDriver={sessionDriver} />
                        ) : (
                            <section className={`${card} p-5 md:p-6`}>
                                <div className="mb-4">
                                    <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor="buscar-config">
                                        Buscar en esta área
                                    </label>
                                    <input
                                        id="buscar-config"
                                        type="search"
                                        value={busqueda}
                                        onChange={(e) => setBusqueda(e.target.value)}
                                        className={`${THEME_INPUT} w-full px-3 py-2 text-sm`}
                                    />
                                </div>

                                {seccionActiva === 'correo' && (
                                    <div className="mb-4">
                                        <button type="button" className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2 min-h-11`} onClick={abrirPrueba}>
                                            <Mail className="w-4 h-4" aria-hidden />
                                            Enviar correo de prueba
                                        </button>
                                    </div>
                                )}

                                {itemsVisibles.length === 0 ? (
                                    <p className="text-sm theme-text-muted m-0">No hay ajustes que coincidan con la búsqueda.</p>
                                ) : (
                                    agruparBloques(itemsVisibles).map((bloque) => (
                                        <div key={bloque.nombre || 'general'} className="mb-6 last:mb-0">
                                            {bloque.nombre && (
                                                <h2 className="text-base font-semibold theme-text-main m-0 mb-1">{bloque.nombre}</h2>
                                            )}
                                            {bloque.items.map(({ config, meta }) => (
                                                meta.control === 'json' || meta.control === 'preparacion' ? (
                                                    <BloqueCompuesto
                                                        key={config.id}
                                                        config={config}
                                                        meta={meta}
                                                        borrador={borradores[config.id]}
                                                        guardando={guardandoId === config.id}
                                                        aviso={avisos[config.id]}
                                                        onBorrador={(valor) => setBorradores((prev) => ({ ...prev, [config.id]: valor }))}
                                                        onGuardar={guardar}
                                                    />
                                                ) : (
                                                    <AjusteFila
                                                        key={config.id}
                                                        config={config}
                                                        meta={meta}
                                                        guardando={guardandoId === config.id}
                                                        aviso={avisos[config.id]}
                                                        onGuardar={guardar}
                                                    />
                                                )
                                            ))}
                                        </div>
                                    ))
                                )}
                            </section>
                        )}
                    </div>
                </div>
            </GeliaPageShell>

            {modalTestMail && typeof document !== 'undefined' && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalTestMail(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg w-full`} onClick={(e) => e.stopPropagation()} role="dialog" aria-modal="true" aria-labelledby="titulo-prueba-correo">
                        <form
                            className="p-6 space-y-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                postMail(route('admin.configuracion_sistema.test_mail'), {
                                    onSuccess: () => setModalTestMail(false),
                                });
                            }}
                        >
                            <h2 id="titulo-prueba-correo" className="text-xl font-semibold theme-text-main m-0">Enviar correo de prueba</h2>
                            <p className="text-sm theme-text-muted m-0">Se usa la configuración de correo que está guardada ahora.</p>
                            <div>
                                <label className="block text-sm font-semibold theme-text-main mb-1" htmlFor="correo-prueba">Correo de destino</label>
                                <input
                                    id="correo-prueba"
                                    type="email"
                                    required
                                    value={dataMail.email}
                                    onChange={(e) => setDataMail('email', e.target.value)}
                                    className={`${THEME_INPUT} w-full px-3 py-2 text-sm`}
                                />
                                {errorsMail.email && <p className="text-sm theme-text-peligro mt-1" role="alert">{errorsMail.email}</p>}
                            </div>
                            <div className="flex justify-end gap-3">
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setModalTestMail(false)}>Cancelar</button>
                                <button type="submit" className={THEME_BTN_PRIMARY} disabled={processingMail}>Enviar prueba</button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body,
            )}
        </AppLayout>
    );
}

function PanelSistema({ card, sessionDriver }) {
    const enBase = sessionDriver === 'database';
    return (
        <section className={`${card} p-5 md:p-6`}>
            <h2 className="text-base font-semibold theme-text-main m-0">Dónde se guardan las sesiones</h2>
            <p className="text-sm theme-text-muted mt-2 mb-0">
                {enBase
                    ? 'Las sesiones se guardan en la base de datos. Así se puede ver quién está dentro y revisar los accesos.'
                    : 'Las sesiones no se están guardando en la base de datos. El listado de sesiones y la revisión de accesos no están disponibles.'}
            </p>
            <details className="mt-3">
                <summary className="text-sm theme-text-muted cursor-pointer">Detalle técnico</summary>
                <p className="text-sm theme-text-muted mt-2 mb-0">
                    Valor actual: <strong>{sessionDriver}</strong>. Este dato se define al instalar el sistema y no se cambia desde aquí.
                </p>
            </details>
        </section>
    );
}

function BloqueCompuesto({ config, meta, borrador, guardando, aviso, onBorrador, onGuardar }) {
    const actual = borrador ?? config.valor ?? '';
    const sucio = canonJson(actual) !== canonJson(config.valor || '');
    const esquema = ESQUEMAS[config.clave];

    return (
        <div className="py-4 border-b theme-border last:border-b-0">
            <p className="text-sm font-semibold theme-text-main m-0">{meta.titulo}</p>
            <p className="text-sm theme-text-muted m-0 mt-1 mb-3 max-w-prose">{meta.ayuda}</p>
            {meta.control === 'preparacion' ? (
                <EditorPreparacionTienda
                    key={`${config.id}:${config.valor}`}
                    valor={config.valor}
                    onChange={onBorrador}
                />
            ) : esquema ? (
                <EditorEstructurado
                    key={`${config.id}:${config.valor}`}
                    valorInicial={config.valor}
                    esquema={esquema}
                    onChange={onBorrador}
                />
            ) : (
                <textarea
                    aria-label={meta.titulo}
                    className="theme-textarea w-full px-3 py-2 text-sm"
                    rows={6}
                    value={actual}
                    onChange={(e) => onBorrador(e.target.value)}
                />
            )}
            <div className="mt-3 flex items-center gap-3">
                <button
                    type="button"
                    className="theme-btn-primary theme-btn-primary--compact min-h-11 disabled:opacity-50"
                    disabled={!sucio || guardando}
                    onClick={() => onGuardar(config, actual)}
                >
                    {guardando ? 'Guardando…' : 'Guardar'}
                </button>
                {aviso && (
                    <p className={`text-sm m-0 ${aviso.tipo === 'ok' ? 'theme-text-exito' : 'theme-text-peligro'}`} role={aviso.tipo === 'ok' ? 'status' : 'alert'}>
                        {aviso.texto}
                    </p>
                )}
            </div>
            <details className="mt-2">
                <summary className="text-sm theme-text-muted cursor-pointer">Código interno</summary>
                <p className="text-sm theme-text-muted m-0 mt-1 break-all">{config.clave}</p>
            </details>
        </div>
    );
}
