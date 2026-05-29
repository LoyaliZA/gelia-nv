import React, { useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import DynamicActivoFields from './DynamicActivoFields';
import GaleriaFotosActivo from './GaleriaFotosActivo';
import ActivosModalShell from './ActivosModalShell';
import {
    INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS,
    BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS,
} from './activosFormStyles';

export default function ModalFormActivo({ abierto, onCerrar, tipos = [], departamentos = [], activo = null }) {
    const esEdicion = !!activo;

    const { data, setData, post, put, processing, errors, reset, clearErrors, transform } = useForm({
        catalogo_tipo_activo_id: '',
        departamento_id: '',
        area_id: '',
        nombre: '',
        descripcion: '',
        atributos: {},
        fecha_adquisicion: '',
        fecha_vencimiento: '',
        valor: '',
        fotos: [],
    });

    const tipoSeleccionado = useMemo(
        () => tipos.find((t) => String(t.id) === String(data.catalogo_tipo_activo_id)),
        [tipos, data.catalogo_tipo_activo_id]
    );

    useEffect(() => {
        if (!abierto) return;

        if (activo) {
            setData({
                catalogo_tipo_activo_id: activo.catalogo_tipo_activo_id || '',
                departamento_id: activo.departamento_id || '',
                area_id: activo.area_id || '',
                nombre: activo.nombre || '',
                descripcion: activo.descripcion || '',
                atributos: activo.atributos || {},
                fecha_adquisicion: activo.fecha_adquisicion?.substring?.(0, 10) || activo.fecha_adquisicion || '',
                fecha_vencimiento: activo.fecha_vencimiento?.substring?.(0, 10) || activo.fecha_vencimiento || '',
                valor: activo.valor || '',
                fotos: [],
            });
        } else {
            reset();
        }
        clearErrors();
    }, [abierto, activo]);

    if (!abierto) return null;

    const tieneFotos = (data.fotos || []).length > 0;

    const submit = (e) => {
        e.preventDefault();
        const accion = esEdicion ? put : post;
        const ruta = esEdicion ? route('activos.update', activo.id) : route('activos.store');

        transform((formData) => ({
            ...formData,
            atributos: typeof formData.atributos === 'object' ? JSON.stringify(formData.atributos) : formData.atributos,
        }));

        accion(ruta, {
            onSuccess: () => { onCerrar(); reset(); },
            forceFormData: tieneFotos,
        });
    };

    return (
        <ActivosModalShell
            title={esEdicion ? 'Editar activo' : 'Registrar activo'}
            onClose={onCerrar}
            size="max-w-3xl"
            loader={<GeliaLoader isVisible={processing} message="Guardando_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-5">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
                        <div>
                            <label className={LABEL_CLASS}>Tipo *</label>
                            <select value={data.catalogo_tipo_activo_id} onChange={(e) => setData('catalogo_tipo_activo_id', e.target.value)} disabled={esEdicion} className={SELECT_CLASS}>
                                <option value="">Seleccionar...</option>
                                {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                            </select>
                            {errors.catalogo_tipo_activo_id && <p className="text-red-500 text-xs mt-1">{errors.catalogo_tipo_activo_id}</p>}
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Departamento *</label>
                            <select value={data.departamento_id} onChange={(e) => setData('departamento_id', e.target.value)} className={SELECT_CLASS}>
                                <option value="">Seleccionar...</option>
                                {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                            </select>
                            {errors.departamento_id && <p className="text-red-500 text-xs mt-1">{errors.departamento_id}</p>}
                        </div>
                        <div className="md:col-span-2">
                            <label className={LABEL_CLASS}>Nombre *</label>
                            <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={INPUT_CLASS} />
                            {errors.nombre && <p className="text-red-500 text-xs mt-1">{errors.nombre}</p>}
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Fecha adquisición</label>
                            <input type="date" value={data.fecha_adquisicion} onChange={(e) => setData('fecha_adquisicion', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <div>
                            <label className={LABEL_CLASS}>Valor</label>
                            <input type="number" step="0.01" value={data.valor} onChange={(e) => setData('valor', e.target.value)} className={INPUT_CLASS} />
                        </div>
                        <div className="md:col-span-2">
                            <label className={LABEL_CLASS}>Descripción</label>
                            <textarea value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} rows={2} className={TEXTAREA_CLASS} />
                        </div>
                    </div>

                    <GaleriaFotosActivo
                        fotosExistentes={activo?.fotos || []}
                        nuevasFotos={data.fotos}
                        onChangeNuevas={(fotos) => setData('fotos', fotos)}
                        maxFotos={5}
                        activoId={activo?.id}
                    />

                    {tipoSeleccionado && (
                        <div className="border-t theme-border pt-5">
                            <p className={`${LABEL_CLASS} mb-3`}>Atributos — {tipoSeleccionado.nombre}</p>
                            <DynamicActivoFields
                                fields={tipoSeleccionado.esquema_atributos?.fields || []}
                                values={data.atributos}
                                onChange={(attrs) => setData('atributos', attrs)}
                                errors={errors}
                                tipoActivoId={data.catalogo_tipo_activo_id}
                            />
                        </div>
                    )}
                </div>

                <div className="gelia-modal-footer p-5 md:p-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <button type="button" onClick={onCerrar} className={`${BTN_SECONDARY_CLASS} w-full sm:w-auto`}>
                        Cancelar
                    </button>
                    <button type="submit" disabled={processing} className={`${BTN_PRIMARY_CLASS} w-full sm:w-auto`}>
                        <Save className="w-4 h-4 shrink-0" /> Guardar
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
