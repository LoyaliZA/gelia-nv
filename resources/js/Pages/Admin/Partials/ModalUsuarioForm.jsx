import React, { useState } from 'react';
import {
    ShieldCheck, X, Briefcase, Check, Network, User, Lock, Smartphone,
    Save, Layers, Mail, AtSign,
} from 'lucide-react';
import PermisosAtomicos from './PermisosAtomicos';
import { GELIA_SEGMENT_TABS_TRACK_COMPACT, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';

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
    errors,
    departamentos,
    posiblesGerentes,
    rolesJerarquia,
    rolesGrupos,
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
    resolverAreaPrincipalFormulario,
}) {
    const [activeTab, setActiveTab] = useState('datos');

    const toggleTodasAreasDepartamento = (depto) => {
        const idsDelDepartamento = (depto.areas || []).map((area) => area.id);
        if (idsDelDepartamento.length === 0) return;

        const todosSeleccionados = idsDelDepartamento.every((id) => data.areas.includes(id));
        let nuevasAreas;

        if (todosSeleccionados) {
            nuevasAreas = data.areas.filter((id) => !idsDelDepartamento.includes(id));
        } else {
            const nuevosIds = idsDelDepartamento.filter((id) => !data.areas.includes(id));
            nuevasAreas = [...data.areas, ...nuevosIds];
        }

        setData('areas', nuevasAreas);
        setData('area_id', resolverAreaPrincipalFormulario(nuevasAreas, data.area_id));
    };

    return (
        <>
            <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center shrink-0">
                <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 leading-none">
                    <ShieldCheck className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                    {usuarioEditando ? 'Ajustar Perfil Completo' : 'Alta de Nuevo Colaborador'}
                </h2>
                <button
                    type="button"
                    onClick={onCerrar}
                    className="theme-text-muted hover:theme-text-main transition-colors p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/5"
                >
                    <X className="w-6 h-6" />
                </button>
            </div>

            <div className={`p-3 md:p-4 border-b theme-border shrink-0 ${GELIA_SEGMENT_TABS_TRACK_COMPACT}`}>
                {TABS.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => setActiveTab(tab.id)}
                        className={`flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all outline-none min-w-0 min-h-[44px] ${
                            activeTab === tab.id
                                ? 'text-white shadow-sm'
                                : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'
                        }`}
                        style={activeTab === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <tab.icon className="w-4 h-4 shrink-0" />
                        <span className="truncate">{tab.label}</span>
                    </button>
                ))}
            </div>

            <div className="gelia-modal-body p-6 md:p-8 custom-scrollbar">
                {activeTab === 'datos' && (
                    <div className="space-y-8">
                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                                Identidad Personal
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Nombre(s) *</label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} type="text" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none focus:ring-1 focus:ring-transparent transition-all" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Ap. Paterno *</label>
                                    <input value={data.apellido_paterno} onChange={(e) => setData('apellido_paterno', e.target.value)} type="text" required className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Ap. Materno</label>
                                    <input value={data.apellido_materno} onChange={(e) => setData('apellido_materno', e.target.value)} type="text" className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Teléfono / WhatsApp</label>
                                    <div className="relative">
                                        <Smartphone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.telefono} onChange={(e) => setData('telefono', e.target.value)} type="text" className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Sexo Biológico</label>
                                    <select value={data.catalogo_sexo_id || ''} onChange={(e) => setData('catalogo_sexo_id', e.target.value)} className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none appearance-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }}>
                                        <option value="">Selecciona...</option>
                                        {(sexos || []).map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Fecha de Nacimiento</label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.fecha_nacimiento || ''} onChange={(e) => setData('fecha_nacimiento', e.target.value)} type="date" className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <Lock className="w-4 h-4 text-blue-500" />
                                Credenciales de Acceso
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Username *</label>
                                    <div className="relative">
                                        <AtSign className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.username} onChange={(e) => setData('username', e.target.value)} type="text" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Correo Electrónico *</label>
                                    <div className="relative">
                                        <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.email} onChange={(e) => setData('email', e.target.value)} type="email" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Contraseña</label>
                                    <div className="relative">
                                        <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                        <input value={data.password} onChange={(e) => setData('password', e.target.value)} type="password" required={!usuarioEditando} placeholder={usuarioEditando ? 'Dejar en blanco para conservar' : 'Asignar contraseña'} className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'rol' && (
                    <div className="space-y-8">
                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                <Briefcase className="w-4 h-4 text-orange-500" />
                                Roles y Jerarquías
                            </h3>
                            <div className="space-y-4">
                                <p className="text-xs font-black uppercase tracking-widest theme-text-muted ml-2">Asignación Principal</p>
                                <div className="flex flex-wrap gap-3">
                                    {rolesJerarquia.map((rol) => (
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
                            </div>
                        </div>

                        {rolesGrupos.length > 0 && (
                            <div>
                                <p className="text-xs font-black uppercase tracking-widest theme-text-muted ml-2 mb-3 flex items-center gap-2">
                                    <Layers className="w-4 h-4 shrink-0" />
                                    Plantillas de Grupo (pre-relleno)
                                </p>
                                <div className="flex flex-wrap gap-3">
                                    {rolesGrupos.map((rol) => (
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

                        <PermisosAtomicos
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
                        />
                    </div>
                )}

                {activeTab === 'organizacion' && (
                    <div>
                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                            <Network className="w-4 h-4 text-purple-500" />
                            Organización y Liderazgo
                        </h3>

                        <div className="grid grid-cols-1 gap-6">
                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">1. Departamentos Asignados (Múltiple)</label>
                                <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent">
                                    {(departamentos || []).map((depto) => (
                                        <button
                                            key={`depto-${depto.id}`}
                                            type="button"
                                            onClick={() => toggleSelection('departamentos', depto.id)}
                                            className={`px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${data.departamentos.includes(depto.id) ? 'shadow-sm text-white' : 'theme-element theme-border theme-text-muted'}`}
                                            style={data.departamentos.includes(depto.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                        >
                                            {data.departamentos.includes(depto.id) && <Check className="w-3 h-3" />}
                                            {depto.nombre}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">2. Áreas de Operación (Múltiple)</label>
                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 p-4 border theme-border rounded-2xl theme-element bg-transparent max-h-80 overflow-y-auto custom-scrollbar">
                                    {(departamentos || []).map((depto) => {
                                        const areasDepto = depto.areas || [];
                                        const todosSeleccionados = areasDepto.length > 0
                                            && areasDepto.every((area) => data.areas.includes(area.id));

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
                                                            const seleccionada = data.areas.includes(area.id);
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
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">
                                    3. Área Principal (Reportes y RH)
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
                                        <span className="block mt-1 text-amber-600 dark:text-amber-400">
                                            Se preseleccionó la primera área asignada; confirma o cambia la principal antes de guardar.
                                        </span>
                                    )}
                                </p>
                                {errors.area_id && (
                                    <p className="text-[9px] text-red-500 font-bold ml-2 mt-1">{errors.area_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">4. Reporta a (Gerentes / Líderes)</label>
                                <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent max-h-32 overflow-y-auto custom-scrollbar">
                                    {(posiblesGerentes || []).length === 0 ? (
                                        <span className="text-xs theme-text-muted italic px-2">No hay gerentes disponibles.</span>
                                    ) : (
                                        (posiblesGerentes || []).map((gerente) => (
                                            <button
                                                key={`gerente-${gerente.id}`}
                                                type="button"
                                                onClick={() => toggleSelection('gerentes', gerente.id)}
                                                className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${data.gerentes.includes(gerente.id) ? 'shadow-sm text-white' : 'bg-transparent theme-border theme-text-muted hover:bg-black/5 dark:hover:bg-white/5'}`}
                                                style={data.gerentes.includes(gerente.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                            >
                                                {data.gerentes.includes(gerente.id) && <Check className="w-3 h-3" />}
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

            <div className="gelia-modal-footer p-6 md:p-8">
                <button type="button" onClick={onSubmit} disabled={processing} className={`${THEME_BTN_PRIMARY} w-full`}>
                    <Save className="w-5 h-5 shrink-0" />
                    {processing
                        ? 'Guardando...'
                        : usuarioEditando
                            ? 'Guardar cambios'
                            : 'Registrar colaborador'}
                </button>
            </div>
        </>
    );
}
