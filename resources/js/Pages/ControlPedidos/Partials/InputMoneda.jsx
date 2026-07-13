import React, { useState, useEffect } from 'react';
import { THEME_INPUT } from '../../../utils/geliaTheme';

function formatearMonedaInput(valor) {
    const n = Number(valor);
    if (Number.isNaN(n)) return '';
    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

function parseMonedaInput(texto) {
    const limpio = String(texto ?? '').replace(/[^\d.,]/g, '').replace(',', '.');
    if (limpio === '' || limpio === '.') return '';
    const n = parseFloat(limpio);
    return Number.isNaN(n) ? '' : Math.round(n * 100) / 100;
}

export default function InputMoneda({
    value,
    onChange,
    className = '',
    readOnly = false,
    placeholder = '0.00',
}) {
    const [focused, setFocused] = useState(false);
    const [texto, setTexto] = useState('');

    useEffect(() => {
        if (!focused) {
            setTexto(value === '' || value == null ? '' : formatearMonedaInput(value));
        }
    }, [value, focused]);

    const handleFocus = () => {
        setFocused(true);
        setTexto(value === '' || value == null ? '' : String(value));
    };

    const handleBlur = () => {
        setFocused(false);
        const parsed = parseMonedaInput(texto);
        onChange(parsed === '' ? '' : parsed);
        setTexto(parsed === '' ? '' : formatearMonedaInput(parsed));
    };

    const handleChange = (e) => {
        const raw = e.target.value;
        setTexto(raw);
        if (!focused) return;
        const parsed = parseMonedaInput(raw);
        onChange(parsed);
    };

    return (
        <input
            type="text"
            inputMode="decimal"
            readOnly={readOnly}
            placeholder={placeholder}
            value={focused ? texto : (value === '' || value == null ? '' : formatearMonedaInput(value))}
            onFocus={handleFocus}
            onBlur={handleBlur}
            onChange={handleChange}
            className={`${THEME_INPUT} ${className}`}
        />
    );
}

export { formatearMonedaInput, parseMonedaInput };
