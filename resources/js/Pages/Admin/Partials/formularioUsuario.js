import { etiquetaPermiso, permisosDePlantilla } from '../../../utils/permisos';

export const FORMULARIO_USUARIO_VACIO = {
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
    departamento_id: '',
    areas: [],
    area_id: '',
    gerentes: [],
    roles_asignados: [],
    permisos_individuales: [],
    plantilla_origen: '',
    plantilla_por_permiso: {},
    sucursales: [],
    sucursal_principal_id: '',
};

export function formularioVacio() {
    return {
        ...FORMULARIO_USUARIO_VACIO,
        departamentos: [],
        areas: [],
        gerentes: [],
        roles_asignados: [],
        permisos_individuales: [],
        plantilla_por_permiso: {},
        sucursales: [],
    };
}

/** Resuelve área, departamento o sucursal principal para el formulario. */
export function resolverPrincipalFormulario(idsAsignados = [], principalId = null) {
    const ids = (idsAsignados || [])
        .map((id) => Number(id))
        .filter((id) => !Number.isNaN(id));
    if (ids.length === 0) return '';

    const parsed = principalId !== '' && principalId != null ? Number(principalId) : null;
    if (parsed != null && ids.includes(parsed)) return String(parsed);
    if (ids.length >= 1) return String(ids[0]);

    return '';
}

export function alternarRelacion(data, campo, idOrName) {
    const actuales = data[campo] || [];
    const nuevos = actuales.includes(idOrName)
        ? actuales.filter((item) => item !== idOrName)
        : [...actuales, idOrName];
    const next = { ...data, [campo]: nuevos };

    if (campo === 'areas') {
        next.area_id = resolverPrincipalFormulario(nuevos, data.area_id);
    }
    if (campo === 'departamentos') {
        next.departamento_id = resolverPrincipalFormulario(nuevos, data.departamento_id);
    }
    if (campo === 'sucursales') {
        next.sucursal_principal_id = resolverPrincipalFormulario(nuevos, data.sucursal_principal_id);
    }

    return next;
}

export function datosFormularioDesdeUsuario(usuario) {
    if (!usuario) return formularioVacio();

    const rolesJerarquicos = (usuario.roles || [])
        .map((rol) => rol.name)
        .filter((nombre) => !nombre.includes('Grupo:'));

    const procMap = {};
    (usuario.permisos_procedencia || []).forEach((meta) => {
        if (meta.permiso) procMap[meta.permiso] = meta;
    });

    const plantillaMap = {};
    Object.entries(procMap).forEach(([permiso, meta]) => {
        if (meta.plantilla_origen) plantillaMap[permiso] = meta.plantilla_origen;
    });

    const departamentos = usuario.departamentos ? usuario.departamentos.map((d) => d.id) : [];
    const areas = usuario.areas ? usuario.areas.map((a) => a.id) : [];
    const sucursalIds = usuario.sucursales ? usuario.sucursales.map((s) => s.id) : [];
    const sucursalPrincipalMarcada = (usuario.sucursales || []).find((s) => s.es_principal);

    return {
        ...formularioVacio(),
        name: (usuario.name || '').trim(),
        apellido_paterno: (usuario.apellido_paterno || '').trim(),
        apellido_materno: (usuario.apellido_materno || '').trim(),
        username: (usuario.username || '').trim(),
        email: (usuario.email || '').trim(),
        password: '',
        telefono: (usuario.telefono || '').trim(),
        fecha_nacimiento: usuario.fecha_nacimiento || '',
        catalogo_sexo_id: usuario.catalogo_sexo_id || '',
        departamentos,
        departamento_id: resolverPrincipalFormulario(departamentos, usuario.departamento_id),
        areas,
        area_id: resolverPrincipalFormulario(areas, usuario.area_id),
        gerentes: usuario.gerentes ? usuario.gerentes.map((g) => g.id) : [],
        roles_asignados: rolesJerarquicos,
        permisos_individuales: usuario.permissions ? usuario.permissions.map((p) => p.name) : [],
        plantilla_origen: '',
        plantilla_por_permiso: plantillaMap,
        sucursales: sucursalIds,
        sucursal_principal_id: resolverPrincipalFormulario(
            sucursalIds,
            sucursalPrincipalMarcada?.id ?? '',
        ),
    };
}

