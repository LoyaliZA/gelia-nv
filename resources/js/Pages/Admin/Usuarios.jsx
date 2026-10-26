import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import axios from 'axios';

import {
    Users, UserPlus, Search, Edit3, Archive, RotateCcw,
    MapPin, Mail, AtSign, UserCog,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import ModalUsuarioForm from './Partials/ModalUsuarioForm';
import DrawerColaborador from './Partials/DrawerColaborador';
import ConfiguracionHerencia from './Partials/ConfiguracionHerencia';
import {
    alternarRelacion,
    aplicarPlantillaPermisos,
    datosFormularioDesdeUsuario,
    debeConfirmarCierre,
    decidirCambioColaborador,
    decidirRespuestaGuardado,
    anchoDrawerColaborador,
    modoDrawerColaborador,
    esAdministracionGlobal,
    formularioVacio,
    limpiarMapaPlantilla,
    mensajeErrorHttp,
    mensajeErrorRed,
    normalizarErrores,
    omitirRelacionesFueraDeCatalogo,
    publicarIncidenciaGuardado,
    resolverPrincipalFormulario,
    soltarEnvio,
    tomarEnvio,
} from './Partials/formularioUsuario';
import {
    deduplicarPermisos,
    permisoProtegidoParaEditor,
} from '../../utils/permisos';
import { geliaCardClass, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../utils/geliaTheme';

function nombreCompleto(usuario) {
    return [(usuario.name || '').trim(), (usuario.apellido_paterno || '').trim(), (usuario.apellido_materno || '').trim()]
        .filter(Boolean)
        .join(' ');
}

function nombreCortoPersona(persona) {
    return [(persona?.name || '').trim(), (persona?.apellido_paterno || '').trim()]
        .filter(Boolean)
        .join(' ');
}

function etiquetasGerentes(usuario) {
    const lista = usuario?.gerentes || [];
    if (lista.length === 0) return 'Sin gerente';
    return lista.map(nombreCortoPersona).filter(Boolean).join(', ');
}

function UserAvatar({ usuario }) {
    const [loadFailed, setLoadFailed] = useState(false);
    const initial = (usuario.name || 'U').charAt(0).toUpperCase();
    const hasPhoto = Boolean(usuario.foto_perfil) && !loadFailed;

    useEffect(() => {
        setLoadFailed(false);
    }, [usuario.foto_perfil]);

    return (
        <div
            className="w-14 h-14 rounded-full overflow-hidden flex items-center justify-center border-[3px] shadow-sm transition-colors shrink-0 bg-[var(--color-primario)]"
            style={{ borderColor: 'var(--color-primario)' }}
        >
            {hasPhoto ? (
                <img
                    src={`/storage/${usuario.foto_perfil}`}
                    alt={`${usuario.name || 'Usuario'}`}
                    className="w-full h-full object-cover"
                    onError={() => setLoadFailed(true)}
                />
            ) : (
                <span className="text-xl font-black italic text-white">{initial}</span>
            )}
        </div>
    );
}

function RolesChips({ roles = [], maxVisible = 4 }) {
    const lista = roles || [];
    const visibles = lista.slice(0, maxVisible);
    const resto = lista.length - visibles.length;

    return (
        <div className="flex flex-wrap gap-1.5">
            {visibles.map((rol) => (
                <span
                    key={rol.id}
                    className="text-[8px] sm:text-[9px] font-black uppercase tracking-widest theme-element px-2 py-1 rounded-lg theme-text-main border theme-border"
                >
                    {rol.name}
                </span>
            ))}
            {resto > 0 && (
                <span className="text-[8px] font-black uppercase tracking-widest theme-text-muted px-2 py-1">
                    +{resto}
                </span>
            )}
        </div>
    );
}

function usuarioEsSuperAdmin(usuario) {
    return (usuario.roles || []).some((rol) => rol.name === 'Super Admin');
}

function usuarioEsAdministrador(usuario) {
    return (usuario.roles || []).some((rol) => rol.name === 'Administrador');
}

const HOVER_PRIMARIO = '[@media(hover:hover)_and_(pointer:fine)]:hover:bg-[var(--color-primario)] [@media(hover:hover)_and_(pointer:fine)]:hover:text-white [@media(hover:hover)_and_(pointer:fine)]:hover:border-[var(--color-primario)]';
const HOVER_AVISO = '[@media(hover:hover)_and_(pointer:fine)]:hover:bg-[var(--color-aviso)] [@media(hover:hover)_and_(pointer:fine)]:hover:text-white [@media(hover:hover)_and_(pointer:fine)]:hover:border-transparent';
const HOVER_EXITO = '[@media(hover:hover)_and_(pointer:fine)]:hover:bg-[var(--color-exito)] [@media(hover:hover)_and_(pointer:fine)]:hover:text-white [@media(hover:hover)_and_(pointer:fine)]:hover:border-transparent';

function detenerAccion(event) {
    event.stopPropagation();
}

function TarjetaUsuarioMobile({
    usuario, onEditar, onArchivar, onRestaurar, puedeArchivar, puedeRestaurar, usuarioActualId, esSuperAdmin = false, esCabezaEquipo = false, vistaArchivados = false,
    seleccionable = false, activa = false, onElegir,
}) {
    const mostrarArchivar = !vistaArchivados && puedeArchivar
        && usuario.id !== usuarioActualId
        && !usuarioEsSuperAdmin(usuario)
        && (esSuperAdmin || !usuarioEsAdministrador(usuario));
    const mostrarRestaurar = vistaArchivados && puedeRestaurar;
    const elegir = (event) => {
        if (!seleccionable || event.target.closest('button, a')) return;
        onElegir?.();
    };
    const teclado = (event) => {
        if (!seleccionable || event.target !== event.currentTarget) return;
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        onElegir?.();
    };
    return (
        <article
            className={`theme-surface border theme-border rounded-[1.75rem] p-4 sm:p-5 space-y-3 transition-shadow hover:shadow-md${seleccionable ? ' usuarios-fila-elegible' : ''}`}
            aria-selected={seleccionable ? activa : undefined}
            tabIndex={seleccionable ? 0 : undefined}
            onClick={elegir}
            onKeyDown={teclado}
        >
            <div className="flex items-start gap-3">
                <UserAvatar usuario={usuario} />
                <div className="min-w-0 flex-1 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="theme-text-main font-black text-sm uppercase italic tracking-tighter leading-snug break-words">
                            {nombreCompleto(usuario)}
                        </h3>
                        {esCabezaEquipo && (
                            <span className="text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded-lg border border-[var(--color-primario)] text-[var(--color-primario)]">
                                Gerente del equipo
                            </span>
                        )}
                    </div>
                    {usuario.username && (
                        <p className="text-[10px] font-bold theme-text-muted flex items-start gap-1.5 break-all">
                            <AtSign className="w-3.5 h-3.5 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                            <span>{usuario.username}</span>
                        </p>
                    )}
                    <p className="text-[10px] font-bold theme-text-muted flex items-start gap-1.5 break-all normal-case tracking-normal">
                        <Mail className="w-3.5 h-3.5 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                        <span>{usuario.email}</span>
                    </p>
                </div>
            </div>

            <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted flex items-start gap-2">
                <MapPin className="w-3.5 h-3.5 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                <span className="line-clamp-2 break-words min-w-0">
                    {usuario.departamentos?.map((d) => d.nombre).join(', ') || 'Sin departamento'}
                </span>
            </p>

            <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted flex items-start gap-2">
                <UserCog className="w-3.5 h-3.5 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                <span className="line-clamp-2 break-words min-w-0 normal-case tracking-normal">
                    Reporta a: {etiquetasGerentes(usuario)}
                </span>
            </p>

            <RolesChips roles={usuario.roles} maxVisible={3} />

            {vistaArchivados && usuario.deleted_at && (
                <p className="text-[10px] font-bold uppercase tracking-widest theme-text-aviso">
                    Archivado: {new Date(usuario.deleted_at).toLocaleString('es-MX')}
                </p>
            )}

            <div className="flex gap-2 pt-3 border-t theme-border">
                {!vistaArchivados && (
                <button
                    type="button"
                    onClick={(event) => {
                        detenerAccion(event);
                        onEditar(usuario, event);
                    }}
                    onKeyDown={detenerAccion}
                    className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl theme-element border theme-border theme-text-main text-[10px] font-black uppercase tracking-widest transition-colors ${HOVER_PRIMARIO}`}
                >
                    <Edit3 className="w-4 h-4 shrink-0" />
                    Editar
                </button>
                )}
                {mostrarArchivar && (
                    <button
                        type="button"
                        onClick={(event) => {
                            detenerAccion(event);
                            onArchivar(usuario);
                        }}
                        onKeyDown={detenerAccion}
                        className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl theme-element border theme-border theme-text-aviso text-[10px] font-black uppercase tracking-widest transition-colors ${HOVER_AVISO}`}
                    >
                        <Archive className="w-4 h-4 shrink-0" />
                        Archivar
                    </button>
                )}
                {mostrarRestaurar && (
                    <button
                        type="button"
                        onClick={(event) => {
                            detenerAccion(event);
                            onRestaurar(usuario);
                        }}
                        onKeyDown={detenerAccion}
                        className={`flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl theme-element border theme-border theme-text-exito text-[10px] font-black uppercase tracking-widest transition-colors ${HOVER_EXITO}`}
                    >
                        <RotateCcw className="w-4 h-4 shrink-0" />
                        Restaurar
                    </button>
                )}
            </div>
        </article>
    );
}

function FilaUsuarioDesktop({
    usuario, onEditar, onArchivar, onRestaurar, puedeArchivar, puedeRestaurar, usuarioActualId, esSuperAdmin = false, esCabezaEquipo = false, vistaArchivados = false,
    seleccionable = false, activa = false, onElegir,
}) {
    const mostrarArchivar = !vistaArchivados && puedeArchivar
        && usuario.id !== usuarioActualId
        && !usuarioEsSuperAdmin(usuario)
        && (esSuperAdmin || !usuarioEsAdministrador(usuario));
    const mostrarRestaurar = vistaArchivados && puedeRestaurar;
    return (
        <tr
            className={`border-b theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors${seleccionable ? ' usuarios-fila-elegible' : ''}`}
            aria-selected={seleccionable ? activa : undefined}
            tabIndex={seleccionable ? 0 : undefined}
            onClick={(event) => {
                if (!seleccionable || event.target.closest('button, a')) return;
                onElegir?.();
            }}
            onKeyDown={(event) => {
                if (!seleccionable || event.target !== event.currentTarget) return;
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                onElegir?.();
            }}
        >
            <td className="px-4 py-4">
                <div className="flex items-center gap-3 min-w-[200px]">
                    <UserAvatar usuario={usuario} />
                    <div className="min-w-0">
                        <p className="theme-text-main font-black text-sm uppercase italic tracking-tighter truncate">
                            {nombreCompleto(usuario)}
                        </p>
                        {usuario.username && (
                            <p className="text-[9px] font-bold theme-text-muted mt-0.5 truncate">@{usuario.username}</p>
                        )}
                        {esCabezaEquipo && (
                            <p className="text-[8px] font-black uppercase tracking-widest mt-1 text-[var(--color-primario)]">
                                Gerente del equipo
                            </p>
                        )}
                        {vistaArchivados && usuario.deleted_at && (
                            <p className="text-[8px] font-black uppercase tracking-widest mt-1 theme-text-aviso">
                                Archivado {new Date(usuario.deleted_at).toLocaleDateString('es-MX')}
                            </p>
                        )}
                    </div>
                </div>
            </td>
            <td className="px-4 py-4">
                <span className="text-[10px] font-bold theme-text-muted normal-case tracking-normal block truncate max-w-[220px]">
                    {usuario.email}
                </span>
            </td>
            <td className="px-4 py-4 hidden xl:table-cell">
                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted line-clamp-2 max-w-[180px]">
                    {usuario.departamentos?.map((d) => d.nombre).join(', ') || '—'}
                </span>
            </td>
            <td className="px-4 py-4 hidden xl:table-cell">
                <span className="text-[9px] font-bold theme-text-muted normal-case tracking-normal line-clamp-2 max-w-[180px]">
                    {etiquetasGerentes(usuario)}
                </span>
            </td>
            <td className="px-4 py-4 min-w-[160px]">
                <RolesChips roles={usuario.roles} maxVisible={2} />
            </td>
            <td className="px-4 py-4 text-right">
                <div className="flex justify-end gap-2">
                    {!vistaArchivados && (
                    <button
                        type="button"
                        onClick={(event) => {
                        detenerAccion(event);
                        onEditar(usuario, event);
                    }}
                    onKeyDown={detenerAccion}
                        className={`p-2.5 rounded-xl theme-element border theme-border theme-text-main transition-colors ${HOVER_PRIMARIO}`}
                        aria-label="Editar usuario"
                    >
                        <Edit3 className="w-4 h-4" />
                    </button>
                    )}
                    {mostrarArchivar && (
                        <button
                            type="button"
                            onClick={(event) => {
                            detenerAccion(event);
                            onArchivar(usuario);
                        }}
                        onKeyDown={detenerAccion}
                            className={`p-2.5 rounded-xl theme-element border theme-border theme-text-aviso transition-colors ${HOVER_AVISO}`}
                            aria-label="Archivar usuario"
                        >
                            <Archive className="w-4 h-4" />
                        </button>
                    )}
                    {mostrarRestaurar && (
                        <button
                            type="button"
                            onClick={(event) => {
                            detenerAccion(event);
                            onRestaurar(usuario);
                        }}
                        onKeyDown={detenerAccion}
                            className={`p-2.5 rounded-xl theme-element border theme-border theme-text-exito transition-colors ${HOVER_EXITO}`}
                            aria-label="Restaurar usuario"
                        >
                            <RotateCcw className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </td>
        </tr>
    );
}

function headersRecargaParcial(version) {
    return {
        'X-Inertia': 'true',
        'X-Inertia-Version': version ?? '',
        'X-Inertia-Partial-Component': 'Admin/Usuarios',
        'X-Inertia-Partial-Data': 'usuarios,filtros',
    };
}

function paramsListadoUsuarios({ busqueda, gerenteId, estado, page }) {
    const params = { page: page ?? 1 };
    const termino = (busqueda || '').trim();
    if (termino) params.busqueda = termino;
    if (gerenteId) params.gerente_id = Number(gerenteId);
    if (estado && estado !== 'activos') params.estado = estado;
    return params;
}

export default function Usuarios({
    auth,
    usuarios = { data: [], current_page: 1, last_page: 1, per_page: 12, total: 0, from: 0, to: 0 },
    filtros = { busqueda: '', gerente_id: null, estado: 'activos' },
    departamentos = [],
    posiblesGerentes = [],
    roles = [],
    rolesConfig = [],
    todosLosPermisos = [],
    catalogoPermisos = [],
    sexos = [],
    sucursales = [],
    esSuperAdmin = false,
    permisosUsuario = [],
}) {
    const { version: inertiaVersion } = usePage();
    const [usuariosState, setUsuariosState] = useState(usuarios);
    const [buscando, setBuscando] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const abortRef = useRef(null);

    useEffect(() => {
        setUsuariosState(usuarios);
    }, [usuarios]);

    const lista = usuariosState?.data ?? [];
    const busquedaInicial = filtros?.busqueda ?? '';
    const gerenteInicial = filtros?.gerente_id ? String(filtros.gerente_id) : '';
    const estadoInicial = filtros?.estado ?? 'activos';
    const debounceRef = useRef(null);
    const [busqueda, setBusqueda] = useState(busquedaInicial);
    const [gerenteId, setGerenteId] = useState(gerenteInicial);
    const [estado, setEstado] = useState(estadoInicial);

    const [errorBusqueda, setErrorBusqueda] = useState('');
    const [cuentaArchivo, setCuentaArchivo] = useState(null);
    const [motivoArchivo, setMotivoArchivo] = useState('');
    const [cuentaRestaurar, setCuentaRestaurar] = useState(null);
    const [errorDialogo, setErrorDialogo] = useState('');
    const [enviandoDialogo, setEnviandoDialogo] = useState(false);

    const puedeArchivar = esSuperAdmin || (auth?.user?.permissions || []).includes('usuarios.archivar');
    const puedeRestaurar = esSuperAdmin || (auth?.user?.permissions || []).includes('usuarios.restaurar');
    const vistaArchivados = estado === 'archivados';

    const mensajeErrorRestauracion = (errors) => {
        const texto = (valor) => (Array.isArray(valor) ? valor.filter(Boolean).join(' ') : (valor || ''));
        const partes = [texto(errors?.email), texto(errors?.username), texto(errors?.usuario), texto(errors?.error)].filter(Boolean);
        return partes.join('\n') || 'No se pudo restaurar la cuenta. Verifica que el correo y el nombre de usuario no estén en uso.';
    };

    const abrirArchivo = (usuario) => {
        setCuentaRestaurar(null);
        setCuentaArchivo(usuario);
        setMotivoArchivo('');
        setErrorDialogo('');
    };

    const confirmarArchivo = () => {
        const motivo = motivoArchivo.trim();
        if (motivo.length < 3) {
            setErrorDialogo('El motivo debe tener al menos 3 caracteres.');
            return;
        }
        setEnviandoDialogo(true);
        router.delete(route('admin.usuarios.archivar', cuentaArchivo.id), {
            data: { motivo },
            preserveScroll: true,
            onSuccess: () => setCuentaArchivo(null),
            onError: (errores) => {
                const motivo = Array.isArray(errores?.motivo) ? errores.motivo.join(' ') : errores?.motivo;
                const general = Array.isArray(errores?.error) ? errores.error.join(' ') : errores?.error;
                setErrorDialogo(motivo || general || 'No se pudo archivar la cuenta.');
            },
            onFinish: () => setEnviandoDialogo(false),
        });
    };

    const confirmarRestauracion = () => {
        setEnviandoDialogo(true);
        setErrorDialogo('');
        router.post(route('admin.usuarios.restaurar', cuentaRestaurar.id), {}, {
            preserveScroll: true,
            onSuccess: () => setCuentaRestaurar(null),
            onError: (errores) => setErrorDialogo(mensajeErrorRestauracion(errores)),
            onFinish: () => setEnviandoDialogo(false),
        });
    };

    const [showModal, setShowModal] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState(null);
    const [plantillaSeleccionada, setPlantillaSeleccionada] = useState('');
    const [plantillaPorPermiso, setPlantillaPorPermiso] = useState({});
    const [procedenciaActual, setProcedenciaActual] = useState({});
    const [incidencia, setIncidencia] = useState(null);
    const [overlayDatos, setOverlayDatos] = useState(null);
    const [ControlesDatos, setControlesDatos] = useState(null);
    const cierreTimer = useRef(null);
    const envioRef = useRef(false);
    const edicionRef = useRef(0);
    const retornoFoco = useRef(null);
    const paginaRef = useRef(null);
    const [lineaBase, setLineaBase] = useState(null);
    const [confirmacionGuardado, setConfirmacionGuardado] = useState(false);
    const [dialogoCambio, setDialogoCambio] = useState(null);
    const [dialogoCierre, setDialogoCierre] = useState(false);
    const [resultadosBusqueda, setResultadosBusqueda] = useState([]);
    const [buscandoSelector, setBuscandoSelector] = useState(false);
    const [hayMasSelector, setHayMasSelector] = useState(false);
    const [avisoSelector, setAvisoSelector] = useState('');
    const [anchoContenido, setAnchoContenido] = useState(1280);
    const [entradaConMovimiento, setEntradaConMovimiento] = useState(true);
    const [filtrosCompaneros, setFiltrosCompaneros] = useState({
        departamento: '',
        sucursal: '',
        area: '',
    });
    const destinoCambioRef = useRef(null);
    const confirmacionTimer = useRef(null);
    const selectorAbortRef = useRef(null);
    const selectorTimer = useRef(null);
    const usuarioEditandoRef = useRef(null);
    usuarioEditandoRef.current = usuarioEditando;

    const { data, setData, processing, isDirty, setDefaults, errors, setError, clearErrors } = useForm(formularioVacio());

    useEffect(() => () => {
        if (cierreTimer.current) clearTimeout(cierreTimer.current);
    }, []);

    useEffect(() => {
        setBusqueda(busquedaInicial);
    }, [busquedaInicial]);

    useEffect(() => {
        setGerenteId(gerenteInicial);
    }, [gerenteInicial]);

    useEffect(() => {
        setEstado(estadoInicial);
    }, [estadoInicial]);

    const recargarUsuarios = useCallback(async (params, { actualizarUrl } = {}) => {
        if (abortRef.current) abortRef.current.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        setBuscando(true);

        try {
            const response = await axios.get(route('admin.usuarios'), {
                params,
                headers: headersRecargaParcial(inertiaVersion),
                signal: controller.signal,
            });

            if (abortRef.current !== controller) return;

            setUsuariosState(response.data.props.usuarios);
            setErrorBusqueda('');

            if (actualizarUrl) {
                const url = new URL(window.location.href);
                if (params.busqueda) {
                    url.searchParams.set('busqueda', params.busqueda);
                } else {
                    url.searchParams.delete('busqueda');
                }
                if (params.gerente_id) {
                    url.searchParams.set('gerente_id', String(params.gerente_id));
                } else {
                    url.searchParams.delete('gerente_id');
                }
                if (params.estado && params.estado !== 'activos') {
                    url.searchParams.set('estado', params.estado);
                } else {
                    url.searchParams.delete('estado');
                }
                url.searchParams.set('page', String(params.page ?? 1));
                window.history.replaceState({}, '', url.pathname + url.search);
            }
        } catch (error) {
            if (abortRef.current !== controller) return;
            if (axios.isCancel(error) || error.code === 'ERR_CANCELED') return;

            console.error('Error al cargar usuarios:', error);
            setErrorBusqueda('No se pudo actualizar el listado. Intenta de nuevo.');
        } finally {
            if (abortRef.current === controller) {
                setBuscando(false);
            }
        }
    }, [inertiaVersion]);

    const buscarColaboradores = useCallback((texto = '') => {
        if (selectorTimer.current) clearTimeout(selectorTimer.current);
        const termino = String(texto ?? '');
        selectorTimer.current = setTimeout(async () => {
            if (selectorAbortRef.current) selectorAbortRef.current.abort();
            const controller = new AbortController();
            selectorAbortRef.current = controller;
            setBuscandoSelector(true);
            const params = { page: 1 };
            const consulta = termino.trim();
            if (consulta) params.busqueda = consulta;
            if (gerenteId) params.gerente_id = Number(gerenteId);
            if (estado && estado !== 'activos') params.estado = estado;
            if (filtrosCompaneros.departamento) params.departamento_id = Number(filtrosCompaneros.departamento);
            if (filtrosCompaneros.area) params.area_id = Number(filtrosCompaneros.area);
            if (filtrosCompaneros.sucursal) params.sucursal_id = Number(filtrosCompaneros.sucursal);

            try {
                const response = await axios.get(route('admin.usuarios'), {
                    params,
                    headers: headersRecargaParcial(inertiaVersion),
                    signal: controller.signal,
                });
                if (selectorAbortRef.current !== controller) return;
                const pagina = response.data?.props?.usuarios || {};
                const datos = pagina.data || [];
                const actual = usuarioEditandoRef.current;
                const hayFiltroOrg = Boolean(
                    filtrosCompaneros.departamento || filtrosCompaneros.area || filtrosCompaneros.sucursal,
                );
                const visible = actual && !consulta && !hayFiltroOrg && !datos.some((persona) => String(persona.id) === String(actual.id))
                    ? [actual, ...datos]
                    : datos;
                setResultadosBusqueda(visible);
                setHayMasSelector((pagina.last_page || 1) > 1);
                setAvisoSelector('');
            } catch (error) {
                if (selectorAbortRef.current !== controller) return;
                if (axios.isCancel(error) || error.code === 'ERR_CANCELED') return;
                setAvisoSelector('No se pudo buscar. Intenta de nuevo.');
                setHayMasSelector(false);
            } finally {
                if (selectorAbortRef.current === controller) setBuscandoSelector(false);
            }
        }, termino.trim() ? 300 : 0);
    }, [estado, filtrosCompaneros, gerenteId, inertiaVersion]);

    const aplicarFiltros = useCallback((overrides = {}) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            const nextBusqueda = overrides.busqueda !== undefined ? overrides.busqueda : busqueda;
            const nextGerente = overrides.gerenteId !== undefined ? overrides.gerenteId : gerenteId;
            const nextEstado = overrides.estado !== undefined ? overrides.estado : estado;
            recargarUsuarios(
                paramsListadoUsuarios({
                    busqueda: nextBusqueda,
                    gerenteId: nextGerente,
                    estado: nextEstado,
                    page: 1,
                }),
                { actualizarUrl: true },
            );
        }, overrides.inmediato ? 0 : 350);
    }, [busqueda, gerenteId, estado, recargarUsuarios]);

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > (usuariosState.last_page || 1)) return;
        recargarUsuarios(
            paramsListadoUsuarios({ busqueda, gerenteId, estado, page: pagina }),
            { actualizarUrl: true },
        ).then(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    };

    const departamentosVista = overlayDatos?.departamentos ?? departamentos;
    const sucursalesVista = overlayDatos?.sucursales ?? sucursales;
    const rolesVista = overlayDatos?.roles ?? roles;
    const posiblesGerentesVista = overlayDatos?.posiblesGerentes ?? posiblesGerentes;
    const sexosVista = overlayDatos?.sexos ?? sexos;
    const todosLosPermisosVista = overlayDatos?.todosLosPermisos ?? todosLosPermisos;
    const catalogoPermisosVista = overlayDatos?.catalogoPermisos ?? catalogoPermisos;
    const listaVisible = overlayDatos ? (overlayDatos.usuarios ?? []) : lista;
    const rolesJerarquia = (rolesVista || []).filter(rol => rol?.name && !rol.name.includes('Grupo:'));
    const rolesGrupos = (rolesVista || []).filter(rol => rol?.name?.includes('Grupo:'));

    // --- MANEJADORES DE EVENTOS ---
    const abrirModal = (usuario = null, evento = null) => {
        const origen = evento?.currentTarget || document.activeElement;
        if (!origen?.closest?.('.usuarios-drawer')) {
            retornoFoco.current = origen;
            setEntradaConMovimiento(evento ? evento.detail !== 0 : true);
        } else {
            setEntradaConMovimiento(false);
        }
        if (cierreTimer.current) {
            clearTimeout(cierreTimer.current);
            cierreTimer.current = null;
        }
        edicionRef.current += 1;
        soltarEnvio(envioRef);
        setGuardando(false);
        setIncidencia(null);
        clearErrors();

        const datos = datosFormularioDesdeUsuario(usuario);
        const procMap = {};
        (usuario?.permisos_procedencia || []).forEach((meta) => {
            if (meta.permiso) procMap[meta.permiso] = meta;
        });

        setUsuarioEditando(usuario);
        setPlantillaSeleccionada('');
        setPlantillaPorPermiso(datos.plantilla_por_permiso);
        setProcedenciaActual(procMap);
        setData(datos);
        setDefaults(datos);
        setLineaBase({ ...datos, plantilla_origen: datos.plantilla_origen || '' });
        setShowModal(true);
    };

    const cerrarModal = ({ forzar = false } = {}) => {
        if (envioRef.current) return;
        if (!forzar && debeConfirmarCierre(isDirty)) {
            setDialogoCierre(true);
            return;
        }
        setDialogoCierre(false);
        setDialogoCambio(null);
        destinoCambioRef.current = null;
        setConfirmacionGuardado(false);
        setFiltrosCompaneros({ departamento: '', sucursal: '', area: '' });
        setResultadosBusqueda([]);
        setShowModal(false);
        const destino = retornoFoco.current;
        requestAnimationFrame(() => destino?.focus?.());
        const ticketAlCerrar = edicionRef.current;
        cierreTimer.current = setTimeout(() => {
            if (edicionRef.current !== ticketAlCerrar) return;
            const vacio = formularioVacio();
            setData(vacio);
            setDefaults(vacio);
            setUsuarioEditando(null);
            setPlantillaSeleccionada('');
            setPlantillaPorPermiso({});
            setProcedenciaActual({});
            setIncidencia(null);
            clearErrors();
            cierreTimer.current = null;
        }, 300);
    };

    const cambiarColaborador = (usuario, evento = null) => {
        if (!usuario) return;
        const decision = decidirCambioColaborador({
            mismo: usuario.id === usuarioEditando?.id,
            sucio: isDirty,
            pendiente: envioRef.current,
        });
        if (decision.accion === 'bloquear' || decision.accion === 'ignorar') return;
        if (decision.accion === 'preguntar') {
            setDialogoCambio(usuario);
            return;
        }
        abrirModal(usuario, evento);
    };

    const toggleSelection = (campo, idOrName) => {
        setData((prev) => alternarRelacion(prev, campo, idOrName));
    };

    const areasSeleccionadas = (departamentosVista || [])
        .flatMap((depto) => depto.areas || [])
        .filter((area) => (data.areas || []).includes(area.id));

    const departamentosSeleccionados = (departamentosVista || [])
        .filter((depto) => (data.departamentos || []).includes(depto.id));

    const sucursalesSeleccionadas = (sucursalesVista || [])
        .filter((sucursal) => (data.sucursales || []).includes(sucursal.id));

    const aplicarPlantilla = (nombreGrupo) => {
        const resultado = aplicarPlantillaPermisos({
            permisos: data.permisos_individuales || [],
            plantillaActual: plantillaSeleccionada,
            plantillaNueva: nombreGrupo,
            mapa: plantillaPorPermiso,
            roles: rolesVista,
        });
        setPlantillaSeleccionada(resultado.plantillaSeleccionada);
        setPlantillaPorPermiso(resultado.mapa);
        setData((prev) => ({
            ...prev,
            permisos_individuales: resultado.permisos,
            plantilla_origen: resultado.plantilla_origen,
            plantilla_por_permiso: resultado.mapa,
        }));
    };

    const setPermisosIndividualesViaForm = (valor) => {
        setData((prev) => {
            const actuales = prev.permisos_individuales || [];
            const nuevos = typeof valor === 'function' ? valor(actuales) : valor;
            return {
                ...prev,
                permisos_individuales: nuevos,
                plantilla_por_permiso: limpiarMapaPlantilla(prev.plantilla_por_permiso, nuevos),
            };
        });
        if (typeof valor !== 'function') {
            setPlantillaPorPermiso((prev) => limpiarMapaPlantilla(prev, valor));
        }
    };

    const permisosFormData = {
        ...data,
        plantillas_activas: plantillaSeleccionada ? [plantillaSeleccionada] : [],
        plantilla_por_permiso: plantillaPorPermiso,
    };

    const aplicarDecisionGuardado = (decision, extra = {}) => {
        if (!decision.aplicar) return decision.detenerInertia ? false : undefined;
        if (decision.tipo === 'validacion') {
            setIncidencia({ status: 422, tipo: 'validacion', mensaje: '' });
            setError(normalizarErrores(extra.errores));
            return undefined;
        }
        if (decision.tipo === 'red' || decision.tipo === 'http') {
            const mensaje = decision.tipo === 'red'
                ? mensajeErrorRed()
                : mensajeErrorHttp(decision.status);
            setIncidencia({ status: decision.status, tipo: decision.tipo, mensaje });
            setError('formulario', mensaje);
            if (decision.publicar) {
                publicarIncidenciaGuardado({
                    status: decision.status,
                    tipo: decision.tipo,
                    mensaje,
                });
            }
            return decision.detenerInertia ? false : undefined;
        }
        return undefined;
    };

    const handleSubmit = (e) => {
        e?.preventDefault?.();
        if (!tomarEnvio(envioRef)) return;

        let permisosLimpios = deduplicarPermisos(data.permisos_individuales);
        if (!esSuperAdmin && usuarioEditando) {
            const usuarioActualId = auth?.user?.id;
            const intocables = (usuarioEditando.permissions || [])
                .map((p) => p.name)
                .filter((p) => !(permisosUsuario || []).includes(p));
            const protegidos = (usuarioEditando.permisos_procedencia || [])
                .filter((proc) => proc.permiso && permisoProtegidoParaEditor(proc, usuarioActualId, false))
                .map((proc) => proc.permiso);
            const gestionados = permisosLimpios.filter(
                (p) => (permisosUsuario || []).includes(p) && !protegidos.includes(p)
            );
            permisosLimpios = deduplicarPermisos([...intocables, ...protegidos, ...gestionados]);
        }

        clearErrors();
        setIncidencia(null);
        setConfirmacionGuardado(false);
        if (confirmacionTimer.current) {
            clearTimeout(confirmacionTimer.current);
            confirmacionTimer.current = null;
        }
        const areaPrincipalId = resolverPrincipalFormulario(data.areas, data.area_id);
        const departamentoPrincipalId = resolverPrincipalFormulario(data.departamentos, data.departamento_id);
        const sucursalPrincipalId = resolverPrincipalFormulario(data.sucursales, data.sucursal_principal_id);
        const rechazarLocal = (campo, mensaje) => {
            soltarEnvio(envioRef);
            destinoCambioRef.current = null;
            setError(campo, mensaje);
        };
        if ((data.areas || []).length > 1 && !areaPrincipalId) {
            rechazarLocal('area_id', 'Selecciona el área principal cuando el colaborador tiene varias áreas asignadas.');
            return;
        }
        if ((data.departamentos || []).length > 1 && !departamentoPrincipalId) {
            rechazarLocal('departamento_id', 'Selecciona el departamento principal cuando el colaborador tiene varios departamentos asignados.');
            return;
        }
        if ((data.sucursales || []).length > 1 && !sucursalPrincipalId) {
            rechazarLocal('sucursal_principal_id', 'Selecciona la sucursal principal cuando el colaborador tiene varias sucursales asignadas.');
            return;
        }

        const payload = omitirRelacionesFueraDeCatalogo({
            ...data,
            area_id: areaPrincipalId || null,
            departamento_id: departamentoPrincipalId || null,
            sucursal_principal_id: sucursalPrincipalId || null,
            permisos_individuales: permisosLimpios,
            plantilla_origen: plantillaSeleccionada || null,
            plantilla_por_permiso: plantillaPorPermiso,
        }, {
            esAdministracionGlobal: esAdministracionGlobal(auth, esSuperAdmin),
            departamentos: departamentosVista,
            sucursales: sucursalesVista,
        });

        const ticket = edicionRef.current;
        setGuardando(true);
        const opciones = {
            preserveState: true,
            preserveScroll: true,
            // El botón del drawer ya informa el envío. AppLayout omite el loader si la visita lo pide.
            showProgress: false,
            onSuccess: (page) => {
                const decision = decidirRespuestaGuardado({ ticket, ticketActual: edicionRef.current, status: 200 });
                if (decision.tipo !== 'ok' || !decision.aplicar) return;
                const destino = destinoCambioRef.current;
                destinoCambioRef.current = null;
                if (destino) {
                    abrirModal(destino);
                    return;
                }
                if (!usuarioEditando) {
                    const listaNueva = page?.props?.usuarios?.data || [];
                    const creado = listaNueva.find((persona) => persona.username === payload.username || persona.email === payload.email);
                    if (creado) {
                        abrirModal(creado);
                        return;
                    }
                }
                const confirmado = {
                    ...payload,
                    plantilla_origen: payload.plantilla_origen || '',
                };
                setDefaults(confirmado);
                setLineaBase(confirmado);
                setUsuarioEditando((actual) => (actual ? {
                    ...actual,
                    name: payload.name,
                    apellido_paterno: payload.apellido_paterno,
                    apellido_materno: payload.apellido_materno,
                    username: payload.username,
                    email: payload.email,
                } : actual));
                setConfirmacionGuardado(true);
                confirmacionTimer.current = setTimeout(() => setConfirmacionGuardado(false), 1600);
            },
            onError: (errores) => {
                destinoCambioRef.current = null;
                const decision = decidirRespuestaGuardado({ ticket, ticketActual: edicionRef.current, status: 422 });
                aplicarDecisionGuardado(decision, { errores });
            },
            onHttpException: (response) => {
                destinoCambioRef.current = null;
                const status = response?.status ?? 0;
                if (status === 422) {
                    const decision = decidirRespuestaGuardado({ ticket, ticketActual: edicionRef.current, status: 422 });
                    if (!decision.aplicar) return false;
                    return undefined;
                }
                const decision = decidirRespuestaGuardado({ ticket, ticketActual: edicionRef.current, status });
                return aplicarDecisionGuardado(decision);
            },
            onNetworkError: () => {
                destinoCambioRef.current = null;
                const decision = decidirRespuestaGuardado({ ticket, ticketActual: edicionRef.current, red: true });
                return aplicarDecisionGuardado(decision);
            },
            onFinish: () => {
                if (ticket !== edicionRef.current) return;
                soltarEnvio(envioRef);
                setGuardando(false);
            },
        };

        if (usuarioEditando) {
            router.put(route('admin.usuarios.update', usuarioEditando.id), payload, opciones);
        } else {
            router.post(route('admin.usuarios.store'), payload, opciones);
        }
    };

    const cambiarModoDatos = async (modo) => {
        if (!import.meta.env.DEV) return;
        const url = new URL(window.location.href);
        if (!modo || modo === 'demo') {
            url.searchParams.delete('datos');
            window.history.replaceState({}, '', url.pathname + url.search);
            setOverlayDatos(null);
            return;
        }
        url.searchParams.set('datos', modo);
        window.history.replaceState({}, '', url.pathname + url.search);
        const modulo = await import('./Partials/formularioUsuarioExtremo.jsx');
        const overlay = { ...modulo.overlayDatos(modo), modo };
        setOverlayDatos(overlay);
        if (overlay.usuario) abrirModal(overlay.usuario);
        else abrirModal(null);
    };

    useEffect(() => {
        if (!import.meta.env.DEV) return undefined;
        let activo = true;
        import('./Partials/formularioUsuarioExtremo.jsx').then((modulo) => {
            if (activo) setControlesDatos(() => modulo.ControlesDatosExtremos);
        });
        const modo = new URLSearchParams(window.location.search).get('datos');
        if (modo && modo !== 'demo') {
            import('./Partials/formularioUsuarioExtremo.jsx').then((modulo) => {
                if (!activo) return;
                setOverlayDatos({ ...modulo.overlayDatos(modo), modo });
            });
        }
        return () => {
            activo = false;
        };
    }, []);

    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6');
    const cardListado = geliaCardClass('overflow-hidden');
    const procesandoForm = processing || guardando;
    const gerenteFiltroId = gerenteId ? Number(gerenteId) : null;
    const modoDrawer = modoDrawerColaborador(anchoContenido);
    const filaSeleccionable = showModal && modoDrawer === 'lateral' && !vistaArchivados;
    const editarCuenta = (usuario, evento) => {
        if (filaSeleccionable && usuarioEditando) cambiarColaborador(usuario, evento);
        else abrirModal(usuario, evento);
    };
    const anchoDrawer = anchoDrawerColaborador(anchoContenido);

    useEffect(() => {
        const main = document.querySelector('.gelia-app-main');
        if (!main) return undefined;
        const leer = () => setAnchoContenido(main.clientWidth);
        leer();
        const observador = new ResizeObserver(leer);
        observador.observe(main);
        return () => observador.disconnect();
    }, []);

    useEffect(() => {
        if (!showModal || !usuarioEditando) return undefined;
        buscarColaboradores('');
        return () => {
            if (selectorTimer.current) clearTimeout(selectorTimer.current);
        };
    }, [showModal, usuarioEditando?.id, filtrosCompaneros, estado, gerenteId, buscarColaboradores]);

    useEffect(() => {
        if (!showModal || isDirty || !usuarioEditando) return;
        const fresco = (usuarios?.data || []).find((persona) => persona.id === usuarioEditando.id);
        if (!fresco) return;
        setUsuarioEditando((actual) => (actual && actual.id === fresco.id ? { ...actual, ...fresco } : actual));
    }, [usuarios, showModal, isDirty, usuarioEditando?.id]);

    useEffect(() => {
        if (!dialogoCambio && !dialogoCierre) return undefined;
        const onKey = (event) => {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            event.stopPropagation();
            setDialogoCambio(null);
            setDialogoCierre(false);
        };
        window.addEventListener('keydown', onKey, true);
        return () => window.removeEventListener('keydown', onKey, true);
    }, [dialogoCambio, dialogoCierre]);

    useEffect(() => () => {
        if (confirmacionTimer.current) clearTimeout(confirmacionTimer.current);
        if (selectorAbortRef.current) selectorAbortRef.current.abort();
    }, []);

    useEffect(() => {
        const pagina = paginaRef.current;
        if (!pagina) return undefined;
        if (showModal && modoDrawer === 'superpuesto') pagina.setAttribute('inert', '');
        else pagina.removeAttribute('inert');
        return () => pagina.removeAttribute('inert');
    }, [showModal, modoDrawer]);

    return (
        <AppLayout auth={auth}>
            <Head title="Directorio de Usuarios" />

            <div
                ref={paginaRef}
                className={`max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8 ${showModal && modoDrawer === 'lateral' ? 'usuarios-pagina--con-drawer' : ''}`}
                style={showModal && anchoDrawer ? { marginInlineEnd: `${anchoDrawer}px` } : undefined}
            >
                <header className={cardHeader}>
                    <div className="min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Administración
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0 flex items-center gap-3 flex-wrap">
                            <Users className="w-7 h-7 md:w-9 md:h-9 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            Directorio y <span style={{ color: 'var(--color-primario)' }}>Accesos</span>
                        </h1>
                        <p className="theme-text-muted text-[10px] font-bold uppercase tracking-widest mt-2 max-w-xl">
                            Gestión de personal, identidad y permisos por módulo (p. ej. Gestión de pedidos por subpanel)
                            {usuariosState.total > 0 && (
                                <span className="block sm:inline sm:ml-2 mt-1 sm:mt-0 opacity-80">
                                    · {usuariosState.total.toLocaleString('es-MX')} colaboradores
                                </span>
                            )}
                        </p>
                    </div>

                    <div className="flex flex-col gap-3 w-full lg:w-auto lg:max-w-2xl shrink-0">
                        <div className="flex flex-col sm:flex-row gap-3">
                            <div className="theme-field-with-icon flex-1 min-w-0">
                                {buscando ? (
                                    <div className="theme-field-icon flex items-center justify-center">
                                        <div className="w-3.5 h-3.5 rounded-full border-2 border-[var(--color-primario)] border-t-transparent animate-spin" />
                                    </div>
                                ) : (
                                    <Search className="theme-field-icon" aria-hidden />
                                )}
                                <input
                                    type="search"
                                    placeholder="Buscar por nombre, correo o usuario..."
                                    className="theme-input w-full pr-4 py-3 text-[11px] font-bold normal-case tracking-normal placeholder:uppercase placeholder:tracking-wider placeholder:text-[10px]"
                                    value={busqueda}
                                    onChange={(e) => {
                                        setBusqueda(e.target.value);
                                        aplicarFiltros({ busqueda: e.target.value });
                                    }}
                                />
                            </div>
                            <button
                                type="button"
                                onClick={(event) => abrirModal(null, event)}
                                className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact shrink-0`}
                                disabled={vistaArchivados}
                            >
                                <UserPlus className="w-4 h-4" /> Nuevo ingreso
                            </button>
                        </div>
                        {errorBusqueda && (
                            <p role="alert" className="text-xs font-bold theme-text-peligro normal-case tracking-normal">
                                {errorBusqueda}
                            </p>
                        )}
                        <div className="theme-field-with-icon w-full">
                            <UserCog className="theme-field-icon" aria-hidden />
                            <select
                                className="theme-input w-full pr-4 py-3 text-[11px] font-bold normal-case tracking-normal"
                                value={gerenteId}
                                onChange={(e) => {
                                    const valor = e.target.value;
                                    setGerenteId(valor);
                                    aplicarFiltros({ gerenteId: valor, inmediato: true });
                                }}
                                aria-label="Filtrar por gerente y su equipo"
                            >
                                <option value="">Todos los equipos (gerente → colaboradores)</option>
                                {(posiblesGerentes || []).map((g) => (
                                    <option key={g.id} value={String(g.id)}>
                                        Equipo de {nombreCortoPersona(g)}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </header>

                {esSuperAdmin && (
                    <ConfiguracionHerencia
                        rolesConfig={rolesConfig}
                        todosLosPermisos={todosLosPermisos}
                        esSuperAdmin={esSuperAdmin}
                    />
                )}

                {(puedeArchivar || puedeRestaurar) && (
                    <div className="flex gap-2 mb-4">
                        <button
                            type="button"
                            onClick={() => {
                                setEstado('activos');
                                aplicarFiltros({ estado: 'activos', inmediato: true });
                            }}
                            className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-colors ${
                                estado === 'activos'
                                    ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[var(--color-primario)]/10'
                                    : 'theme-border theme-text-muted'
                            }`}
                        >
                            Activos
                        </button>
                        {puedeRestaurar && (
                            <button
                                type="button"
                                onClick={() => {
                                    setEstado('archivados');
                                    aplicarFiltros({ estado: 'archivados', inmediato: true });
                                }}
                                className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-colors ${
                                    estado === 'archivados'
                                        ? 'border-[var(--color-aviso)] text-[var(--color-aviso)] bg-[var(--color-aviso)]/10'
                                        : 'theme-border theme-text-muted'
                                }`}
                            >
                                Archivados
                            </button>
                        )}
                    </div>
                )}

                <div className={`lg:hidden space-y-3 transition-opacity duration-200 ${buscando ? 'opacity-50 pointer-events-none' : 'opacity-100'}`}>
                    {listaVisible.length === 0 ? (
                        <div className={`${geliaCardClass()} text-center py-14 px-6 border-dashed`}>
                            <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                            <h3 className="text-base font-black italic uppercase theme-text-main">Sin resultados</h3>
                            <p className="text-[10px] font-bold theme-text-muted mt-2 uppercase tracking-widest">
                                {vistaArchivados ? 'No hay colaboradores archivados con estos filtros' : 'Ajusta la búsqueda o registra un nuevo colaborador'}
                            </p>
                        </div>
                    ) : (
                        listaVisible.map((usuario) => (
                            <TarjetaUsuarioMobile
                                key={usuario.id}
                                usuario={usuario}
                                onEditar={editarCuenta}
                                seleccionable={filaSeleccionable}
                                activa={usuarioEditando?.id === usuario.id}
                                onElegir={() => cambiarColaborador(usuario)}
                                onArchivar={abrirArchivo}
                                onRestaurar={(usuario) => {
                                    setCuentaArchivo(null);
                                    setCuentaRestaurar(usuario);
                                    setErrorDialogo('');
                                }}
                                puedeArchivar={puedeArchivar}
                                puedeRestaurar={puedeRestaurar}
                                usuarioActualId={auth?.user?.id}
                                esSuperAdmin={esSuperAdmin}
                                esCabezaEquipo={gerenteFiltroId != null && Number(usuario.id) === gerenteFiltroId}
                                vistaArchivados={vistaArchivados}
                            />
                        ))
                    )}
                    {listaVisible.length > 0 && !overlayDatos && (
                        <GeliaPaginacion paginator={usuariosState} onIrAPagina={irAPagina} />
                    )}
                </div>

                <div className={`hidden lg:block ${cardListado} transition-opacity duration-200 ${buscando ? 'opacity-50 pointer-events-none' : 'opacity-100'}`}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left min-w-[720px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <th className="px-4 py-4">Colaborador</th>
                                    <th className="px-4 py-4">Correo</th>
                                    <th className="px-4 py-4 hidden xl:table-cell">Departamentos</th>
                                    <th className="px-4 py-4 hidden xl:table-cell">Reporta a</th>
                                    <th className="px-4 py-4">Roles</th>
                                    <th className="px-4 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {listaVisible.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-16 text-center">
                                            <Users className="w-10 h-10 theme-text-muted mx-auto mb-3 opacity-50" />
                                            <p className="font-black italic uppercase theme-text-main text-sm">Sin resultados</p>
                                            <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase tracking-widest">
                                                {vistaArchivados ? 'No hay colaboradores archivados con estos filtros' : 'Ajusta la búsqueda o crea un nuevo ingreso'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    listaVisible.map((usuario) => (
                                        <FilaUsuarioDesktop
                                            key={usuario.id}
                                            usuario={usuario}
                                            onEditar={editarCuenta}
                                            seleccionable={filaSeleccionable}
                                            activa={usuarioEditando?.id === usuario.id}
                                            onElegir={() => cambiarColaborador(usuario)}
                                            onArchivar={abrirArchivo}
                                            onRestaurar={(usuario) => {
                                                setCuentaArchivo(null);
                                                setCuentaRestaurar(usuario);
                                                setErrorDialogo('');
                                            }}
                                            puedeArchivar={puedeArchivar}
                                            puedeRestaurar={puedeRestaurar}
                                            usuarioActualId={auth?.user?.id}
                                            esSuperAdmin={esSuperAdmin}
                                            esCabezaEquipo={gerenteFiltroId != null && Number(usuario.id) === gerenteFiltroId}
                                            vistaArchivados={vistaArchivados}
                                        />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    {listaVisible.length > 0 && !overlayDatos && (
                        <GeliaPaginacion paginator={usuariosState} onIrAPagina={irAPagina} embedded />
                    )}
                </div>

                {(cuentaArchivo || cuentaRestaurar) && createPortal(
                    <div
                        className={`${THEME_MODAL_OVERLAY} items-center`}
                        onClick={() => {
                            if (enviandoDialogo) return;
                            setCuentaArchivo(null);
                            setCuentaRestaurar(null);
                        }}
                    >
                        <div
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="dialogo-cuenta-titulo"
                            className={`${THEME_MODAL_SHELL} max-w-md w-full min-w-0 text-left`}
                            onClick={(event) => event.stopPropagation()}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape' && !enviandoDialogo) {
                                    setCuentaArchivo(null);
                                    setCuentaRestaurar(null);
                                }
                            }}
                        >
                            <div className="gelia-modal-body min-w-0 p-6">
                                <h2 id="dialogo-cuenta-titulo" className="text-lg font-black theme-text-main m-0">
                                    {cuentaArchivo ? 'Archivar cuenta' : 'Restaurar cuenta'}
                                </h2>
                                <p className="theme-text-muted text-sm mt-2 mb-4 min-w-0 [overflow-wrap:anywhere] normal-case tracking-normal">
                                    {cuentaArchivo
                                        ? `Se archivará la cuenta de ${nombreCompleto(cuentaArchivo)}. El motivo queda en la auditoría. Correo y teléfono quedan libres para reasignación.`
                                        : `Se restaurará la cuenta de ${nombreCompleto(cuentaRestaurar)}. Las credenciales de acceso vuelven a estar activas.`}
                                </p>
                                {cuentaArchivo && (
                                    <label className="block space-y-1.5">
                                        <span className="text-xs font-bold theme-text-muted">Motivo</span>
                                        <textarea
                                            autoFocus
                                            value={motivoArchivo}
                                            onChange={(event) => setMotivoArchivo(event.target.value)}
                                            rows={3}
                                            maxLength={1000}
                                            className="theme-input w-full text-sm font-medium normal-case tracking-normal"
                                        />
                                    </label>
                                )}
                                {errorDialogo && (
                                    <p role="alert" className="text-sm font-bold theme-text-peligro mt-3 min-w-0 whitespace-pre-line [overflow-wrap:anywhere] normal-case tracking-normal">
                                        {errorDialogo}
                                    </p>
                                )}
                            </div>
                            <div className="gelia-modal-footer flex flex-wrap justify-end gap-2 p-6">
                                <button
                                    type="button"
                                    className="theme-btn-secondary"
                                    disabled={enviandoDialogo}
                                    onClick={() => {
                                        setCuentaArchivo(null);
                                        setCuentaRestaurar(null);
                                    }}
                                >
                                    Cancelar
                                </button>
                                {cuentaArchivo ? (
                                    <button
                                        type="button"
                                        className="theme-btn-danger"
                                        disabled={enviandoDialogo}
                                        onClick={confirmarArchivo}
                                    >
                                        Archivar cuenta
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}
                                        disabled={enviandoDialogo}
                                        autoFocus
                                        onClick={confirmarRestauracion}
                                    >
                                        Restaurar cuenta
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>,
                    document.body
                )}

                {(dialogoCambio || dialogoCierre) && createPortal(
                    <div
                        className={`${THEME_MODAL_OVERLAY} items-center usuarios-dialogo-decision`}
                        onClick={() => {
                            setDialogoCambio(null);
                            setDialogoCierre(false);
                        }}
                    >
                        <div
                            role="alertdialog"
                            aria-modal="true"
                            aria-labelledby="dialogo-cambios-titulo"
                            aria-describedby="dialogo-cambios-texto"
                            className={`${THEME_MODAL_SHELL} max-w-md w-full min-w-0 text-left`}
                            onClick={(event) => event.stopPropagation()}
                        >
                            <div className="gelia-modal-body min-w-0 p-6">
                                <h2 id="dialogo-cambios-titulo" className="text-lg font-black theme-text-main m-0">
                                    {dialogoCambio ? 'Cambiar de colaborador' : 'Cerrar sin guardar'}
                                </h2>
                                <p id="dialogo-cambios-texto" className="theme-text-muted text-sm mt-2 mb-0 normal-case tracking-normal">
                                    {dialogoCambio
                                        ? 'Hay cambios sin guardar en esta cuenta.'
                                        : 'Si cierras ahora, los cambios de esta cuenta se perderán.'}
                                </p>
                            </div>
                            <div className="gelia-modal-footer usuarios-dialogo-acciones">
                                <button
                                    type="button"
                                    className={THEME_BTN_SECONDARY}
                                    autoFocus
                                    onClick={() => {
                                        setDialogoCambio(null);
                                        setDialogoCierre(false);
                                    }}
                                >
                                    Seguir editando
                                </button>
                                {dialogoCambio ? (
                                    <>
                                        <button
                                            type="button"
                                            className={THEME_BTN_SECONDARY}
                                            onClick={() => {
                                                const destino = dialogoCambio;
                                                setDialogoCambio(null);
                                                abrirModal(destino);
                                            }}
                                        >
                                            Descartar y cambiar
                                        </button>
                                        <button
                                            type="button"
                                            className={THEME_BTN_PRIMARY}
                                            onClick={() => {
                                                if (!dialogoCambio || envioRef.current) return;
                                                destinoCambioRef.current = dialogoCambio;
                                                setDialogoCambio(null);
                                                handleSubmit();
                                            }}
                                        >
                                            Guardar y cambiar
                                        </button>
                                    </>
                                ) : (
                                    <button
                                        type="button"
                                        className="theme-btn-danger"
                                        onClick={() => cerrarModal({ forzar: true })}
                                    >
                                        Descartar cambios
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>,
                    document.body
                )}
                <DrawerColaborador
                    abierto={showModal}
                    modo={modoDrawer}
                    onCerrar={cerrarModal}
                    movimiento={entradaConMovimiento}
                    sucio={isDirty}
                    clave={usuarioEditando?.id ?? 'nuevo'}
                    ancho={anchoDrawer}
                >
                    <ModalUsuarioForm
                        key={usuarioEditando?.id ?? 'nuevo'}
                        usuarioEditando={usuarioEditando}
                        onCerrar={cerrarModal}
                        onSubmit={handleSubmit}
                        processing={procesandoForm}
                        data={data}
                        setData={setData}
                        errors={errors}
                        incidencia={incidencia}
                        departamentos={departamentosVista}
                        sucursales={sucursalesVista}
                        posiblesGerentes={posiblesGerentesVista}
                        rolesJerarquia={rolesJerarquia}
                        rolesGrupos={rolesGrupos}
                        plantillaSeleccionada={plantillaSeleccionada}
                        aplicarPlantilla={aplicarPlantilla}
                        permisosFormData={permisosFormData}
                        setPermisosIndividualesViaForm={setPermisosIndividualesViaForm}
                        roles={rolesVista}
                        todosLosPermisos={todosLosPermisosVista}
                        catalogoPermisos={catalogoPermisosVista}
                        permisosUsuario={permisosUsuario}
                        esSuperAdmin={esSuperAdmin}
                        usuarioActualId={auth?.user?.id}
                        procedenciaActual={procedenciaActual}
                        setPlantillaPorPermiso={setPlantillaPorPermiso}
                        sexos={sexosVista}
                        toggleSelection={toggleSelection}
                        areasSeleccionadas={areasSeleccionadas}
                        departamentosSeleccionados={departamentosSeleccionados}
                        sucursalesSeleccionadas={sucursalesSeleccionadas}
                        resolverAreaPrincipalFormulario={resolverPrincipalFormulario}
                        resolverPrincipalFormulario={resolverPrincipalFormulario}
                        lineaBase={lineaBase}
                        companeros={listaVisible}
                        onCambiarColaborador={cambiarColaborador}
                        filtroDepartamento={filtrosCompaneros.departamento}
                        filtroSucursal={filtrosCompaneros.sucursal}
                        filtroArea={filtrosCompaneros.area}
                        onCambiarFiltro={(campo, valor) => setFiltrosCompaneros((previo) => ({ ...previo, [campo]: valor }))}
                        onAplicarFiltros={(filtros) => setFiltrosCompaneros({
                            departamento: filtros?.departamento || '',
                            sucursal: filtros?.sucursal || '',
                            area: filtros?.area || '',
                        })}
                        resultadosBusqueda={resultadosBusqueda}
                        buscandoBusqueda={buscandoSelector}
                        hayMasResultados={hayMasSelector}
                        avisoBusqueda={avisoSelector}
                        onBuscarColaboradores={buscarColaboradores}
                        confirmado={confirmacionGuardado}
                    />
                </DrawerColaborador>
                {ControlesDatos && (
                    <ControlesDatos modo={overlayDatos?.modo ?? 'demo'} onCambiar={cambiarModoDatos} />
                )}
            </div>
        </AppLayout>
    );
}