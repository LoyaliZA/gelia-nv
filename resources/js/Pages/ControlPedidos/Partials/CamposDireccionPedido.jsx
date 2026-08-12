import React from 'react';
import { THEME_INPUT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { THEME_LABEL, BTN_PRIMARY, BTN_SECONDARY } from './pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-2 block`;

export const CAMPOS_DIRECCION_VACIOS = {
    nombre_destinatario: '',
    telefono_destinatario: '',
    calle: '',
    numero_exterior: '',
    numero_interior: '',
    colonia: '',
    codigo_postal: '',
    municipio: '',
    ciudad: '',
    estado: '',
    pais: 'México',
    referencias: '',
    indicaciones_entrega: '',
    domicilio_irregular: false,
};

export function camposDesdeDireccion(dir) {
    if (!dir) return { ...CAMPOS_DIRECCION_VACIOS };
    return {
        nombre_destinatario: dir.nombre_destinatario || '',
        telefono_destinatario: dir.telefono_destinatario || '',
        calle: dir.calle || '',
        numero_exterior: dir.numero_exterior || '',
        numero_interior: dir.numero_interior || '',
        colonia: dir.colonia || '',
        codigo_postal: dir.codigo_postal || '',
        municipio: dir.municipio || '',
        ciudad: dir.ciudad || '',
        estado: dir.estado || '',
        pais: dir.pais || 'México',
        referencias: dir.referencias || '',
        indicaciones_entrega: dir.indicaciones_entrega || '',
        domicilio_irregular: Boolean(dir.domicilio_irregular),
    };
}

export function resumirCamposDireccion(campos) {
    if (campos.domicilio_irregular && campos.referencias) {
        const partes = [
            campos.calle || null,
            campos.referencias,
            campos.municipio || campos.ciudad,
            campos.estado,
            campos.codigo_postal,
        ].filter(Boolean);
        return partes.join(', ');
    }
    const partes = [
        campos.calle,
        campos.numero_exterior,
        campos.numero_interior ? `Int. ${campos.numero_interior}` : null,
        campos.colonia,
        campos.codigo_postal,
        campos.municipio || campos.ciudad,
        campos.estado,
    ].filter(Boolean);
    return partes.join(', ');
}

/**
 * Campos independientes de dirección (editables) para el formulario de pedido.
 */
export default function CamposDireccionPedido({
    valores,
    onChange,
    disabled = false,
    sucio = false,
    guardando = false,
    onGuardar = null,
    onDescartar = null,
    puedeEditar = true,
}) {
    const set = (campo, valor) => onChange({ ...valores, [campo]: valor });
    const irregular = Boolean(valores.domicilio_irregular);

    return (
        <div className="space-y-4">
            <label className="flex items-start gap-2 text-sm font-bold theme-text-main">
                <input
                    type="checkbox"
                    className="mt-1"
                    checked={irregular}
                    disabled={disabled || !puedeEditar}
                    onChange={(e) => set('domicilio_irregular', e.target.checked)}
                />
                <span>
                    Domicilio irregular / sin calle formal
                    <span className="block text-[10px] font-bold theme-text-muted mt-0.5">
                        Relaja calle, colonia y CP; exige referencias y municipio o ciudad.
                    </span>
                </span>
            </label>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className={SECCION}>Destinatario</label>
                    <input type="text" value={valores.nombre_destinatario} disabled={disabled || !puedeEditar} onChange={(e) => set('nombre_destinatario', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>Teléfono</label>
                    <input type="text" value={valores.telefono_destinatario} disabled={disabled || !puedeEditar} onChange={(e) => set('telefono_destinatario', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div className="md:col-span-2">
                    <label className={SECCION}>{irregular ? 'Calle (opcional)' : 'Calle'}</label>
                    <input type="text" value={valores.calle} disabled={disabled || !puedeEditar} onChange={(e) => set('calle', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>Número exterior</label>
                    <input type="text" value={valores.numero_exterior} disabled={disabled || !puedeEditar} onChange={(e) => set('numero_exterior', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>Número interior</label>
                    <input type="text" value={valores.numero_interior} disabled={disabled || !puedeEditar} onChange={(e) => set('numero_interior', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>{irregular ? 'Colonia (opcional)' : 'Colonia'}</label>
                    <input type="text" value={valores.colonia} disabled={disabled || !puedeEditar} onChange={(e) => set('colonia', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>{irregular ? 'C.P. (opcional)' : 'C.P.'}</label>
                    <input type="text" value={valores.codigo_postal} disabled={disabled || !puedeEditar} onChange={(e) => set('codigo_postal', e.target.value)} className={`${THEME_INPUT} w-full py-3`} maxLength={5} />
                </div>
                <div>
                    <label className={SECCION}>{irregular ? 'Municipio (o ciudad)' : 'Municipio'}</label>
                    <input type="text" value={valores.municipio} disabled={disabled || !puedeEditar} onChange={(e) => set('municipio', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>{irregular ? 'Ciudad (o municipio)' : 'Ciudad'}</label>
                    <input type="text" value={valores.ciudad} disabled={disabled || !puedeEditar} onChange={(e) => set('ciudad', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>Estado</label>
                    <input type="text" value={valores.estado} disabled={disabled || !puedeEditar} onChange={(e) => set('estado', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION}>País</label>
                    <input type="text" value={valores.pais} disabled={disabled || !puedeEditar} onChange={(e) => set('pais', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div className="md:col-span-2">
                    <label className={SECCION}>{irregular ? 'Referencias *' : 'Referencias'}</label>
                    <textarea value={valores.referencias} disabled={disabled || !puedeEditar} onChange={(e) => set('referencias', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[64px]`} />
                </div>
                <div className="md:col-span-2">
                    <label className={SECCION}>Indicaciones de entrega</label>
                    <textarea value={valores.indicaciones_entrega} disabled={disabled || !puedeEditar} onChange={(e) => set('indicaciones_entrega', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[64px]`} />
                </div>
            </div>
            {puedeEditar && sucio && (
                <div className="flex flex-wrap gap-3">
                    <button type="button" onClick={onGuardar} disabled={guardando || disabled} className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}>
                        {guardando ? 'Guardando…' : 'Actualizar dirección'}
                    </button>
                    <button type="button" onClick={onDescartar} disabled={guardando || disabled} className={`${BTN_SECONDARY} outline-none`}>
                        Descartar cambios
                    </button>
                </div>
            )}
        </div>
    );
}