export function mapaPlantillaInicial(usuario) {
    return datosFormularioDesdeUsuario(usuario).plantilla_por_permiso;
}

function quitarPermisosDePlantilla(permisos, mapa, nombre, roles) {
    if (!nombre) return { permisos: [...(permisos || [])], mapa: { ...(mapa || {}) } };

    const dePlantilla = new Set(permisosDePlantilla([nombre], roles));
    const siguientes = (permisos || []).filter((permiso) => {
        if (!dePlantilla.has(permiso)) return true;
        return mapa?.[permiso] !== nombre;
    });
    const siguienteMapa = { ...(mapa || {}) };
    Object.entries(siguienteMapa).forEach(([permiso, plantilla]) => {
        if (plantilla === nombre || !siguientes.includes(permiso)) delete siguienteMapa[permiso];
    });

    return { permisos: siguientes, mapa: siguienteMapa };
}

/**
 * Aplica o retira una plantilla en un solo cálculo.
 * Cambiar de plantilla no reincorpora los permisos de la anterior.
 */
export function aplicarPlantillaPermisos({
    permisos = [],
    plantillaActual = '',
    plantillaNueva,
    mapa = {},
    roles = [],
}) {
    if (plantillaActual === plantillaNueva) {
        const limpio = quitarPermisosDePlantilla(permisos, mapa, plantillaNueva, roles);
        return {
            permisos: limpio.permisos,
            mapa: limpio.mapa,
            plantillaSeleccionada: '',
            plantilla_origen: '',
        };
    }

    const base = quitarPermisosDePlantilla(permisos, mapa, plantillaActual, roles);
    const nuevos = permisosDePlantilla([plantillaNueva], roles);
    const permisosFinales = [...new Set([...base.permisos, ...nuevos])];
    const mapaFinal = { ...base.mapa };
    Object.keys(mapaFinal).forEach((clave) => {
        if (!permisosFinales.includes(clave)) delete mapaFinal[clave];
    });
    nuevos.forEach((permiso) => {
        mapaFinal[permiso] = plantillaNueva;
    });

    return {
        permisos: permisosFinales,
        mapa: mapaFinal,
        plantillaSeleccionada: plantillaNueva,
        plantilla_origen: plantillaNueva,
    };
}

export function limpiarMapaPlantilla(mapa, permisos) {
    const next = { ...(mapa || {}) };
    Object.keys(next).forEach((clave) => {
        if (!(permisos || []).includes(clave)) delete next[clave];
    });
    return next;
}

export function filtrarIdsEnCatalogo(ids, catalogoIds) {
    const permitidos = new Set((catalogoIds || []).map((id) => Number(id)));
    return (ids || []).filter((id) => permitidos.has(Number(id)));
}

function idsFueraDeCatalogo(ids, catalogoIds) {
    const permitidos = new Set((catalogoIds || []).map((id) => Number(id)));
    return (ids || []).filter((id) => !permitidos.has(Number(id)));
}

/** El principal sigue en la unión de lo visible y lo que el servidor va a conservar. */
export function alinearPrincipal(idsVisibles, idsOcultos, principalId) {
    const union = [...(idsVisibles || []), ...(idsOcultos || [])];
    if (union.length === 0) return null;

    const principal = principalId !== '' && principalId != null ? Number(principalId) : null;
    if (principal != null && !Number.isNaN(principal) && union.some((id) => Number(id) === principal)) {
        return principal;
    }
    if (union.length === 1) return Number(union[0]);

    return null;
}

export function esAdministracionGlobal(auth, esSuperAdmin) {
    if (esSuperAdmin) return true;
    const roles = auth?.user?.roles || [];
    return roles.includes('Administrador') || roles.includes('Super Admin');
}

/**
 * Omite relaciones que el actor no ve en su catálogo.
 * El servidor vuelve a incorporar las que ya existían fuera de su alcance.
 * Administración global no se filtra: su catálogo activo no incluye inactivos
 * y el servidor no los restauraría.
 */
