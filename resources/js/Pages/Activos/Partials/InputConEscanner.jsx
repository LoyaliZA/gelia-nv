import React, { useState } from 'react';
import { ScanLine } from 'lucide-react';
import ModalEscanearCodigo from './ModalEscanearCodigo';
import { BTN_ICON_CLASS } from './activosFormStyles';

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

    return (
        <>
            <div className="flex gap-2 items-stretch">
                <InputTag
                    value={value ?? ''}
                    onChange={onChange}
                    className={`${className} flex-1 min-w-0`}
                    {...inputProps}
                />
                <button
                    type="button"
                    onClick={() => setModalAbierto(true)}
                    className={`${BTN_ICON_CLASS} shrink-0 self-stretch min-w-[44px]`}
                    title={`Escanear ${label || 'código'}`}
                    aria-label={`Escanear ${label || 'código'}`}
                >
                    <ScanLine className="w-4 h-4" />
                </button>
            </div>

            {modalAbierto && (
                <ModalEscanearCodigo
                    abierto={modalAbierto}
                    onCerrar={() => setModalAbierto(false)}
                    titulo={label ? `Escanear ${label}` : 'Escanear código'}
                    descripcion={`Apunta la cámara al código de barras o QR del campo ${label || 'seleccionado'}.`}
                    onEscaneado={(codigo) => {
                        onChange({ target: { value: codigo } });
                        setModalAbierto(false);
                    }}
                />
            )}
        </>
    );
}
