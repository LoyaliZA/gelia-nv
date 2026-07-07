import React, { useState, useMemo, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Link as LinkIcon,
    Copy,
    CheckCircle,
    Sparkles,
    ShieldCheck,
    Clock,
    ChevronDown,
    Briefcase,
    Layers,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import PermisosAtomicos from './Partials/PermisosAtomicos';
import {
    permisosDePlantilla,
} from '../../utils/permisos';

export default function Enlaces({
    auth,
    roles = [],
    todosLosPermisos = [],
    permisosUsuario = [],
    esSuperAdmin = false,
}) {
    const rolesJerarquia = useMemo(
        () => (roles || []).filter((r) => r?.name && !r.name.includes('Grupo:')),
        [roles]
    );

    const rolesGrupos = useMemo(
        () => (roles || []).filter((r) => r?.name?.includes('Grupo:')),
        [roles]
    );

    const [rolSeleccionado, setRolSeleccionado] = useState('');
    const [grupoSeleccionado, setGrupoSeleccionado] = useState('');
    const [permisosIndividuales, setPermisosIndividuales] = useState([]);
    const [plantillaPorPermiso, setPlantillaPorPermiso] = useState({});
    const [enlaceGenerado, setEnlaceGenerado] = useState('');
    const [copiado, setCopiado] = useState(false);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        if (!rolSeleccionado && rolesJerarquia[0]?.name) {
            setRolSeleccionado(rolesJerarquia[0].name);
        }
    }, [rolesJerarquia, rolSeleccionado]);

    const rolesAsignados = useMemo(
        () => [rolSeleccionado].filter(Boolean),
        [rolSeleccionado]
    );

    const plantillasActivas = useMemo(
        () => [grupoSeleccionado].filter(Boolean),
        [grupoSeleccionado]
    );

    const permisosData = useMemo(
        () => ({
            roles_asignados: rolesAsignados,
            permisos_individuales: permisosIndividuales,
            plantillas_activas: plantillasActivas,
            plantilla_por_permiso: plantillaPorPermiso,
        }),
        [rolesAsignados, permisosIndividuales, plantillasActivas, plantillaPorPermiso]
    );

    const setPermisosData = (campo, valor) => {
        if (campo === 'permisos_individuales') {
            setPermisosIndividuales(valor);
            setPlantillaPorPermiso((prev) => {
                const next = { ...prev };
                Object.keys(next).forEach((k) => {
                    if (!valor.includes(k)) delete next[k];
                });
                return next;
            });
        }
    };

    const handleGrupoChange = (nuevoGrupo) => {
        const grupoAnterior = grupoSeleccionado;
        setGrupoSeleccionado(nuevoGrupo);

        if (grupoAnterior) {
            const permisosAnteriores = permisosDePlantilla([grupoAnterior], roles);
            setPermisosIndividuales((prev) =>
                prev.filter((p) => {
                    if (!permisosAnteriores.includes(p)) return true;
                    return plantillaPorPermiso[p] !== grupoAnterior;
                })
            );
            setPlantillaPorPermiso((prev) => {
                const next = { ...prev };
                Object.entries(next).forEach(([permiso, plantilla]) => {
                    if (plantilla === grupoAnterior) delete next[permiso];
                });
                return next;
            });
        }

        if (nuevoGrupo) {
            const nuevosPermisos = permisosDePlantilla([nuevoGrupo], roles);
            setPermisosIndividuales((prev) => [...new Set([...prev, ...nuevosPermisos])]);
            setPlantillaPorPermiso((prev) => {
                const next = { ...prev };
                nuevosPermisos.forEach((p) => {
                    next[p] = nuevoGrupo;
                });
                return next;
            });
        }
    };

    const generarEnlace = async () => {
        if (!rolSeleccionado) {
            alert('Selecciona un rol jerárquico antes de generar el enlace.');
            return;
        }

        setCargando(true);
        setEnlaceGenerado('');
        setCopiado(false);

        try {
            const payload = {
                role_name: rolSeleccionado,
                permisos_asignados: permisosIndividuales,
            };

            if (grupoSeleccionado) {
                payload.grupo_name = grupoSeleccionado;
            }

            const response = await axios.post(route('admin.enlaces.generar'), payload);
            setEnlaceGenerado(response.data.enlace);
        } catch (error) {
            console.error('Error generando el enlace:', error);
            const msg = error.response?.data?.message || 'Hubo un error al generar el enlace.';
            alert(msg);
        } finally {
            setCargando(false);
        }
    };

    const copiarAlPortapapeles = async () => {
        if (!enlaceGenerado) return;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(enlaceGenerado);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = enlaceGenerado;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
            }
            setCopiado(true);
            setTimeout(() => setCopiado(false), 3000);
        } catch (error) {
            alert('Tu navegador bloqueó el copiado automático.');
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Generación de Accesos | GELIANV" />

            <div className="max-w-7xl mx-auto p-4 md:p-8 space-y-8">
                <div className="animate-page-reveal theme-surface border theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[3rem] shadow-sm">
                    <h1 className="text-3xl md:text-4xl font-black theme-text-main flex items-center gap-3 italic uppercase tracking-tighter m-0">
                        <ShieldCheck className="w-8 h-8 md:w-10 md:h-10" style={{ color: 'var(--color-primario)' }} />
                        GENERACIÓN DE <span style={{ color: 'var(--color-primario)' }}>ACCESOS</span>
                    </h1>
                    <p className="theme-text-muted text-[10px] font-bold uppercase tracking-widest mt-2 opacity-80 max-w-2xl">
                        Define rol de identidad, aplica plantilla de permisos y delega accesos explícitos al nuevo colaborador.
                    </p>
                    {!esSuperAdmin && (
                        <p className="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 mt-3 bg-amber-500/10 border border-amber-500/20 px-3 py-2 rounded-lg w-fit">
                            Solo puedes asignar permisos que tú posees
                        </p>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div className="animate-page-reveal lg:col-span-2 theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 shadow-sm relative overflow-hidden">
                        <div className="relative z-10 space-y-8">
                            <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-2 border-b theme-border pb-4">
                                <LinkIcon className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                Configuración de Enlace
                            </h2>

                            <div className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">
                                            Rol Jerárquico (identidad)_
                                        </label>
                                        <div className="relative mt-2">
                                            <Briefcase className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                            <select
                                                value={rolSeleccionado}
                                                onChange={(e) => setRolSeleccionado(e.target.value)}
                                                className="w-full pl-12 pr-12 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none cursor-pointer"
                                            >
                                                {rolesJerarquia.length === 0 ? (
                                                    <option value="">Sin roles disponibles</option>
                                                ) : (
                                                    rolesJerarquia.map((rol) => (
                                                        <option key={rol.id} value={rol.name}>{rol.name}</option>
                                                    ))
                                                )}
                                            </select>
                                            <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">
                                            Plantilla de Grupo (opcional)_
                                        </label>
                                        <div className="relative mt-2">
                                            <Layers className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                            <select
                                                value={grupoSeleccionado}
                                                onChange={(e) => handleGrupoChange(e.target.value)}
                                                className="w-full pl-12 pr-12 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none cursor-pointer"
                                            >
                                                <option value="">Sin plantilla</option>
                                                {rolesGrupos.map((grupo) => (
                                                    <option key={grupo.id} value={grupo.name}>{grupo.name}</option>
                                                ))}
                                            </select>
                                            <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                                        </div>
                                    </div>
                                </div>

                                <PermisosAtomicos
                                    data={permisosData}
                                    setData={setPermisosData}
                                    roles={roles}
                                    todosLosPermisos={todosLosPermisos}
                                    permisosUsuario={permisosUsuario}
                                    esSuperAdmin={esSuperAdmin}
                                    onPlantillaPorPermisoChange={setPlantillaPorPermiso}
                                    plantillaActiva={grupoSeleccionado}
                                />

                                <button
                                    onClick={generarEnlace}
                                    disabled={cargando || !rolSeleccionado}
                                    className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] transition-transform shadow-xl hover:scale-105 disabled:opacity-50 outline-none flex items-center justify-center gap-3"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    {cargando ? <Clock className="w-4 h-4 animate-spin" /> : <Sparkles className="w-4 h-4" />}
                                    {cargando ? 'Cifrando Token...' : 'Generar Enlace Seguro'}
                                </button>
                            </div>

                            {enlaceGenerado && (
                                <div className="mt-8 p-6 theme-element border-2 border-dashed theme-border rounded-[2rem] space-y-4">
                                    <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest italic" style={{ color: 'var(--color-primario)' }}>
                                        <Key className="w-3 h-3" /> Token Generado_
                                    </label>
                                    <div className="flex flex-col sm:flex-row items-center gap-3">
                                        <input type="text" readOnly value={enlaceGenerado} className="w-full sm:flex-1 p-4 theme-element border theme-border rounded-xl theme-text-main font-bold text-xs truncate" onClick={(e) => e.target.select()} />
                                        <button onClick={copiarAlPortapapeles} className="px-6 py-4 theme-surface border theme-border rounded-xl">
                                            {copiado ? <CheckCircle className="w-5 h-5 text-emerald-500" /> : <Copy className="w-5 h-5 theme-text-muted" />}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="animate-page-reveal lg:col-span-1 space-y-4">
                        <div className="p-6 theme-surface border theme-border rounded-[2rem] shadow-sm">
                            <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-main mb-2">Composición del enlace</h4>
                            <p className="text-[11px] theme-text-muted font-bold leading-relaxed">
                                <span className="text-blue-500 font-black">Rol</span> define identidad jerárquica.
                                <span className="text-purple-500 font-black"> Plantilla</span> sugiere permisos pre-rellenados.
                                <span className="text-orange-500 font-black"> Permisos</span> son la delegación explícita del superior.
                            </p>
                        </div>
                        <div className="p-6 theme-surface border border-amber-500/40 rounded-[2rem] shadow-sm">
                            <Clock className="w-5 h-5 text-amber-500 mb-2" />
                            <p className="text-[11px] theme-text-main font-bold italic">Válido 48 horas. Uso único por registro.</p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