export function omitirRelacionesFueraDeCatalogo(data, {
    esAdministracionGlobal: global = false,
    departamentos = [],
    sucursales = [],
} = {}) {
    if (global) return data;

    const idsDeptos = (departamentos || []).map((depto) => depto.id);
    const idsAreas = (departamentos || []).flatMap((depto) => (depto.areas || []).map((area) => area.id));
    const idsSucursales = (sucursales || []).map((sucursal) => sucursal.id);
    const departamentosVisibles = filtrarIdsEnCatalogo(data.departamentos, idsDeptos);
    const areasVisibles = filtrarIdsEnCatalogo(data.areas, idsAreas);
    const sucursalesVisibles = filtrarIdsEnCatalogo(data.sucursales, idsSucursales);

    return {
        ...data,
        departamentos: departamentosVisibles,
        areas: areasVisibles,
        sucursales: sucursalesVisibles,
        departamento_id: alinearPrincipal(
            departamentosVisibles,
            idsFueraDeCatalogo(data.departamentos, idsDeptos),
            data.departamento_id,
        ),
        area_id: alinearPrincipal(
            areasVisibles,
            idsFueraDeCatalogo(data.areas, idsAreas),
            data.area_id,
        ),
        sucursal_principal_id: alinearPrincipal(
            sucursalesVisibles,
            idsFueraDeCatalogo(data.sucursales, idsSucursales),
            data.sucursal_principal_id,
        ),
    };
}

/**
 * Contrato de @inertiajs/core 3.0.3 (dist/index.js, Response.process / Request.send):
 * - Si onHttpException devuelve false, no hay setPage ni onError.
 * - Un 422 con cabecera X-Inertia solo entrega la bolsa si onHttpException no devuelve false.
 * - 403 y 500 de esta app son una página Inertia Error (bootstrap/app.php). setPage la
 *   monta y destruye el formulario. Hay que detener esa visita y publicar el status.
 * - Un fallo de red no cambia de página. No se detiene el evento inertia:networkError.
 * - Un ticket distinto al formulario abierto no cierra ni pisa el estado nuevo.
 */
export function decidirRespuestaGuardado({ ticket, ticketActual, status = null, red = false }) {
    if (ticket !== ticketActual) {
        return {
            aplicar: false,
            cerrar: false,
            conservarDatos: true,
            detenerInertia: true,
            publicar: false,
            tipo: 'obsoleta',
            status: status ?? null,
        };
    }

    if (red) {
        return {
            aplicar: true,
            cerrar: false,
            conservarDatos: true,
            detenerInertia: false,
            publicar: true,
            tipo: 'red',
            status: null,
        };
    }

    if (status === 422) {
        return {
            aplicar: true,
            cerrar: false,
            conservarDatos: true,
            detenerInertia: false,
            publicar: false,
            tipo: 'validacion',
            status: 422,
        };
    }

    if (typeof status === 'number' && status >= 400) {
        return {
            aplicar: true,
            cerrar: false,
            conservarDatos: true,
            detenerInertia: true,
            publicar: true,
            tipo: 'http',
            status,
        };
    }

    return {
        aplicar: true,
        cerrar: true,
        conservarDatos: true,
        detenerInertia: false,
        publicar: false,
        tipo: 'ok',
        status: status ?? null,
    };
}

export function publicarIncidenciaGuardado(detail) {
    if (typeof console !== 'undefined') {
        console.error('No se pudo guardar el usuario', detail);
    }
    if (typeof document === 'undefined') return;
    document.dispatchEvent(new CustomEvent('gelia:error-guardado-usuario', { detail }));
}

export function principalFueraDeCatalogo(principalId, catalogo = []) {
    if (principalId === '' || principalId == null) return false;
    const id = Number(principalId);
    if (Number.isNaN(id)) return false;
    return !(catalogo || []).some((item) => Number(item.id) === id);
}

export function areasDelCatalogo(departamentos = []) {
    return (departamentos || []).flatMap((depto) => depto.areas || []);
}

export function textoError(valor) {
    if (Array.isArray(valor)) return valor.filter(Boolean).join(' ');
    if (valor == null) return '';
    return String(valor);
}

export function normalizarErrores(errores) {
    const next = {};
    Object.entries(errores || {}).forEach(([campo, valor]) => {
        const texto = textoError(valor);
        if (texto) next[campo] = texto;
    });
    return next;
}

export function aplicarErroresSinLimpiarDatos(data, errores) {
    return {
        data,
        errors: normalizarErrores(errores),
    };
}

