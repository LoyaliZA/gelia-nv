import React from 'react';
import { INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS } from './activosFormStyles';
import CatalogCombobox from './CatalogCombobox';
import InputConEscanner from '@/Components/Escanner/InputConEscanner';

function esCampoEscaneable(field, readOnly) {
    if (readOnly) return false;
    if (field.scannable === false) return false;
    if (field.scannable === true) return true;
    return ['text', 'textarea', 'number'].includes(field.type);
}

function FieldLabel({ field }) {
    return (
        <label className={LABEL_CLASS}>
            {field.label}{field.required ? ' *' : ''}
        </label>
    );
}

function inputPropsFromField(field) {
    const props = {};
    if (field.type === 'number') {
        if (field.min !== undefined) props.min = field.min;
        if (field.max !== undefined) props.max = field.max;
    }
    if (['text', 'textarea'].includes(field.type)) {
        if (field.min_length !== undefined) props.minLength = field.min_length;
        if (field.max_length !== undefined) props.maxLength = field.max_length;
        if (field.pattern) props.pattern = field.pattern;
    }
    return props;
}

export default function DynamicActivoFields({ fields = [], values = {}, onChange, errors = {}, readOnly = false, tipoActivoId = null }) {
    if (!fields.length) {
        return (
            <p className="text-xs theme-text-muted italic px-1">
                Este tipo no define campos adicionales.
            </p>
        );
    }

    const handleChange = (key, value) => {
        onChange({ ...values, [key]: value });
    };

    const marcaId = values.marca_id || null;

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {fields.map((field) => {
                const key = field.key;
                const error = errors[`atributos.${key}`];
                const extraProps = inputPropsFromField(field);

                if (field.type === 'catalog_marca' || field.type === 'catalog_modelo') {
                    return (
                        <div key={key}>
                            <FieldLabel field={field} />
                            <CatalogCombobox
                                field={field}
                                value={values[key] ?? ''}
                                onChange={(v) => {
                                    if (field.type === 'catalog_marca') {
                                        const cambio = v !== (values.marca ?? '');
                                        onChange({
                                            ...values,
                                            marca: v,
                                            marca_id: null,
                                            ...(cambio ? { modelo: '', modelo_id: null } : {}),
                                        });
                                        return;
                                    }
                                    onChange({
                                        ...values,
                                        [key]: v,
                                        modelo_id: null,
                                    });
                                }}
                                onItemSelect={(item) => {
                                    if (field.type === 'catalog_marca') {
                                        onChange({
                                            ...values,
                                            marca: item?.nombre ?? values.marca,
                                            marca_id: item?.id ?? null,
                                            ...(item ? { modelo: '', modelo_id: null } : {}),
                                        });
                                        return;
                                    }
                                    onChange({
                                        ...values,
                                        modelo: item?.nombre ?? values.modelo,
                                        modelo_id: item?.id ?? null,
                                    });
                                }}
                                tipoActivoId={tipoActivoId}
                                marcaId={marcaId}
                                marcaNombre={values.marca}
                                readOnly={readOnly}
                            />
                            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                        </div>
                    );
                }

                if (field.type === 'select') {
                    return (
                        <div key={key}>
                            <FieldLabel field={field} />
                            {readOnly ? (
                                <p className="text-sm theme-text-main py-2">{values[key] || '—'}</p>
                            ) : (
                                <select
                                    value={values[key] ?? ''}
                                    onChange={(e) => handleChange(key, e.target.value)}
                                    className={SELECT_CLASS}
                                >
                                    <option value="">Seleccionar...</option>
                                    {(field.options || []).map((opt) => (
                                        <option key={opt} value={opt}>{opt}</option>
                                    ))}
                                </select>
                            )}
                            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                        </div>
                    );
                }

                if (field.type === 'boolean') {
                    return (
                        <div key={key} className="flex items-center gap-2 pt-6">
                            <input
                                type="checkbox"
                                checked={!!values[key]}
                                onChange={(e) => handleChange(key, e.target.checked)}
                                disabled={readOnly}
                                className="rounded w-5 h-5"
                            />
                            <label className="text-sm theme-text-main">{field.label}</label>
                        </div>
                    );
                }

                if (field.type === 'textarea') {
                    return (
                        <div key={key} className="md:col-span-2">
                            <FieldLabel field={field} />
                            {esCampoEscaneable(field, readOnly) ? (
                                <InputConEscanner
                                    multiline
                                    value={values[key] ?? ''}
                                    onChange={(e) => handleChange(key, e.target.value)}
                                    label={field.label}
                                    className={TEXTAREA_CLASS}
                                    inputProps={{ rows: 3, ...extraProps }}
                                />
                            ) : (
                                <textarea
                                    value={values[key] ?? ''}
                                    onChange={(e) => handleChange(key, e.target.value)}
                                    readOnly={readOnly}
                                    rows={3}
                                    className={TEXTAREA_CLASS}
                                    {...extraProps}
                                />
                            )}
                            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                        </div>
                    );
                }

                const inputType = field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text';

                return (
                    <div key={key}>
                        <FieldLabel field={field} />
                        {esCampoEscaneable(field, readOnly) ? (
                            <InputConEscanner
                                value={values[key] ?? ''}
                                onChange={(e) => handleChange(key, e.target.value)}
                                label={field.label}
                                readOnly={readOnly}
                                className={`${INPUT_CLASS} ${readOnly ? 'opacity-80 cursor-default' : ''}`}
                                inputProps={{ type: inputType, ...extraProps }}
                            />
                        ) : (
                            <input
                                type={inputType}
                                value={values[key] ?? ''}
                                onChange={(e) => handleChange(key, e.target.value)}
                                readOnly={readOnly}
                                className={`${INPUT_CLASS} ${readOnly ? 'opacity-80 cursor-default' : ''}`}
                                {...extraProps}
                            />
                        )}
                        {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                    </div>
                );
            })}
        </div>
    );
}
