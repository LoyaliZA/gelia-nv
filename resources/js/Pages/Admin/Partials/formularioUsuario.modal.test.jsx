// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it } from 'vitest';
import ModalUsuarioForm from './ModalUsuarioForm';
import { formularioVacio } from './formularioUsuario';

let root;
let container;

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});

afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

function Harness({ inicial, errors = {}, incidencia = null }) {
    const [data, setData] = React.useState(inicial);
    const actualizar = (campo, valor) => {
        if (typeof campo === 'function') {
            setData(campo);
            return;
        }
        if (campo && typeof campo === 'object') {
            setData(campo);
            return;
        }
        setData((prev) => ({ ...prev, [campo]: valor }));
    };

    return (
        <ModalUsuarioForm
            usuarioEditando={null}
            onCerrar={() => {}}
            onSubmit={() => {}}
            processing={false}
            data={data}
            setData={actualizar}
            errors={errors}
            incidencia={incidencia}
            departamentos={[{ id: 1, nombre: 'Operacion', areas: [{ id: 10, nombre: 'Mostrador' }] }]}
            sucursales={[]}
            posiblesGerentes={[]}
            rolesJerarquia={[]}
            rolesGrupos={[]}
            plantillaSeleccionada=""
            aplicarPlantilla={() => {}}
            permisosFormData={data}
            setPermisosIndividualesViaForm={() => {}}
            roles={[]}
            todosLosPermisos={[]}
            catalogoPermisos={[]}
            permisosUsuario={[]}
            esSuperAdmin
            procedenciaActual={{}}
            setPlantillaPorPermiso={() => {}}
            sexos={[]}
            toggleSelection={() => {}}
            areasSeleccionadas={[]}
            departamentosSeleccionados={[]}
            sucursalesSeleccionadas={[]}
            resolverAreaPrincipalFormulario={() => ''}
            resolverPrincipalFormulario={() => ''}
        />
    );
}

it('conserva los datos al cambiar de pestaña y muestra el error sin borrarlos', async () => {
    await act(() => root.render(
        <Harness inicial={{ ...formularioVacio(), name: 'Ana', email: 'ana@example.com' }} />,
    ));

    const nombre = container.querySelector('input');
    expect(nombre.value).toBe('Ana');

    const organizacion = [...container.querySelectorAll('button')].find((boton) => boton.textContent.includes('Organización'));
    await act(() => organizacion.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    expect(container.textContent).toContain('Departamentos Asignados');
    expect(container.textContent).not.toContain('Identidad Personal');

    const datos = [...container.querySelectorAll('button')].find((boton) => boton.textContent.includes('Datos Generales'));
    await act(() => datos.dispatchEvent(new MouseEvent('click', { bubbles: true })));
    expect(container.querySelector('input').value).toBe('Ana');

    await act(() => root.render(
        <Harness
            inicial={{ ...formularioVacio(), name: 'Ana', email: 'ana@example.com' }}
            errors={{ email: 'El correo ya está en uso.' }}
            incidencia={{ status: 422, tipo: 'validacion', mensaje: '' }}
        />,
    ));

    expect(container.querySelector('[role="alert"]').textContent).toContain('Error 422');
    expect(container.querySelector('[role="alert"]').textContent).toContain('El correo ya está en uso.');
    expect(container.querySelector('input').value).toBe('Ana');
});