export function resumenErrores(errores) {
    return Object.values(normalizarErrores(errores));
}

export function mensajeErrorHttp(status) {
    if (status === 403) return 'No tienes permiso para guardar estos cambios. Los datos del formulario siguen aquí.';
    if (status === 500) return 'No se pudo guardar por un error del servidor. Los datos del formulario siguen aquí.';
    return 'No se pudo guardar. Los datos del formulario siguen aquí.';
}

export function mensajeErrorRed() {
    return 'No hubo conexión con el servidor. Los datos del formulario siguen aquí.';
}

export function tomarEnvio(ref) {
    if (ref?.current) return false;
    ref.current = true;
    return true;
}

export function soltarEnvio(ref) {
    if (ref) ref.current = false;
}

export function debeConfirmarCierre(isDirty) {
    return Boolean(isDirty);
}

export const MENSAJE_CIERRE_SUCIO = 'Hay cambios sin guardar. Si cierras el formulario, se perderán. ¿Cerrar de todas formas?';

const ANCHO_DIRECTORIO_MIN = 280;
const ANCHO_DRAWER_MIN = 760;

/** Lateral solo si el área útil cabe el directorio y un drawer de 760 px. */
export function modoDrawerColaborador(anchoContenido) {
    const ancho = Number(anchoContenido);
    if (!Number.isFinite(ancho) || ancho < ANCHO_DIRECTORIO_MIN + ANCHO_DRAWER_MIN) {
        return 'superpuesto';
    }
    return 'lateral';
}

/** Ancho del drawer lateral según el área útil ya medida, no según el viewport. */
export function anchoDrawerColaborador(anchoContenido) {
    if (modoDrawerColaborador(anchoContenido) !== 'lateral') return null;
    const libre = Math.round(Number(anchoContenido) - ANCHO_DIRECTORIO_MIN);
    return Math.min(880, Math.max(ANCHO_DRAWER_MIN, libre));
}

function textoPlano(valor) {
    if (valor == null) return '';
    return String(valor).trim();
}

function indiceNombre(items, campos) {
    const mapa = new Map();
    (items || []).forEach((item) => {
        if (!item || item.id == null) return;
        const nombre = campos.map((campo) => item[campo]).filter(Boolean).join(' ').trim();
        mapa.set(String(item.id), nombre || `Sin nombre (${item.id})`);
    });
    return mapa;
}

function diffConjunto(antes, despues, etiquetar) {
    const origen = new Set((antes || []).map(String));
    const destino = new Set((despues || []).map(String));
    return [
        ...[...destino].filter((id) => !origen.has(id)).map((id) => ({
            id: `alta-${id}`,
            tipo: 'alta',
            etiqueta: etiquetar(id),
            clave: id,
        })),
        ...[...origen].filter((id) => !destino.has(id)).map((id) => ({
            id: `baja-${id}`,
            tipo: 'baja',
            etiqueta: etiquetar(id),
            clave: id,
        })),
    ];
}

function cambioPrincipal(id, etiqueta, antes, despues, mapa) {
    const origen = textoPlano(antes);
    const destino = textoPlano(despues);
    if (origen === destino) return [];
    return [{
        id,
        tipo: 'valor',
        etiqueta,
        antes: mapa.get(origen) || (origen ? `Sin nombre (${origen})` : 'Sin dato'),
        despues: mapa.get(destino) || (destino ? `Sin nombre (${destino})` : 'Sin dato'),
    }];
}

/**
 * Compara el colaborador al abrir el drawer con el formulario actual.
 * No incluye la contraseña en claro. Un valor que vuelve al inicial no cuenta.
 */
