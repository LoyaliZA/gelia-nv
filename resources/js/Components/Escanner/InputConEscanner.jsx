import React, { useState } from 'react';
import { ScanLine } from 'lucide-react';
import ModalEscanearCodigo from './ModalEscanearCodigo';
import { THEME_BTN_ICON } from '@/utils/geliaTheme';

export default function InputConEscanner({
    value,
    onChange,
    inputProps = {},
    className = '',
    label = '',
    readOnly = false,
    multiline = false,
}) {
    const [modalAbierto, setModalAbierto] = useState(false);

    if (readOnly) {
        return multiline ? (
            <textarea value={value ?? ''} readOnly className={className} {...inputProps} />
        ) : (
            <input value={value ?? ''} readOnly className={className} {...inputProps} />
        );
    }

    const InputTag = multiline ? 'textarea' : 'input';
    const { className: inputClassName = '', ...restInputProps } = inputProps;

    return (
        <>
            <div className="flex gap-2 items-stretch">
                <InputTag
                    value={value ?? ''}
                    onChange={onChange}
                    className={`${className} ${inputClassName} flex-1 min-w-0`}
                    {...restInputProps}
                />
                <button
                    type="button"
                    onClick={() => setModalAbierto(true)}
                    className={`${THEME_BTN_ICON} shrink-0 self-stretch min-w-[44px]`}
                    title={`Escanear ${label || 'código'}`}
                    aria-label={`Escanear ${label || 'código'}`}
                >
                    <ScanLine className="w-4 h-4" />
                </button>
            </div>

            <ModalEscanearCodigo
                abierto={modalAbierto}
                onCerrar={() => setModalAbierto(false)}
                titulo={label ? `Escanear ${label}` : 'Escanear código'}
                descripcion={`Apunta la cámara al código de barras o QR${label ? ` del campo ${label}` : ''}.`}
                onEscaneado={(codigo) => {
                    onChange({ target: { value: codigo } });
                    setModalAbierto(false);
                }}
            />
        </>
    );
}
