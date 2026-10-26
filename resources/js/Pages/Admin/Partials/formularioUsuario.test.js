import { describe, expect, it } from 'vitest';
import {
    alinearPrincipal,
    alternarRelacion,
    aplicarErroresSinLimpiarDatos,
    aplicarPlantillaPermisos,
    anchoDrawerColaborador,
    compararFormularioUsuario,
    datosFormularioDesdeUsuario,
    decidirRespuestaGuardado,
    formularioVacio,
    modoDrawerColaborador,
    omitirRelacionesFueraDeCatalogo,
    soltarEnvio,
    tomarEnvio,
} from './formularioUsuario';

const roles = [
    {
        name: 'Grupo: Captura',
        permissions: [{ name: 'pedidos.ver' }, { name: 'pedidos.capturar' }],
    },
    {
        name: 'Grupo: Auditoria',
        permissions: [{ name: 'auditoria.ver' }, { name: 'pedidos.ver' }],
    },
];

describe('relaciones del formulario', () => {
    it('selecciona y retira varias relaciones y conserva un principal coherente', () => {
        let data = formularioVacio();
        data = alternarRelacion(data, 'departamentos', 4);
        data = alternarRelacion(data, 'departamentos', 9);
        expect(data.departamentos).toEqual([4, 9]);
        expect(data.departamento_id).toBe('4');

        data = alternarRelacion(data, 'departamentos', 4);
        expect(data.departamentos).toEqual([9]);
        expect(data.departamento_id).toBe('9');

        data = alternarRelacion(data, 'sucursales', 2);
        data = alternarRelacion(data, 'sucursales', 7);
        data = { ...data, sucursal_principal_id: '7' };
        data = alternarRelacion(data, 'sucursales', 2);
        expect(data.sucursales).toEqual([7]);
        expect(data.sucursal_principal_id).toBe('7');
    });

    it('al quitar lo visible conserva el id oculto como principal', () => {
        const payload = omitirRelacionesFueraDeCatalogo({
            ...formularioVacio(),
            departamentos: [9],
            departamento_id: '2',
            areas: [90],
            area_id: '20',
            sucursales: [8],
            sucursal_principal_id: '3',
        }, {
            esAdministracionGlobal: false,
            departamentos: [{ id: 2, areas: [{ id: 20 }] }],
            sucursales: [{ id: 3 }],
        });

        expect(payload.departamentos).toEqual([]);
        expect(payload.departamento_id).toBe(9);
        expect(payload.areas).toEqual([]);
        expect(payload.area_id).toBe(90);
        expect(payload.sucursales).toEqual([]);
        expect(payload.sucursal_principal_id).toBe(8);
    });

    it('no filtra el catalogo de administracion global', () => {
        const data = { ...formularioVacio(), departamentos: [9], departamento_id: 9 };
        expect(omitirRelacionesFueraDeCatalogo(data, {
            esAdministracionGlobal: true,
            departamentos: [],
            sucursales: [],
        })).toBe(data);
    });

    it('un principal que ya no queda en ninguna relacion se vacia si hay mas de una', () => {
        expect(alinearPrincipal([1], [8, 9], 2)).toBe(null);
        expect(alinearPrincipal([], [8], 2)).toBe(8);
    });
});

describe('plantillas', () => {
    it('cambiar de plantilla no reincorpora los permisos de la anterior', () => {
        const primero = aplicarPlantillaPermisos({
            permisos: ['propio.permiso'],
            plantillaActual: '',
            plantillaNueva: 'Grupo: Captura',
            mapa: {},
            roles,
        });
        const segundo = aplicarPlantillaPermisos({
            permisos: primero.permisos,
            plantillaActual: primero.plantillaSeleccionada,
            plantillaNueva: 'Grupo: Auditoria',
            mapa: primero.mapa,
            roles,
        });

        expect(segundo.permisos).not.toContain('pedidos.capturar');
        expect(segundo.permisos).toContain('auditoria.ver');
        expect(segundo.permisos).toContain('propio.permiso');
        expect(segundo.mapa['pedidos.capturar']).toBeUndefined();
        expect(segundo.mapa['auditoria.ver']).toBe('Grupo: Auditoria');
    });
});

describe('estado inicial y errores', () => {
    it('la alta vacia no arrastra la edicion de otro usuario', () => {
        const editado = datosFormularioDesdeUsuario({
            name: 'Ana',
            apellido_paterno: 'Luz',
            email: 'ana@example.com',
            username: 'ana',
            roles: [{ name: 'Gerente' }, { name: 'Grupo: Captura' }],
            departamentos: [{ id: 3 }],
            departamento_id: 3,
            permissions: [{ name: 'pedidos.ver' }],
        });
        const alta = datosFormularioDesdeUsuario(null);
        const otro = datosFormularioDesdeUsuario({
            name: 'Bo',
            apellido_paterno: 'Sol',
            email: 'bo@example.com',
            username: 'bo',
        });

        expect(editado.roles_asignados).toEqual(['Gerente']);
        expect(editado.password).toBe('');
        expect(alta.name).toBe('');
        expect(alta.departamentos).toEqual([]);
        expect(otro.name).toBe('Bo');
        expect(otro.email).not.toBe(editado.email);
    });

    it('aplica errores sin borrar entradas validas', () => {
        const data = { ...formularioVacio(), name: 'Ana', email: 'ana@example.com' };
        const resultado = aplicarErroresSinLimpiarDatos(data, {
            email: ['El correo ya está en uso.'],
            password: 'La contraseña debe tener al menos 8 caracteres.',
        });

        expect(resultado.data).toBe(data);
        expect(resultado.data.name).toBe('Ana');
        expect(resultado.errors.email).toBe('El correo ya está en uso.');
        expect(resultado.errors.password).toContain('8');
    });
});

