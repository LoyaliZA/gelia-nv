import React from 'react';

const MODOS = [
    { id: 'demo', label: 'Demo' },
    { id: 'extremo', label: 'Extremo' },
    { id: 'vacio', label: 'Vacío' },
    { id: 'muchas', label: 'Muchas opciones' },
];

function rol(id, name, permisos = []) {
    return {
        id,
        name,
        permissions: permisos.map((permiso, index) => ({ id: id * 100 + index, name: permiso })),
    };
}

function depto(id, nombre, areas) {
    return {
        id,
        nombre,
        areas: areas.map((area, index) => ({ id: id * 100 + index + 1, nombre: area })),
    };
}

const correoLargo = 'bartholomew.fitzgerald@northwind-industries-holdings.example.com';
const nombreLargo = 'Aleksandra Wiśniewska-Kowalczyk';

function usuarioBase(extra) {
    return {
        id: extra.id,
        name: extra.name,
        apellido_paterno: extra.apellido_paterno ?? '',
        apellido_materno: extra.apellido_materno ?? '',
        username: extra.username,
        email: extra.email,
        telefono: extra.telefono ?? '',
        fecha_nacimiento: extra.fecha_nacimiento ?? '',
        catalogo_sexo_id: extra.catalogo_sexo_id ?? '',
        foto_perfil: extra.foto_perfil ?? null,
        departamento_id: extra.departamento_id ?? '',
        area_id: extra.area_id ?? '',
        departamentos: extra.departamentos ?? [],
        areas: extra.areas ?? [],
        gerentes: extra.gerentes ?? [],
        sucursales: extra.sucursales ?? [],
        roles: extra.roles ?? [],
        permissions: extra.permissions ?? [],
        permisos_procedencia: extra.permisos_procedencia ?? [],
    };
}

