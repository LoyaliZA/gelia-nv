import React from 'react';
import { INPUT_CLASS, SELECT_CLASS, TEXTAREA_CLASS, LABEL_CLASS } from './activosFormStyles';
import CatalogCombobox, { renderFieldLabel } from './CatalogCombobox';

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

                if (field.type === 'catalog_marca' || field.type === 'catalog_modelo') {
                    return (
                        <div key={key}>
                            {renderFieldLabel(field)}
                            <CatalogCombobox
                                field={field}
                                value={values[key] ?? ''}
                                onChange={(v) => handleChange(key, v)}
                                onItemSelect={(item) => {
                                    if (field.type === 'catalog_marca') {
                                        onChange({
                                            ...values,
                                            marca: item?.nombre ?? values.marca,
                                            marca_id: item?.id ?? null,
                                            ...(item ? { modelo: '' } : {}),
                                        });
                                    }
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
                            {renderFieldLabel(field)}
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
                                className="rounded"
                            />
                            <label className="text-sm theme-text-main">{field.label}</label>
                        </div>
                    );
                }

                if (field.type === 'textarea') {
                    return (
                        <div key={key} className="md:col-span-2">
                            {renderFieldLabel(field)}
                            <textarea
                                value={values[key] ?? ''}
                                onChange={(e) => handleChange(key, e.target.value)}
                                readOnly={readOnly}
                                rows={3}
                                className={TEXTAREA_CLASS}
                            />
                            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                        </div>
                    );
                }

                return (
                    <div key={key}>
                        {renderFieldLabel(field)}
                        <input
                            type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'}
                            value={values[key] ?? ''}
                            onChange={(e) => handleChange(key, e.target.value)}
                            readOnly={readOnly}
                            className={`${INPUT_CLASS} ${readOnly ? 'opacity-80 cursor-default' : ''}`}
                        />
                        {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
                    </div>
                );
            })}
        </div>
    );
}
