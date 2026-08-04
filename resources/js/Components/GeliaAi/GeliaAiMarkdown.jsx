import React, { useEffect, useMemo, useState } from 'react';

/**
 * Markdown mínimo y seguro: **bold**, *italic*, `code`, saltos de línea.
 * Sin HTML crudo ni dependencias.
 */
function tokenizeInline(text) {
    const nodes = [];
    const re = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/g;
    let last = 0;
    let m;
    let key = 0;

    while ((m = re.exec(text)) !== null) {
        if (m.index > last) {
            nodes.push(text.slice(last, m.index));
        }
        const token = m[0];
        if (token.startsWith('**') && token.endsWith('**')) {
            nodes.push(<strong key={`b${key++}`}>{token.slice(2, -2)}</strong>);
        } else if (token.startsWith('*') && token.endsWith('*')) {
            nodes.push(<em key={`i${key++}`}>{token.slice(1, -1)}</em>);
        } else if (token.startsWith('`') && token.endsWith('`')) {
            nodes.push(<code key={`c${key++}`} className="gelia-ai-md-code">{token.slice(1, -1)}</code>);
        } else {
            nodes.push(token);
        }
        last = m.index + token.length;
    }

    if (last < text.length) {
        nodes.push(text.slice(last));
    }

    return nodes.length ? nodes : [text];
}

export default function GeliaAiMarkdown({ text = '', className = '', reveal = false }) {
    const blocks = useMemo(() => String(text).split('\n'), [text]);
    const [visibleCount, setVisibleCount] = useState(reveal ? 0 : blocks.length);

    useEffect(() => {
        if (!reveal) {
            setVisibleCount(blocks.length);
            return undefined;
        }

        setVisibleCount(0);
        let i = 0;
        const id = window.setInterval(() => {
            i += 1;
            setVisibleCount(i);
            if (i >= blocks.length) {
                window.clearInterval(id);
            }
        }, 38);

        return () => window.clearInterval(id);
    }, [reveal, text, blocks.length]);

    return (
        <div className={`gelia-ai-md ${reveal ? 'gelia-ai-md--reveal' : ''} ${className}`.trim()}>
            {blocks.slice(0, visibleCount).map((line, i) => (
                <p
                    key={`${i}-${line.slice(0, 12)}`}
                    className="gelia-ai-md-p"
                    style={reveal ? { animationDelay: `${Math.min(i * 28, 280)}ms` } : undefined}
                >
                    {line === '' ? '\u00A0' : tokenizeInline(line)}
                </p>
            ))}
        </div>
    );
}
