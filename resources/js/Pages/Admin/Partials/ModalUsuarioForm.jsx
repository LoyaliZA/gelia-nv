import React, { useMemo, useState } from 'react';
import {
    ShieldCheck, X, Briefcase, Check, Network, User, Lock, Smartphone,
    Save, Layers, Mail, AtSign, MapPin,
} from 'lucide-react';
import PermisosAtomicos from './PermisosAtomicos';
import { areasDelCatalogo, compararFormularioUsuario, principalFueraDeCatalogo, resumenErrores, textoError } from './formularioUsuario';
import { seccionDeModulo } from '../../../utils/permisos';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';
import ResumenCambiosUsuario from './ResumenCambiosUsuario';

function TextoError({ mensaje }) {
    const texto = textoError(mensaje);
    if (!texto) return null;
    return <p className="text-sm font-medium mt-1 theme-text-peligro">{texto}</p>;
}

const TABS = [
    { id: 'datos', label: 'Datos Generales', icon: User },
    { id: 'rol', label: 'Rol y Permisos', icon: ShieldCheck },
    { id: 'organizacion', label: 'Organización', icon: Network },
];

export default function ModalUsuarioForm({
    usuarioEditando,
    onCerrar,
    onSubmit,
    processing,
    data,
    setData,
    errors = {},
    incidencia = null,
    departamentos = [],
    sucursales = [],
    posiblesGerentes = [],
    rolesJerarquia = [],
    rolesGrupos = [],
    plantillaSeleccionada,
    aplicarPlantilla,
    permisosFormData,
    setPermisosIndividualesViaForm,
    roles,
    todosLosPermisos,
    catalogoPermisos,
    permisosUsuario,
    esSuperAdmin,
    usuarioActualId,
    procedenciaActual,
    setPlantillaPorPermiso,
    sexos,
    toggleSelection,
    areasSeleccionadas,
    departamentosSeleccionados,
    sucursalesSeleccionadas = [],
    resolverAreaPrincipalFormulario,
    resolverPrincipalFormulario,
    lineaBase = null,
    companeros = [],
    onCambiarColaborador,
    modoDrawer = 'lateral',
}) {
    const [activeTab, setActiveTab] = useState('datos');
    const [expansionPermisos, setExpansionPermisos] = useState(() => ({
        busqueda: '',
        modulos: { control_pedidos: true },
        secciones: { [seccionDeModulo('control_pedidos')]: true },
        submodulos: {},
    }));
    const resumen = resumenErrores(errors);
    const avisoIncidencia = incidencia?.mensaje && !resumen.includes(incidencia.mensaje) ? incidencia.mensaje : '';
    const hayAviso = resumen.length > 0 || Boolean(avisoIncidencia) || Boolean(incidencia?.status);
    const resumenCambios = useMemo(() => compararFormularioUsuario(lineaBase, {
        ...data,
        plantilla_origen: plantillaSeleccionada || '',
    }, {
        departamentos,
        sucursales,
        gerentes: posiblesGerentes,
        sexos,
        roles,
    }), [lineaBase, data, plantillaSeleccionada, departamentos, sucursales, posiblesGerentes, sexos, roles]);

    const toggleTodasAreasDepartamento = (depto) => {
        const idsDelDepartamento = (depto.areas || []).map((area) => area.id);
        if (idsDelDepartamento.length === 0) return;

        const areasActuales = data.areas || [];
        const todosSeleccionados = idsDelDepartamento.every((id) => areasActuales.includes(id));
        let nuevasAreas;

        if (todosSeleccionados) {
            nuevasAreas = areasActuales.filter((id) => !idsDelDepartamento.includes(id));
        } else {
            const nuevosIds = idsDelDepartamento.filter((id) => !areasActuales.includes(id));
            nuevasAreas = [...areasActuales, ...nuevosIds];
        }

        setData('areas', nuevasAreas);
        setData('area_id', resolverAreaPrincipalFormulario(nuevasAreas, data.area_id));
    };

    const nombreColaborador = (persona) => [persona?.name, persona?.apellido_paterno, persona?.apellido_materno]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="usuarios-drawer-form">
            <div className="usuarios-drawer-cabecera">
                <div className="min-w-0">
                    <h2 id="drawer-colaborador-titulo" className="theme-text-main">
                        {usuarioEditando ? nombreColaborador(usuarioEditando) || 'Colaborador' : 'Alta de nuevo colaborador'}
                    </h2>
                    <p className="text-sm theme-text-muted m-0 mt-1">
                        {usuarioEditando ? 'Editar colaborador' : 'Datos, acceso y organización'}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onCerrar}
                    className="usuarios-drawer-cerrar"
                    aria-label="Cerrar"
                >
                    <X className="w-5 h-5" />
                </button>
            </div>

            {modoDrawer === 'superpuesto' && companeros.length > 1 && (
                <div className="usuarios-drawer-companeros" role="navigation" aria-label="Cambiar de colaborador">
                    {companeros.map((persona) => (
                        <button
                            key={persona.id}
                            type="button"
                            aria-current={usuarioEditando?.id === persona.id ? 'true' : undefined}
                            onClick={(event) => onCambiarColaborador?.(persona, event)}
                        >
                            {nombreColaborador(persona) || persona.email}
                        </button>
                    ))}
                </div>
            )}

            <div className="usuarios-drawer-nav" role="tablist" aria-label="Secciones del colaborador">
                {TABS.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        role="tab"
                        aria-selected={activeTab === tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        className="usuarios-drawer-tab"
                    >
                        <tab.icon className="w-4 h-4 shrink-0" aria-hidden />
                        <span>{tab.label}</span>
                    </button>
                ))}
            </div>

            {hayAviso && (
                <div role="alert" className="px-6 md:px-8 py-3 border-b theme-border">
                    <p className="text-sm theme-text-peligro leading-relaxed break-words m-0">
                        {incidencia?.status ? `Error ${incidencia.status}. ` : ''}
                        {avisoIncidencia ? `${avisoIncidencia} ` : ''}
                        {resumen.join(' ')}
                    </p>
                </div>
            )}

            <div className="gelia-modal-body p-6 md:p-8 custom-scrollbar">
                <ResumenCambiosUsuario resumen={resumenCambios} />
                {activeTab === 'datos' && (
                    <div className="space-y-8">
                        <div>
                            <h3 className="text-base font-semibold theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                Identidad Personal
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Nombre(s) *</label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.name || ''} onChange={(e) => setData('name', e.target.value)} type="text" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none focus:ring-1 focus:ring-transparent transition-all" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                        <TextoError mensaje={errors.name} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Ap. Paterno *</label>
                                    <input value={data.apellido_paterno || ''} onChange={(e) => setData('apellido_paterno', e.target.value)} type="text" required className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                    <TextoError mensaje={errors.apellido_paterno} />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Ap. Materno</label>
                                    <input value={data.apellido_materno || ''} onChange={(e) => setData('apellido_materno', e.target.value)} type="text" className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                    <TextoError mensaje={errors.apellido_materno} />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Teléfono / WhatsApp</label>
                                    <div className="relative">
                                        <Smartphone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} type="text" className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Sexo Biológico</label>
                                    <select value={data.catalogo_sexo_id || ''} onChange={(e) => setData('catalogo_sexo_id', e.target.value)} className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none appearance-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }}>
                                        <option value="">Selecciona...</option>
                                        {(sexos || []).map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Fecha de Nacimiento</label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.fecha_nacimiento || ''} onChange={(e) => setData('fecha_nacimiento', e.target.value)} type="date" className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-base font-semibold theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <Lock className="w-4 h-4 theme-text-primario" />
                                Credenciales de Acceso
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Username *</label>
                                    <div className="relative">
                                        <AtSign className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.username || ''} onChange={(e) => setData('username', e.target.value)} type="text" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                        <TextoError mensaje={errors.username} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Correo Electrónico *</label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.email || ''} onChange={(e) => setData('email', e.target.value)} type="email" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                        <TextoError mensaje={errors.email} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="usuarios-campo-label">Contraseña</label>
                                    <div className="relative">
                                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.password || ''} onChange={(e) => setData('password', e.target.value)} type="password" required={!usuarioEditando} placeholder={usuarioEditando ? 'Dejar en blanco para conservar' : 'Asignar contraseña'} className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                        <TextoError mensaje={errors.password} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'rol' && (
                    <div className="space-y-8">
                        <div>
                            <h3 className="text-base font-semibold theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <Briefcase className="w-4 h-4 theme-text-primario" />
                                Roles y Jerarquías
                            </h3>
                            <div className="space-y-4">
                                <p className="text-xs font-black uppercase tracking-widest theme-text-muted ml-2">Asignación Principal</p>
                                <div className="flex flex-wrap gap-3">
                                    {(rolesJerarquia || []).map((rol) => (
                                        <button
                                            key={rol.id}
                                            type="button"
                                            onClick={() => toggleSelection('roles_asignados', rol.name)}
                                            className={`px-5 py-3.5 rounded-2xl text-xs font-black uppercase tracking-widest border-2 transition-all flex items-center gap-2 min-h-[44px] ${data.roles_asignados?.includes(rol.name) ? 'shadow-md text-white' : 'theme-element theme-border theme-text-muted'}`}
                                            style={data.roles_asignados?.includes(rol.name) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                        >
                                            {data.roles_asignados?.includes(rol.name) && <Check className="w-4 h-4 shrink-0" />}
                                            {rol.name}
                                        </button>
                                    ))}
                                </div>
                                <TextoError mensaje={errors.roles_asignados} />
                            </div>
                        </div>

                        {(rolesGrupos || []).length > 0 && (
                            <div>
                                <p className="text-xs font-black uppercase tracking-widest theme-text-muted ml-2 mb-3 flex items-center gap-2">
                                    <Layers className="w-4 h-4 shrink-0" />
                                    Plantillas de Grupo (pre-relleno)
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    {(rolesGrupos || []).map((rol) => (
                                        <button
                                            key={rol.id}
                                            type="button"
                                            onClick={() => aplicarPlantilla(rol.name)}
                                            className={`px-5 py-3.5 rounded-2xl text-xs font-black uppercase tracking-widest border-2 transition-all flex items-center gap-2 min-h-[44px] ${plantillaSeleccionada === rol.name ? 'shadow-md text-white' : 'theme-element theme-border theme-text-muted'}`}
                                            style={plantillaSeleccionada === rol.name ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                        >
                                            {plantillaSeleccionada === rol.name && <Check className="w-4 h-4 shrink-0" />}
                                            {rol.name}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <p className="text-[11px] theme-text-muted leading-relaxed px-0.5">
                            Los permisos de <span className="font-bold theme-text-main">Gestión de pedidos</span> se agrupan por panel del módulo (ventas, auditoría, CEDIS, tienda, pagos, etc.). Cada casilla incluye una descripción de lo que habilita.
                        </p>

                        <PermisosAtomicos
                            colapsarSecciones
                            expansion={expansionPermisos}
                            onExpansion={setExpansionPermisos}
                            modulosInicialesExpandidos={['control_pedidos']}
                            data={permisosFormData}
                            setData={(campo, valor) => {
                                if (campo === 'permisos_individuales') {
                                    setPermisosIndividualesViaForm(valor);
                                } else {
                                    setData(campo, valor);
                                }
                            }}
                            roles={roles}
                            todosLosPermisos={todosLosPermisos}
                            catalogoPermisos={catalogoPermisos}
                            permisosUsuario={permisosUsuario}
                            esSuperAdmin={esSuperAdmin}
                            usuarioActualId={usuarioActualId}
                            procedencia={procedenciaActual}
                            onPlantillaPorPermisoChange={setPlantillaPorPermiso}
                            plantillaActiva={plantillaSeleccionada}
                            sucursalesCatalogo={sucursales}
                            sucursalIds={data.sucursales}
                            sucursalPrincipalId={data.sucursal_principal_id}
                        />
                    </div>
                )}

                {activeTab === 'organizacion' && (
                    <div>
                        <h3 className="text-base font-semibold theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <Network className="w-4 h-4 theme-text-muted" />
                            Organización y Liderazgo
                        </h3>

                        <div className="grid grid-cols-1 gap-6">
                            <div className="space-y-2">
                                <label className="usuarios-campo-label">1. Departamentos Asignados (Múltiple)</label>
                                <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent">
                                    {(departamentos || []).map((depto) => (
                                        <button
                                            key={`depto-${depto.id}`}
                                            type="button"
                                            onClick={() => toggleSelection('departamentos', depto.id)}
                                            className={`px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${(data.departamentos || []).includes(depto.id) ? 'shadow-sm text-white' : 'theme-element theme-border theme-text-muted'}`}
                                            style={(data.departamentos || []).includes(depto.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                        >
                                            {(data.departamentos || []).includes(depto.id) && <Check className="w-3 h-3" />}
                                            {depto.nombre}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="usuarios-campo-label">
                                    2. Departamento Principal (marca / tickets)
                                </label>
                                <select
                                    value={data.departamento_id || ''}
                                    onChange={(e) => setData('departamento_id', e.target.value)}
                                    disabled={departamentosSeleccionados.length === 0}
                                    required={departamentosSeleccionados.length > 1}
                                    className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none appearance-none transition-all focus:ring-1 focus:ring-transparent disabled:opacity-50"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                >
                                    <option value="">
                                        {departamentosSeleccionados.length === 0
                                            ? 'Selecciona al menos un departamento'
                                            : departamentosSeleccionados.length === 1
                                                ? 'Se asignará automáticamente'
                                                : 'Selecciona el departamento principal...'}
                                    </option>
                                    {departamentosSeleccionados.map((depto) => (
                                        <option key={`depto-principal-${depto.id}`} value={depto.id}>
                                            {depto.nombre}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[9px] theme-text-muted ml-2 leading-relaxed">
                                    Define la marca del ticket térmico y recibos. Debe ser uno de los departamentos asignados arriba.
                                </p>
                                {principalFueraDeCatalogo(data.departamento_id, departamentos) && (
                                    <span className="block mt-1 theme-text-aviso">
                                        El departamento principal está fuera de tu alcance y se conserva al guardar.
                                    </span>
                                )}
                                <TextoError mensaje={errors.departamento_id} />
                            </div>

                            <div className="space-y-2">
                                <label className="usuarios-campo-label">3. Áreas de Operación (Múltiple)</label>
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 p-4 border theme-border rounded-2xl theme-element bg-transparent max-h-80 overflow-y-auto custom-scrollbar">
                                    {(departamentos || []).map((depto) => {
                                        const areasDepto = depto.areas || [];
                                        const todosSeleccionados = areasDepto.length > 0
                                            && areasDepto.every((area) => (data.areas || []).includes(area.id));

                                        return (
                                            <section key={`grupo-${depto.id}`} className="space-y-2 min-w-0">
                                                <div className="flex items-center justify-between gap-2 border-b theme-border pb-2">
                                                    <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-main truncate">
                                                        {depto.nombre}
                                                    </h4>
                                                    {areasDepto.length > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => toggleTodasAreasDepartamento(depto)}
                                                            className="shrink-0 text-[9px] font-black uppercase tracking-widest px-2.5 py-1 rounded-lg border theme-border theme-text-muted hover:theme-text-main hover:border-[var(--color-primario)] transition-colors"
                                                        >
                                                            {todosSeleccionados ? 'Ninguno' : 'Todos'}
                                                        </button>
                                                    )}
                                                </div>
                                                {areasDepto.length === 0 ? (
                                                    <p className="text-[10px] theme-text-muted italic px-1">Sin áreas registradas</p>
                                                ) : (
                                                    <ul className="flex flex-col gap-1.5">
                                                        {areasDepto.map((area) => {
                                                            const seleccionada = (data.areas || []).includes(area.id);
                                                            return (
                                                                <li key={`area-${area.id}`}>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => toggleSelection('areas', area.id)}
                                                                        className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border transition-all text-left ${
                                                                            seleccionada
                                                                                ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/10'
                                                                                : 'theme-border theme-text-muted hover:bg-black/[0.03] dark:hover:bg-white/[0.03]'
                                                                        }`}
                                                                    >
                                                                        <span
                                                                            className={`w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-colors ${
                                                                                seleccionada
                                                                                    ? 'border-[var(--color-primario)] bg-[var(--color-primario)] text-white'
                                                                                    : 'theme-border bg-transparent'
                                                                            }`}
                                                                            aria-hidden
                                                                        >
                                                                            {seleccionada && <Check className="w-3 h-3" />}
                                                                        </span>
                                                                        <span className={`text-xs font-bold leading-snug break-words min-w-0 ${seleccionada ? 'theme-text-main' : ''}`}>
                                                                            {area.nombre}
                                                                        </span>
                                                                    </button>
                                                                </li>
                                                            );
                                                        })}
                                                    </ul>
                                                )}
                                            </section>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="usuarios-campo-label">
                                    4. Área Principal (Reportes y RH)
                                </label>
                                <select
                                    value={data.area_id || ''}
                                    onChange={(e) => setData('area_id', e.target.value)}
                                    disabled={areasSeleccionadas.length === 0}
                                    required={areasSeleccionadas.length > 1}
                                    className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none appearance-none transition-all focus:ring-1 focus:ring-transparent disabled:opacity-50"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                >
                                    <option value="">
                                        {areasSeleccionadas.length === 0
                                            ? 'Selecciona al menos un área operativa'
                                            : areasSeleccionadas.length === 1
                                                ? 'Se asignará automáticamente'
                                                : 'Selecciona el área principal...'}
                                    </option>
                                    {areasSeleccionadas.map((area) => (
                                        <option key={`area-principal-${area.id}`} value={area.id}>
                                            {area.nombre}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[9px] theme-text-muted ml-2 leading-relaxed">
                                    Define el área que aparecerá en responsivas y reportes. Debe ser una de las áreas operativas asignadas arriba.
                                    {areasSeleccionadas.length > 1 && !usuarioEditando?.area_id && (
                                        <span className="block mt-1 theme-text-aviso">
                                            Se preseleccionó la primera área asignada; confirma o cambia la principal antes de guardar.
                                        </span>
                                    )}
                                </p>
                                {principalFueraDeCatalogo(data.area_id, areasDelCatalogo(departamentos)) && (
                                    <span className="block mt-1 theme-text-aviso">
                                        El área principal está fuera de tu alcance y se conserva al guardar.
                                    </span>
                                )}
                                <TextoError mensaje={errors.area_id} />
                            </div>

                            <div className="space-y-2 border-t theme-border pt-6">
                                <label className="usuarios-campo-label flex items-center gap-1.5">
                                    <MapPin className="w-3.5 h-3.5" style={{ color: 'var(--color-primario)' }} />
                                    5. Sucursales PDV (Múltiple)
                                </label>
                                {(sucursales || []).length === 0 ? (
                                    <p className="text-[10px] theme-text-muted italic px-2">
                                        No hay sucursales activas en el catálogo.
                                    </p>
                                ) : (
                                    <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent">
                                        {(sucursales || []).map((sucursal) => (
                                            <button
                                                key={`sucursal-${sucursal.id}`}
                                                type="button"
                                                onClick={() => toggleSelection('sucursales', sucursal.id)}
                                                className={`px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${(data.sucursales || []).includes(sucursal.id) ? 'shadow-sm text-white' : 'theme-element theme-border theme-text-muted'}`}
                                                style={(data.sucursales || []).includes(sucursal.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                            >
                                                {(data.sucursales || []).includes(sucursal.id) && <Check className="w-3 h-3" />}
                                                {sucursal.nombre}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <TextoError mensaje={errors.sucursales} />
                            </div>

                            <div className="space-y-2">
                                <label className="usuarios-campo-label">
                                    6. Sucursal Principal PDV
                                </label>
                                <select
                                    value={data.sucursal_principal_id || ''}
                                    onChange={(e) => setData('sucursal_principal_id', e.target.value)}
                                    disabled={sucursalesSeleccionadas.length === 0}
                                    required={sucursalesSeleccionadas.length > 1}
                                    className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none appearance-none transition-all focus:ring-1 focus:ring-transparent disabled:opacity-50"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                >
                                    <option value="">
                                        {sucursalesSeleccionadas.length === 0
                                            ? 'Sin sucursales asignadas'
                                            : sucursalesSeleccionadas.length === 1
                                                ? 'Se asignará automáticamente'
                                                : 'Selecciona la sucursal principal...'}
                                    </option>
                                    {sucursalesSeleccionadas.map((sucursal) => (
                                        <option key={`sucursal-principal-${sucursal.id}`} value={sucursal.id}>
                                            {sucursal.nombre}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-[9px] theme-text-muted ml-2 leading-relaxed">
                                    Requerida para operar en Punto de Venta. Define la sucursal activa por defecto al ingresar al módulo.
                                </p>
                                {principalFueraDeCatalogo(data.sucursal_principal_id, sucursales) && (
                                    <span className="block mt-1 theme-text-aviso">
                                        La sucursal principal está fuera de tu alcance y se conserva al guardar.
                                    </span>
                                )}
                                <TextoError mensaje={errors.sucursal_principal_id} />
                            </div>

                            <div className="space-y-2">
                                <label className="usuarios-campo-label">7. Reporta a (Gerentes / Líderes)</label>
                                <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent max-h-32 overflow-y-auto custom-scrollbar">
                                    {(posiblesGerentes || []).length === 0 ? (
                                        <span className="text-xs theme-text-muted italic px-2">No hay gerentes disponibles.</span>
                                    ) : (
                                        (posiblesGerentes || []).map((gerente) => (
                                            <button
                                                key={`gerente-${gerente.id}`}
                                                type="button"
                                                onClick={() => toggleSelection('gerentes', gerente.id)}
                                                className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${(data.gerentes || []).includes(gerente.id) ? 'shadow-sm text-white' : 'bg-transparent theme-border theme-text-muted hover:bg-black/5 dark:hover:bg-white/5'}`}
                                                style={(data.gerentes || []).includes(gerente.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                            >
                                                {(data.gerentes || []).includes(gerente.id) && <Check className="w-3 h-3" />}
                                                {gerente.name} {gerente.apellido_paterno}
                                            </button>
                                        ))
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <div className="gelia-modal-footer usuarios-drawer-pie">
                <button type="button" onClick={onCerrar} disabled={processing} className={THEME_BTN_SECONDARY}>
                    Cancelar
                </button>
                <button type="button" onClick={onSubmit} disabled={processing} className={THEME_BTN_PRIMARY}>
                    <Save className="w-5 h-5 shrink-0" />
                    {processing
                        ? 'Guardando...'
                        : usuarioEditando
                            ? 'Guardar cambios'
                            : 'Registrar colaborador'}
                </button>
            </div>
        </div>
    );
}
