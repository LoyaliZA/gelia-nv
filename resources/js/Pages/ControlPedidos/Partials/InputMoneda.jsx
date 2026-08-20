import React, { useState, useEffect } from 'react';
import { THEME_INPUT } from '../../../utils/geliaTheme';

function formatearMonedaInput(valor) {
    const n = Number(valor);
    if (Number.isNaN(n)) return '';
    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

function parseMonedaInput(texto) {
    let s = String(texto ?? '').replace(/[^\d.,]/g, '');
    if (s === '' || s === '.' || s === ',') return '';

    const lastComma = s.lastIndexOf(',');
    const lastDot = s.lastIndexOf('.');

    if (lastComma > lastDot) {
        // 1.234,56 o 1234,56 → coma decimal
        s = s.replace(/\./g, '').replace(',', '.');
    } else if (lastDot > lastComma) {
        // 1,234.56 o $4,850.00 → punto decimal, coma miles
        s = s.replace(/,/g, '');
    } else if (lastComma !== -1) {
        // solo comas: 1,23 → decimal; 1,234 → miles
        const parts = s.split(',');
        if (parts.length === 2 && parts[1].length <= 2) {
            s = `${parts[0]}.${parts[1]}`;
        } else {
            s = s.replace(/,/g, '');
        }
    } else if ((s.match(/\./g) || []).length > 1) {
        // 1.234.567 → solo miles
        s = s.replace(/\./g, '');
    }

    const n = parseFloat(s);
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