export function compararFormularioUsuario(inicial, actual, catalogos = {}) {
    const antes = inicial || formularioVacio();
    const despues = actual || formularioVacio();
    const grupos = [];
    const sexo = indiceNombre(catalogos.sexos, ['nombre']);
    const datos = [
        ['name', 'Nombre'],
        ['apellido_paterno', 'Apellido paterno'],
        ['apellido_materno', 'Apellido materno'],
        ['username', 'Usuario'],
        ['email', 'Correo'],
        ['telefono', 'Teléfono'],
        ['fecha_nacimiento', 'Fecha de nacimiento'],
    ].flatMap(([campo, etiqueta]) => {
        const origen = textoPlano(antes[campo]);
        const destino = textoPlano(despues[campo]);
        if (origen === destino) return [];
        return [{
            id: campo,
            tipo: 'valor',
            etiqueta,
            antes: origen || 'Sin dato',
            despues: destino || 'Sin dato',
        }];
    });

    const sexoAntes = textoPlano(antes.catalogo_sexo_id);
    const sexoDespues = textoPlano(despues.catalogo_sexo_id);
    if (sexoAntes !== sexoDespues) {
        datos.push({
            id: 'catalogo_sexo_id',
            tipo: 'valor',
            etiqueta: 'Sexo',
            antes: sexo.get(sexoAntes) || (sexoAntes ? `Sin nombre (${sexoAntes})` : 'Sin dato'),
            despues: sexo.get(sexoDespues) || (sexoDespues ? `Sin nombre (${sexoDespues})` : 'Sin dato'),
        });
    }

    if (textoPlano(despues.password)) {
        datos.push({
            id: 'password',
            tipo: 'valor',
            etiqueta: 'Contraseña',
            antes: 'Sin cambio',
            despues: 'Se reemplaza al guardar',
        });
    }

    if (datos.length) grupos.push({ id: 'datos', label: 'Datos generales', cambios: datos });

    const cambiosRoles = diffConjunto(
        antes.roles_asignados,
        despues.roles_asignados,
        (nombre) => nombre,
    );
    const plantillaAntes = textoPlano(antes.plantilla_origen);
    const plantillaDespues = textoPlano(despues.plantilla_origen);
    if (plantillaAntes !== plantillaDespues) {
        cambiosRoles.push({
            id: 'plantilla',
            tipo: 'valor',
            etiqueta: 'Plantilla',
            antes: plantillaAntes || 'Sin plantilla',
            despues: plantillaDespues || 'Sin plantilla',
        });
    }
    if (cambiosRoles.length) grupos.push({ id: 'roles', label: 'Roles y plantillas', cambios: cambiosRoles });

    const cambiosPermisos = diffConjunto(
        antes.permisos_individuales,
        despues.permisos_individuales,
        (nombre) => etiquetaPermiso(nombre),
    );
    if (cambiosPermisos.length) grupos.push({ id: 'permisos', label: 'Permisos', cambios: cambiosPermisos });

    const departamentos = indiceNombre(catalogos.departamentos, ['nombre']);
    const areas = indiceNombre(
        [...(catalogos.areas || []), ...(catalogos.departamentos || []).flatMap((item) => item.areas || [])],
        ['nombre'],
    );
    const sucursales = indiceNombre(catalogos.sucursales, ['nombre']);
    const gerentes = indiceNombre(catalogos.gerentes, ['name', 'apellido_paterno', 'apellido_materno']);
    const organizacion = [
        ...diffConjunto(antes.departamentos, despues.departamentos, (id) => departamentos.get(id) || `Departamento ${id}`),
        ...cambioPrincipal('departamento', 'Departamento principal', antes.departamento_id, despues.departamento_id, departamentos),
        ...diffConjunto(antes.areas, despues.areas, (id) => areas.get(id) || `Área ${id}`),
        ...cambioPrincipal('area', 'Área principal', antes.area_id, despues.area_id, areas),
        ...diffConjunto(antes.gerentes, despues.gerentes, (id) => gerentes.get(id) || `Gerente ${id}`),
        ...diffConjunto(antes.sucursales, despues.sucursales, (id) => sucursales.get(id) || `Sucursal ${id}`),
        ...cambioPrincipal('sucursal', 'Sucursal principal', antes.sucursal_principal_id, despues.sucursal_principal_id, sucursales),
    ];
    if (organizacion.length) grupos.push({ id: 'organizacion', label: 'Organización', cambios: organizacion });

    const total = grupos.reduce((suma, grupo) => suma + grupo.cambios.length, 0);

    return {
        total,
        contadores: {
            datos: datos.length,
            roles: cambiosRoles.length,
            permisosAlta: cambiosPermisos.filter((cambio) => cambio.tipo === 'alta').length,
            permisosBaja: cambiosPermisos.filter((cambio) => cambio.tipo === 'baja').length,
            organizacion: organizacion.length,
        },
        grupos,
    };
}
