import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';

const TIPOS_EVIDENCIA = [
    { key: 'evidencia_bascula', label: 'Báscula / peso' },
    { key: 'evidencia_bulto', label: 'Bulto' },
    { key: 'evidencia_caratula_colocada', label: 'Carátula colocada' },
    { key: 'evidencia_entrega', label: 'Entrega al transporte' },
];

export default function EmpaqueMunicipioTienda({ tarea, apartado, auth, requisitos = {}, documentos = [] }) {
    const permisos = auth?.user?.permissions || [];
    const puedeEmpacar = permisos.includes('control_pedidos.tienda.empacar_municipio');
    const puedeDespachar = permisos.includes('control_pedidos.tienda.despachar_municipio');
    const [bultos, setBultos] = useState(String(apartado?.bultos_salida || 1));
    const [receptor, setReceptor] = useState('');

    if (!apartado) return null;

    const subir = (tipo, file) => {
        if (!file) return;
        const form = new FormData();
        form.append('tipo', tipo);
        form.append('archivo', file);
        router.post(route('control_pedidos.tienda.municipio.evidencia_empaque', tarea.id), form, { forceFormData: true });
    };

    const empacar = () => {
        router.post(route('control_pedidos.tienda.municipio.empacar', tarea.id), {
            bultos,
            version: apartado.version,
        });
    };

    const despachar = () => {
        router.post(route('control_pedidos.tienda.municipio.despachar', tarea.id), {
            receptor,
            version: apartado.version,
        });
    };

    const tiene = (tipo) => documentos.some((d) => d.tipo_evidencia === tipo);
    const hojaInterna = documentos.some((d) => d.tipo_evidencia === 'hoja_salida_interna');
    const remision = documentos.some((d) => d.tipo_evidencia === 'remision');

    if (!['LISTA_PARA_SALIDA', 'EMPACADA', 'DESPACHADA'].includes(apartado.estado)) {
        return null;
    }

    return (
        <section className="rounded-xl border border-teal-500/40 p-4 space-y-3 bg-teal-500/5">
            <h3 className="text-[10px] font-black uppercase tracking-widest text-teal-800 dark:text-teal-200 m-0">Empaque y salida municipal</h3>
            {requisitos.causa_remision_salida && (
                <p className="text-xs font-bold m-0 theme-text-muted">{requisitos.causa_remision_salida}</p>
            )}
            <p className="text-xs m-0 theme-text-muted">
                Documental: {remision ? 'Remisión adjunta' : hojaInterna ? 'Hoja interna de salida (no es remisión)' : 'Pendiente al autorizar o adjuntar remisión'}
            </p>
            <ul className="text-xs m-0 space-y-1">
                {TIPOS_EVIDENCIA.map(({ key, label }) => (
                    <li key={key} className={tiene(key) ? 'text-emerald-700 dark:text-emerald-300' : 'theme-text-muted'}>
                        {tiene(key) ? '✓' : '○'} {label}
                    </li>
                ))}
            </ul>
            {apartado.estado === 'LISTA_PARA_SALIDA' && puedeEmpacar && (
                <>
                    <div className="flex flex-wrap gap-2">
                        {TIPOS_EVIDENCIA.map(({ key, label }) => (
                            <label key={key} className={`${BTN_SECONDARY} cursor-pointer text-xs`}>
                                {label}
                                <input type="file" accept="image/*" className="hidden" onChange={(e) => subir(key, e.target.files?.[0])} />
                            </label>
                        ))}
                    </div>
                    <div className="grid sm:grid-cols-3 gap-3 items-end">
                        <div>
                            <label className={THEME_LABEL}>Bultos</label>
                            <input className={`${THEME_INPUT} w-full mt-1`} type="number" min="1" value={bultos} onChange={(e) => setBultos(e.target.value)} />
                        </div>
                        <button type="button" className={BTN_PRIMARY} onClick={empacar}>Confirmar empaque</button>
                    </div>
                </>
            )}
            {apartado.estado === 'EMPACADA' && puedeDespachar && (
                <div className="grid sm:grid-cols-2 gap-3 items-end">
                    <div>
                        <label className={THEME_LABEL}>Transportista / quien recibe</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} value={receptor} onChange={(e) => setReceptor(e.target.value)} />
                    </div>
                    <button type="button" className={BTN_PRIMARY} onClick={despachar}>Registrar despacho</button>
                </div>
            )}
            {apartado.estado === 'DESPACHADA' && (
                <p className="text-sm font-bold m-0">
                    Despachado {apartado.despachada_at ? formatearFechaNegocio(apartado.despachada_at) : ''}
                    {apartado.receptor_nombre ? ` · ${apartado.receptor_nombre}` : ''}
                    {apartado.bultos_salida ? ` · ${apartado.bultos_salida} bulto(s)` : ''}
                </p>
            )}
        </section>
    );
}
