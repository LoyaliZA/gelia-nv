import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { createPortal } from 'react-dom';
import axios from 'axios';

import {
    Users, UserPlus, Search, Edit3, Archive,
    MapPin, Mail, AtSign, UserCog,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import ModalUsuarioForm from './Partials/ModalUsuarioForm';
import ConfiguracionHerencia from './Partials/ConfiguracionHerencia';
import {
    deduplicarPermisos,
    permisosDePlantilla,
    permisoProtegidoParaEditor,
} from '../../utils/permisos';
import { geliaCardClass, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '../../utils/geliaTheme';

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

function TarjetaUsuarioMobile({ usuario, onEditar, onArchivar, puedeArchivar, usuarioActualId, esCabezaEquipo = false }) {
    const mostrarArchivar = puedeArchivar
        && usuario.id !== usuarioActualId
        && !usuarioEsSuperAdmin(usuario);
    return (
        <article className="fade-in-user theme-surface border theme-border rounded-[1.75rem] p-4 sm:p-5 space-y-3 transition-shadow hover:shadow-md">
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

            <div className="flex gap-2 pt-3 border-t theme-border">
                <button
                    type="button"
                    onClick={() => onEditar(usuario)}
                    className="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl theme-element border theme-border theme-text-main text-[10px] font-black uppercase tracking-widest transition-colors hover:text-white"
                    onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = 'var(--color-primario)'; e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = ''; e.currentTarget.style.borderColor = ''; }}
                >
                    <Edit3 className="w-4 h-4 shrink-0" />
                    Editar
                </button>
                {mostrarArchivar && (
                    <button
                        type="button"
                        onClick={() => onArchivar(usuario)}
                        className="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl theme-element border theme-border theme-text-muted text-[10px] font-black uppercase tracking-widest transition-colors hover:bg-amber-500 hover:text-white hover:border-transparent"
                    >
                        <Archive className="w-4 h-4 shrink-0" />
                        Archivar
                    </button>
                )}
            </div>
        </article>
    );
}

