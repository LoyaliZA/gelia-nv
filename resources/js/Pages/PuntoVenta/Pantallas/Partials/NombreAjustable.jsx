import React, { useLayoutEffect, useRef } from 'react';

export default function NombreAjustable({
    children,
    className = '',
    minPx = 14,
    maxPx = 28,
    maxLines = 3,
}) {
    const ref = useRef(null);

    useLayoutEffect(() => {
        const el = ref.current;
        if (!el) return;

        el.style.fontSize = `${maxPx}px`;
        let size = maxPx;
        while (size > minPx && el.scrollHeight > el.clientHeight + 1) {
            size -= 1;
            el.style.fontSize = `${size}px`;
        }
    }, [children, minPx, maxPx]);

    return (
        <p
            ref={ref}
            className={className}
            style={{
                whiteSpace: 'normal',
                overflowWrap: 'break-word',
                lineHeight: 1.05,
                maxHeight: `${maxLines * 1.05}em`,
                overflow: 'hidden',
            }}
        >
            {children}
        </p>
    );
}