describe('envio y respuestas de Inertia 3', () => {
    it('ignora el segundo disparo hasta soltar el candado', () => {
        const ref = { current: false };
        expect(tomarEnvio(ref)).toBe(true);
        expect(tomarEnvio(ref)).toBe(false);
        soltarEnvio(ref);
        expect(tomarEnvio(ref)).toBe(true);
    });

    it('una respuesta vieja no cierra ni pisa el formulario nuevo', () => {
        const vieja = decidirRespuestaGuardado({ ticket: 1, ticketActual: 2, status: 200 });
        const errorViejo = decidirRespuestaGuardado({ ticket: 1, ticketActual: 2, status: 422 });
        expect(vieja.cerrar).toBe(false);
        expect(vieja.aplicar).toBe(false);
        expect(vieja.conservarDatos).toBe(true);
        expect(errorViejo.aplicar).toBe(false);
        expect(errorViejo.detenerInertia).toBe(true);
    });

    it('un 422 deja pasar la bolsa y un 403 o 500 no sustituye la pagina', () => {
        const validacion = decidirRespuestaGuardado({ ticket: 4, ticketActual: 4, status: 422 });
        const prohibido = decidirRespuestaGuardado({ ticket: 4, ticketActual: 4, status: 403 });
        const servidor = decidirRespuestaGuardado({ ticket: 4, ticketActual: 4, status: 500 });
        const red = decidirRespuestaGuardado({ ticket: 4, ticketActual: 4, red: true });

        expect(validacion).toMatchObject({
            aplicar: true,
            cerrar: false,
            detenerInertia: false,
            publicar: false,
            tipo: 'validacion',
            status: 422,
        });
        expect(prohibido).toMatchObject({ detenerInertia: true, publicar: true, status: 403, cerrar: false });
        expect(servidor).toMatchObject({ detenerInertia: true, publicar: true, status: 500, cerrar: false });
        expect(red).toMatchObject({ detenerInertia: false, publicar: true, tipo: 'red', cerrar: false });
    });
});

describe('drawer y resumen de cambios', () => {
    it('usa drawer lateral solo cuando caben directorio y panel', () => {
        expect(modoDrawerColaborador(1039)).toBe('superpuesto');
        expect(modoDrawerColaborador(1040)).toBe('lateral');
        expect(anchoDrawerColaborador(1040)).toBe(760);
        expect(anchoDrawerColaborador(1128)).toBe(848);
        expect(anchoDrawerColaborador(1400)).toBe(880);
        expect(anchoDrawerColaborador(900)).toBeNull();
        expect(modoDrawerColaborador(Number.NaN)).toBe('superpuesto');
    });

    it('no cuenta un valor que vuelve al inicial ni muestra la contraseña', () => {
        const base = formularioVacio();
        base.name = 'Ana';
        base.permisos_individuales = ['pedidos.ver'];
        const igual = compararFormularioUsuario(base, { ...base, name: 'Ana', password: '' });
        expect(igual.total).toBe(0);

        const conClave = compararFormularioUsuario(base, { ...base, password: 'secreto-no-visible' });
        expect(JSON.stringify(conClave)).not.toContain('secreto-no-visible');
        expect(conClave.grupos[0].cambios[0].despues).toBe('Se reemplaza al guardar');
    });

    it('resume 40 permisos añadidos y 40 retirados sin tratarlos como un solo cambio', () => {
        const base = formularioVacio();
        base.permisos_individuales = Array.from({ length: 40 }, (_, indice) => `pedidos.retirado_${indice}`);
        base.sucursales = [1];
        base.sucursal_principal_id = '1';
        const actual = {
            ...base,
            permisos_individuales: Array.from({ length: 40 }, (_, indice) => `pedidos.anadido_${indice}`),
            sucursales: [1, 2, 3],
            sucursal_principal_id: '3',
            departamentos: [4, 5],
            areas: [8, 9],
            gerentes: [6, 7],
        };
        const resumen = compararFormularioUsuario(base, actual, {
            sucursales: [
                { id: 1, nombre: 'Centro' },
                { id: 2, nombre: 'Norte' },
                { id: 3, nombre: 'Bodega' },
            ],
            departamentos: [{ id: 4, nombre: 'Operación' }, { id: 5, nombre: 'Administración' }],
            areas: [{ id: 8, nombre: 'Mostrador' }, { id: 9, nombre: 'CEDIS' }],
            gerentes: [{ id: 6, name: 'Dana', apellido_paterno: 'Cruz' }],
        });

        expect(resumen.contadores.permisosAlta).toBe(40);
        expect(resumen.contadores.permisosBaja).toBe(40);
        expect(resumen.grupos.find((grupo) => grupo.id === 'permisos').cambios).toHaveLength(80);
        expect(resumen.contadores.organizacion).toBeGreaterThanOrEqual(3);
        expect(resumen.total).toBe(resumen.grupos.reduce((suma, grupo) => suma + grupo.cambios.length, 0));
    });

    it('no cuenta un permiso que se añade y se retira', () => {
        const base = formularioVacio();
        base.permisos_individuales = ['pedidos.ver'];
        const resumen = compararFormularioUsuario(base, {
            ...base,
            permisos_individuales: ['pedidos.ver'],
        });
        expect(resumen.total).toBe(0);
    });
});