function FilaUsuarioDesktop({ usuario, onEditar, onArchivar, puedeArchivar, usuarioActualId, esCabezaEquipo = false }) {
    const mostrarArchivar = puedeArchivar
        && usuario.id !== usuarioActualId
        && !usuarioEsSuperAdmin(usuario);
    return (
        <tr className="border-b theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
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
                    <button
                        type="button"
                        onClick={() => onEditar(usuario)}
                        className="p-2.5 rounded-xl theme-element border theme-border theme-text-main hover:text-white transition-colors"
                        onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = 'var(--color-primario)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = ''; }}
                        aria-label="Editar usuario"
                    >
                        <Edit3 className="w-4 h-4" />
                    </button>
                    {mostrarArchivar && (
                        <button
                            type="button"
                            onClick={() => onArchivar(usuario)}
                            className="p-2.5 rounded-xl theme-element border theme-border theme-text-muted hover:bg-amber-500 hover:text-white hover:border-transparent transition-colors"
                            aria-label="Archivar usuario"
                        >
                            <Archive className="w-4 h-4" />
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

/** Resuelve área principal para el formulario (datos legacy en prod sin area_id). */
function resolverAreaPrincipalFormulario(areas = [], areaId = null) {
    const ids = (areas || [])
        .map((id) => Number(id))
        .filter((id) => !Number.isNaN(id));
    if (ids.length === 0) return '';

    const parsed = areaId !== '' && areaId != null ? Number(areaId) : null;
    if (parsed != null && ids.includes(parsed)) return String(parsed);
    if (ids.length >= 1) return String(ids[0]);

    return '';
}

function paramsListadoUsuarios({ busqueda, gerenteId, page }) {
    const params = { page: page ?? 1 };
    const termino = (busqueda || '').trim();
    if (termino) params.busqueda = termino;
    if (gerenteId) params.gerente_id = Number(gerenteId);
    return params;
}

export default function Usuarios({
    auth,
    usuarios = { data: [], current_page: 1, last_page: 1, per_page: 12, total: 0, from: 0, to: 0 },
    filtros = { busqueda: '', gerente_id: null },
    departamentos = [],
    posiblesGerentes = [],
    roles = [],
    rolesConfig = [],
    todosLosPermisos = [],
    catalogoPermisos = [],
    sexos = [],
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
    const debounceRef = useRef(null);

    const puedeArchivar = esSuperAdmin || (auth?.user?.permissions || []).includes('usuarios.archivar');

    const archivarUsuario = (usuario) => {
        const motivo = window.prompt(
            `Archivar a ${nombreCompleto(usuario)}.\n\nIngresa el motivo del archivado (obligatorio):`
        );
        if (motivo === null) return;
        if (motivo.trim().length < 3) {
            alert('El motivo debe tener al menos 3 caracteres.');
            return;
        }
        if (!window.confirm(`¿Confirmas archivar la cuenta de ${nombreCompleto(usuario)}? Se liberarán correo y teléfono para reasignación.`)) {
            return;
        }
        router.delete(route('admin.usuarios.archivar', usuario.id), {
            data: { motivo: motivo.trim() },
            preserveScroll: true,
        });
    };

    const [busqueda, setBusqueda] = useState(busquedaInicial);
    const [gerenteId, setGerenteId] = useState(gerenteInicial);
    const [showModal, setShowModal] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState(null);
    const [plantillaSeleccionada, setPlantillaSeleccionada] = useState('');
    const [plantillaPorPermiso, setPlantillaPorPermiso] = useState({});
    const [procedenciaActual, setProcedenciaActual] = useState({});

    // --- FORMULARIO MATRICIAL ---
    const { data, setData, processing, reset, errors, setError, clearErrors } = useForm({
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        username: '',
        email: '',
        password: '',
        telefono: '',
        fecha_nacimiento: '',
        catalogo_sexo_id: '',
        departamentos: [],
        areas: [],
        area_id: '',
        gerentes: [],
        roles_asignados: [],
        permisos_individuales: [],
        plantilla_origen: '',
        plantilla_por_permiso: {},
    });
    
    // --- ANIMACIONES ---
    useEffect(() => {
        animate('.fade-in-user', {
            opacity: [0, 1],
            translateY: [10, 0],
            delay: (el, i) => i * 50,
            easing: 'easeOutExpo',
            duration: 600
        });
    }, [lista]);

    useEffect(() => {
        setBusqueda(busquedaInicial);
    }, [busquedaInicial]);

    useEffect(() => {
        setGerenteId(gerenteInicial);
    }, [gerenteInicial]);

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
                url.searchParams.set('page', String(params.page ?? 1));
                window.history.replaceState({}, '', url.pathname + url.search);
            }
        } catch (error) {
            if (abortRef.current !== controller) return;
            if (axios.isCancel(error) || error.code === 'ERR_CANCELED') return;

            console.error('Error al cargar usuarios:', error);
        } finally {
            if (abortRef.current === controller) {
                setBuscando(false);
            }
        }
    }, [inertiaVersion]);

    const aplicarFiltros = useCallback((overrides = {}) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            const nextBusqueda = overrides.busqueda !== undefined ? overrides.busqueda : busqueda;
            const nextGerente = overrides.gerenteId !== undefined ? overrides.gerenteId : gerenteId;
            recargarUsuarios(
                paramsListadoUsuarios({
                    busqueda: nextBusqueda,
                    gerenteId: nextGerente,
                    page: 1,
                }),
                { actualizarUrl: true },
            );
        }, overrides.inmediato ? 0 : 350);
    }, [busqueda, gerenteId, recargarUsuarios]);

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > (usuariosState.last_page || 1)) return;
        recargarUsuarios(
            paramsListadoUsuarios({ busqueda, gerenteId, page: pagina }),
            { actualizarUrl: true },
        ).then(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    };

    const rolesJerarquia = (roles || []).filter(rol => rol?.name && !rol.name.includes('Grupo:'));
    const rolesGrupos = (roles || []).filter(rol => rol?.name?.includes('Grupo:'));

    // --- MANEJADORES DE EVENTOS ---
    const abrirModal = (usuario = null) => {
        setUsuarioEditando(usuario);
        if (usuario) {
            const rolesJerarquicos = (usuario.roles || [])
                .map(r => r.name)
                .filter(n => !n.includes('Grupo:'));

            const procMap = {};
            (usuario.permisos_procedencia || []).forEach((p) => {
                if (p.permiso) procMap[p.permiso] = p;
            });

            const plantillaMap = {};
            Object.entries(procMap).forEach(([permiso, meta]) => {
                if (meta.plantilla_origen) plantillaMap[permiso] = meta.plantilla_origen;
            });

            setPlantillaSeleccionada('');
            setPlantillaPorPermiso(plantillaMap);
            setProcedenciaActual(procMap);

            setData({
                name: (usuario.name || '').trim(),
                apellido_paterno: (usuario.apellido_paterno || '').trim(),
                apellido_materno: (usuario.apellido_materno || '').trim(),
                username: (usuario.username || '').trim(),
                email: (usuario.email || '').trim(),
                password: '',
                telefono: (usuario.telefono || '').trim(),
                fecha_nacimiento: usuario.fecha_nacimiento || '',
                catalogo_sexo_id: usuario.catalogo_sexo_id || '',
                departamentos: usuario.departamentos ? usuario.departamentos.map(d => d.id) : [],
                areas: usuario.areas ? usuario.areas.map(a => a.id) : [],
                area_id: resolverAreaPrincipalFormulario(
                    usuario.areas ? usuario.areas.map(a => a.id) : [],
                    usuario.area_id,
                ),
                gerentes: usuario.gerentes ? usuario.gerentes.map(g => g.id) : [],
                roles_asignados: rolesJerarquicos,
                permisos_individuales: usuario.permissions ? usuario.permissions.map(p => p.name) : [],
                plantilla_origen: '',
                plantilla_por_permiso: plantillaMap,
            });
        } else {
            setPlantillaSeleccionada('');
            setPlantillaPorPermiso({});
            setProcedenciaActual({});
            reset();
        }
        setShowModal(true);
    };

    const cerrarModal = () => {
        setShowModal(false);
        setTimeout(() => {
            reset();
            setUsuarioEditando(null);
        }, 300);
    };

    const toggleSelection = (campo, idOrName) => {
        const actuales = data[campo] || [];
        const nuevos = actuales.includes(idOrName)
            ? actuales.filter(item => item !== idOrName)
            : [...actuales, idOrName];
        setData(campo, nuevos);

        if (campo === 'areas') {
            setData('area_id', resolverAreaPrincipalFormulario(nuevos, data.area_id));
        }
    };

    const areasSeleccionadas = (departamentos || [])
        .flatMap((depto) => depto.areas || [])
        .filter((area) => (data.areas || []).includes(area.id));

    const aplicarPlantilla = (nombreGrupo) => {
        if (plantillaSeleccionada === nombreGrupo) {
            const permisosAnteriores = permisosDePlantilla([nombreGrupo], roles);
            setPermisosIndividualesViaForm((prev) =>
                prev.filter((p) => {
                    if (!permisosAnteriores.includes(p)) return true;
                    return plantillaPorPermiso[p] !== nombreGrupo;
                })
            );
            setPlantillaPorPermiso((prev) => {
                const next = { ...prev };
                Object.entries(next).forEach(([permiso, plantilla]) => {
                    if (plantilla === nombreGrupo) delete next[permiso];
                });
                return next;
            });
            setPlantillaSeleccionada('');
            setData('plantilla_origen', '');
            return;
        }

        if (plantillaSeleccionada) {
            const permisosAnteriores = permisosDePlantilla([plantillaSeleccionada], roles);
            setPermisosIndividualesViaForm((prev) =>
                prev.filter((p) => {
                    if (!permisosAnteriores.includes(p)) return true;
                    return plantillaPorPermiso[p] !== plantillaSeleccionada;
                })
            );
            setPlantillaPorPermiso((prev) => {
                const next = { ...prev };
                Object.entries(next).forEach(([permiso, plantilla]) => {
                    if (plantilla === plantillaSeleccionada) delete next[permiso];
                });
                return next;
            });
        }

        const nuevosPermisos = permisosDePlantilla([nombreGrupo], roles);
        setPlantillaSeleccionada(nombreGrupo);
        setData('plantilla_origen', nombreGrupo);
        setPermisosIndividualesViaForm((prev) => [...new Set([...prev, ...nuevosPermisos])]);
        setPlantillaPorPermiso((prev) => {
            const next = { ...prev };
            nuevosPermisos.forEach((p) => { next[p] = nombreGrupo; });
            setData('plantilla_por_permiso', next);
            return next;
        });
    };

    const setPermisosIndividualesViaForm = (updater) => {
        const actuales = data.permisos_individuales || [];
        const nuevos = typeof updater === 'function' ? updater(actuales) : updater;
        setPlantillaPorPermiso((prevPlantilla) => {
            const nextPlantilla = { ...prevPlantilla };
            Object.keys(nextPlantilla).forEach((k) => {
                if (!nuevos.includes(k)) delete nextPlantilla[k];
            });
            setData('plantilla_por_permiso', nextPlantilla);
            return nextPlantilla;
        });
        setData('permisos_individuales', nuevos);
    };

    const permisosFormData = {
        ...data,
        plantillas_activas: plantillaSeleccionada ? [plantillaSeleccionada] : [],
        plantilla_por_permiso: plantillaPorPermiso,
    };

    const handleSubmit = (e) => {
        e?.preventDefault?.();
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
        const areaPrincipalId = resolverAreaPrincipalFormulario(data.areas, data.area_id);
        if ((data.areas || []).length > 1 && !areaPrincipalId) {
            setError('area_id', 'Selecciona el área principal cuando el colaborador tiene varias áreas asignadas.');
            return;
        }

        const payload = {
            ...data,
            area_id: areaPrincipalId || null,
            permisos_individuales: permisosLimpios,
            plantilla_origen: plantillaSeleccionada || null,
            plantilla_por_permiso: plantillaPorPermiso,
        };

        setGuardando(true);
        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                cerrarModal();
                recargarUsuarios(
                    paramsListadoUsuarios({
                        busqueda,
                        gerenteId,
                        page: usuariosState.current_page || 1,
                    }),
                    { actualizarUrl: true },
                );
            },
            onFinish: () => setGuardando(false),
        };

        if (usuarioEditando) {
            router.put(route('admin.usuarios.update', usuarioEditando.id), payload, opciones);
        } else {
            router.post(route('admin.usuarios.store'), payload, opciones);
        }
    };

    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6');
    const cardListado = geliaCardClass('overflow-hidden');
    const procesandoForm = processing || guardando;
    const gerenteFiltroId = gerenteId ? Number(gerenteId) : null;

    return (
        <AppLayout auth={auth}>
            <Head title="Directorio de Usuarios" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
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
                            Gestión de personal, identidad y permisos operativos
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
                                onClick={() => abrirModal()}
                                className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact shrink-0`}
                            >
                                <UserPlus className="w-4 h-4" /> Nuevo ingreso
                            </button>
                        </div>
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

                <div className={`lg:hidden space-y-3 transition-opacity duration-200 ${buscando ? 'opacity-50 pointer-events-none' : 'opacity-100'}`}>
                    {lista.length === 0 ? (
                        <div className={`${geliaCardClass()} text-center py-14 px-6 border-dashed`}>
                            <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                            <h3 className="text-base font-black italic uppercase theme-text-main">Sin resultados</h3>
                            <p className="text-[10px] font-bold theme-text-muted mt-2 uppercase tracking-widest">
                                Ajusta la búsqueda o registra un nuevo colaborador
                            </p>
                        </div>
                    ) : (
                        lista.map((usuario) => (
                            <TarjetaUsuarioMobile
                                key={usuario.id}
                                usuario={usuario}
                                onEditar={abrirModal}
                                onArchivar={archivarUsuario}
                                puedeArchivar={puedeArchivar}
                                usuarioActualId={auth?.user?.id}
                                esCabezaEquipo={gerenteFiltroId != null && Number(usuario.id) === gerenteFiltroId}
                            />
                        ))
                    )}
                    {lista.length > 0 && (
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
                                {lista.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-16 text-center">
                                            <Users className="w-10 h-10 theme-text-muted mx-auto mb-3 opacity-50" />
                                            <p className="font-black italic uppercase theme-text-main text-sm">Sin resultados</p>
                                            <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase tracking-widest">
                                                Ajusta la búsqueda o crea un nuevo ingreso
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    lista.map((usuario) => (
                                        <FilaUsuarioDesktop
                                            key={usuario.id}
                                            usuario={usuario}
                                            onEditar={abrirModal}
                                            onArchivar={archivarUsuario}
                                            puedeArchivar={puedeArchivar}
                                            usuarioActualId={auth?.user?.id}
                                            esCabezaEquipo={gerenteFiltroId != null && Number(usuario.id) === gerenteFiltroId}
                                        />
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    {lista.length > 0 && (
                        <GeliaPaginacion paginator={usuariosState} onIrAPagina={irAPagina} embedded />
                    )}
                </div>

                {showModal && createPortal(
                    <div
                        className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`}
                        onClick={cerrarModal}
                        data-gelia-unsaved-form="1"
                    >
                        <div
                            className={`${THEME_MODAL_SHELL} max-w-4xl modal-pop text-left`}
                            onClick={(e) => e.stopPropagation()}
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
                                departamentos={departamentos}
                                posiblesGerentes={posiblesGerentes}
                                rolesJerarquia={rolesJerarquia}
                                rolesGrupos={rolesGrupos}
                                plantillaSeleccionada={plantillaSeleccionada}
                                aplicarPlantilla={aplicarPlantilla}
                                permisosFormData={permisosFormData}
                                setPermisosIndividualesViaForm={setPermisosIndividualesViaForm}
                                roles={roles}
                                todosLosPermisos={todosLosPermisos}
                                catalogoPermisos={catalogoPermisos}
                                permisosUsuario={permisosUsuario}
                                esSuperAdmin={esSuperAdmin}
                                usuarioActualId={auth?.user?.id}
                                procedenciaActual={procedenciaActual}
                                setPlantillaPorPermiso={setPlantillaPorPermiso}
                                sexos={sexos}
                                toggleSelection={toggleSelection}
                                areasSeleccionadas={areasSeleccionadas}
                                resolverAreaPrincipalFormulario={resolverAreaPrincipalFormulario}
                            />
                        </div>
                    </div>,
                    document.body
                )}
            </div>
        </AppLayout>
    );
}