import React from 'react';

/**
 * Markdown mínimo y seguro: texto, listas, encabezados y tablas.
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
    const lines = String(text).split(/\r?\n/);
    const blocks = [];
    const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map((s) => s.trim());
    const isDivider = (line = '') => line.includes('|') && cells(line).every((s) => /^:?-{3,}:?$/.test(s));
    const listItem = (line) => line.match(/^\s*(?:([-*])|\d+[.)])\s+(.+)$/);

    for (let i = 0; i < lines.length;) {
        const line = lines[i];
        const key = i;
        if (!line.trim()) { i++; continue; }
        if (line.includes('|') && isDivider(lines[i + 1])) {
            const header = cells(line);
            const rows = [];
            i += 2;
            while (i < lines.length && lines[i].trim() && lines[i].includes('|')) rows.push(cells(lines[i++]));
            blocks.push(
                <div key={key} className="gelia-ai-md-table-wrap" tabIndex={0} role="region" aria-label="Tabla de resultados">
                    <table className="gelia-ai-md-table">
                        <thead><tr>{header.map((s, c) => <th key={c} scope="col">{tokenizeInline(s)}</th>)}</tr></thead>
                        <tbody>{rows.map((row, r) => <tr key={r}>{header.map((_, c) => <td key={c}>{tokenizeInline(row[c] || '')}</td>)}</tr>)}</tbody>
                    </table>
                </div>,
            );
            continue;
        }
        const item = listItem(line);
        if (item) {
            const ordered = !item[1];
            const start = ordered ? Number.parseInt(line.trim(), 10) : undefined;
            const items = [];
            while (i < lines.length) {
                const next = listItem(lines[i]);
                if (!next || (!next[1]) !== ordered) break;
                items.push(<li key={i}>{tokenizeInline(next[2])}</li>);
                i++;
            }
            const Tag = ordered ? 'ol' : 'ul';
            blocks.push(<Tag key={key} className="gelia-ai-md-list" start={start}>{items}</Tag>);
            continue;
        }
        const heading = line.match(/^#{1,6}\s+(.+)$/);
        blocks.push(heading
            ? <h3 key={key} className="gelia-ai-md-heading">{tokenizeInline(heading[1])}</h3>
            : <p key={key} className="gelia-ai-md-p">{tokenizeInline(line)}</p>);
        i++;
    }

    return (
        <div className={`gelia-ai-md ${reveal ? 'gelia-ai-md--reveal' : ''} ${className}`.trim()}>
            {blocks}
        </div>
    );
}