export function overlayDatos(modo) {
    if (modo === 'vacio') {
        return {
            modo,
            usuarios: [],
            departamentos: [],
            sucursales: [],
            roles: [],
            posiblesGerentes: [],
            sexos: [],
            todosLosPermisos: [],
            catalogoPermisos: [],
            usuario: null,
        };
    }

    if (modo === 'muchas') {
        const departamentos = Array.from({ length: 12 }, (_, index) => depto(
            index + 1,
            index === 0 ? 'Operación y distribución regional norte' : `Departamento ${index + 1}`,
            Array.from({ length: 6 }, (__, area) => `Área ${index + 1}.${area + 1}`),
        ));
        const sucursales = Array.from({ length: 40 }, (_, index) => ({
            id: index + 1,
            nombre: index === 0 ? 'Sucursal Centro Histórico y Bodega Annex' : `Sucursal ${index + 1}`,
            codigo: `S${index + 1}`,
        }));
        const roles = [
            rol(1, 'Colaborador', ['pedidos.ver']),
            rol(2, 'Gerente', ['pedidos.ver']),
            ...Array.from({ length: 8 }, (_, index) => rol(index + 3, `Grupo: Plantilla operativa ${index + 1}`, [`modulo.permiso_${index + 1}`])),
        ];
        const usuario = usuarioBase({
            id: 9001,
            name: 'Jo',
            apellido_paterno: 'L',
            username: 'jo.l',
            email: 'jo@example.com',
            departamentos: [{ id: 1, nombre: departamentos[0].nombre }],
            departamento_id: 1,
            sucursales: sucursales.slice(0, 8).map((sucursal) => ({ ...sucursal, es_principal: sucursal.id === 1 })),
            roles: [{ id: 1, name: 'Colaborador' }, { id: 2, name: 'Gerente' }],
        });

        return {
            modo,
            usuarios: [usuario],
            departamentos,
            sucursales,
            roles,
            posiblesGerentes: [{ id: 50, name: 'Dana', apellido_paterno: 'Cruz' }],
            sexos: [{ id: 1, nombre: 'No especificado' }],
            todosLosPermisos: roles.flatMap((item) => item.permissions),
            catalogoPermisos: roles.flatMap((item) => item.permissions),
            usuario,
        };
    }

    const departamentos = [
        depto(1, 'Distribución y atención a sucursales', ['Mostrador centro', 'CEDIS nocturno']),
        depto(2, 'Administración', []),
    ];
    const sucursales = [
        { id: 1, nombre: 'Sucursal Centro Histórico', codigo: 'SCH' },
        { id: 2, nombre: 'Sucursal Norte', codigo: 'SN' },
        { id: 3, nombre: 'Sucursal Bodega Annex', codigo: 'SBA' },
    ];
    const roles = [
        rol(1, 'Colaborador', ['pedidos.ver', 'pedidos.capturar']),
        rol(2, 'Gerente', ['pedidos.ver']),
        rol(3, 'Grupo: Captura de pedidos', ['pedidos.ver', 'pedidos.capturar']),
        rol(4, 'Grupo: Auditoría de sucursal', ['auditoria.ver']),
    ];
    const permisosAsignados = Array.from({ length: 40 }, (_, indice) => ({
        id: 7000 + indice,
        name: `solicitudes.gestion_de_pedidos_regionales.consultar_listado_completo_${indice}`,
    }));
    const permisosDisponibles = Array.from({ length: 40 }, (_, indice) => ({
        id: 7100 + indice,
        name: `solicitudes.gestion_de_pedidos_regionales.registrar_movimiento_de_sucursal_${indice}`,
    }));
    const principal = usuarioBase({
        id: 9100,
        name: nombreLargo,
        apellido_paterno: 'Kowalczyk',
        apellido_materno: '',
        username: 'aleksandra.w',
        email: correoLargo,
        telefono: '',
        departamentos: [
            { id: 1, nombre: departamentos[0].nombre },
            { id: 2, nombre: 'Administración' },
        ],
        departamento_id: 1,
        areas: [{ id: 101, nombre: 'Mostrador centro' }, { id: 102, nombre: 'CEDIS nocturno' }],
        area_id: 101,
        sucursales: sucursales.map((sucursal) => ({ ...sucursal, es_principal: sucursal.id === 3 })),
        gerentes: [{ id: 50, name: 'Dana', apellido_paterno: 'Cruz' }],
        roles: [
            { id: 1, name: 'Colaborador' },
            { id: 2, name: 'Gerente' },
            { id: 4, name: 'Grupo: Auditoría de sucursal' },
        ],
        permissions: [
            { id: 1, name: 'pedidos.ver' },
            { id: 2, name: 'pedidos.capturar' },
            { id: 3, name: 'auditoria.ver' },
            ...permisosAsignados,
        ],
        permisos_procedencia: [
            { permiso: 'pedidos.ver', plantilla_origen: 'Grupo: Captura de pedidos' },
            { permiso: 'pedidos.capturar', plantilla_origen: 'Grupo: Captura de pedidos' },
            { permiso: permisosAsignados[0].name, plantilla_origen: 'sistema:migracion' },
            {
                permiso: permisosAsignados[1].name,
                asignado_por: { id: 50, nombre: 'Dana Cruz', es_administrador: true },
            },
            { permiso: permisosAsignados[2].name, asignado_por: { id: 1, nombre: 'Quien edita' } },
        ],
    });
    const corto = usuarioBase({
        id: 9101,
        name: 'Jo',
        apellido_paterno: 'L',
        username: 'jo',
        email: 'jo@example.com',
    });
    const sinDepartamento = usuarioBase({
        id: 9102,
        name: 'Ólafur',
        apellido_paterno: 'Ólafsson',
        username: 'olafur',
        email: 'olafur@example.com',
    });

    return {
        modo: 'extremo',
        usuarios: [principal, corto, sinDepartamento],
        departamentos,
        sucursales,
        roles,
        posiblesGerentes: [{ id: 50, name: nombreLargo, apellido_paterno: 'Kowalczyk' }],
        sexos: [],
        todosLosPermisos: [
            ...roles.flatMap((item) => item.permissions),
            ...permisosAsignados,
            ...permisosDisponibles,
        ],
        catalogoPermisos: [
            ...roles.flatMap((item) => item.permissions),
            ...permisosAsignados,
            ...permisosDisponibles,
        ],
        usuario: principal,
    };
}

export function ControlesDatosExtremos({ modo = 'demo', onCambiar }) {
    return (
        <div
            role="group"
            aria-label="Datos de prueba"
            style={{
                position: 'fixed',
                bottom: 16,
                left: '50%',
                transform: 'translateX(-50%)',
                display: 'flex',
                gap: 4,
                padding: 4,
                borderRadius: 999,
                background: 'gray',
                zIndex: 80,
                fontFamily: 'system-ui, sans-serif',
            }}
        >
            {MODOS.map((item) => {
                const activo = modo === item.id;
                return (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => onCambiar(item.id)}
                        style={{
                            border: 0,
                            borderRadius: 999,
                            padding: '6px 10px',
                            background: activo ? 'white' : 'transparent',
                            color: activo ? 'black' : 'white',
                            font: '12px system-ui, sans-serif',
                            cursor: 'pointer',
                        }}
                    >
                        {item.label}
                    </button>
                );
            })}
        </div>
    );
}
