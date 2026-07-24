import React, { useRef, useState } from 'react';
import { Code2, Eye } from 'lucide-react';

const labelClass = 'block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2';
const inputClass = 'w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main font-mono';

const TOOLS = [
    { id: 'p', label: 'P', title: 'Párrafo', wrap: ['<p>', '</p>'] },
    { id: 'h2', label: 'H2', title: 'Título', wrap: ['<h2>', '</h2>'] },
    { id: 'h3', label: 'H3', title: 'Subtítulo', wrap: ['<h3>', '</h3>'] },
    { id: 'strong', label: 'B', title: 'Negrita', wrap: ['<strong>', '</strong>'] },
    { id: 'em', label: 'I', title: 'Cursiva', wrap: ['<em>', '</em>'] },
    { id: 'br', label: 'BR', title: 'Salto de línea', snippet: '<br>\n' },
    { id: 'ul', label: 'UL', title: 'Lista con viñetas', list: 'ul' },
    { id: 'ol', label: 'OL', title: 'Lista numerada', list: 'ol' },
    { id: 'li', label: 'LI', title: 'Ítem de lista', wrap: ['<li>', '</li>'] },
    { id: 'a', label: 'A', title: 'Enlace', link: true },
    { id: 'blockquote', label: 'Quote', title: 'Cita', wrap: ['<blockquote>', '</blockquote>'] },
    { id: 'hr', label: 'HR', title: 'Separador', snippet: '\n<hr>\n' },
    {
        id: 'details',
        label: 'Details',
        title: 'Acordeón (details)',
        details: true,
    },
];

function sanitizePreviewHtml(html) {
    if (!html) return '';
    return html
        .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
        .replace(/\son\w+\s*=\s*(['"]).*?\1/gi, '')
        .replace(/\son\w+\s*=\s*[^\s>]+/gi, '');
}

function applyEdit(textarea, onChange, next, selStart, selEnd) {
    onChange(next);
    requestAnimationFrame(() => {
        if (!textarea) return;
        textarea.focus();
        textarea.setSelectionRange(selStart, selEnd ?? selStart);
    });
}

function wrapRange(textarea, value, onChange, range, openTag, closeTag, placeholder = 'texto') {
    const { start, end } = range;
    const selected = value.slice(start, end);
    const body = selected || placeholder;
    const next = value.slice(0, start) + openTag + body + closeTag + value.slice(end);
    const innerStart = start + openTag.length;
    const innerEnd = innerStart + body.length;
    applyEdit(textarea, onChange, next, selected ? innerEnd + closeTag.length : innerStart, selected ? innerEnd + closeTag.length : innerEnd);
}

function insertAtRange(textarea, value, onChange, range, html) {
    const { start, end } = range;
    const next = value.slice(0, start) + html + value.slice(end);
    const cursor = start + html.length;
    applyEdit(textarea, onChange, next, cursor);
}

function wrapAsList(textarea, value, onChange, range, tag) {
    const { start, end } = range;
    const selected = value.slice(start, end).trim() || 'Ítem 1\nÍtem 2';
    const items = selected
        .split(/\n+/)
        .map((line) => line.replace(/^<li>|<\/li>$/gi, '').trim())
        .filter(Boolean)
        .map((line) => `  <li>${line}</li>`)
        .join('\n');
    const html = `<${tag}>\n${items}\n</${tag}>\n`;
    insertAtRange(textarea, value, onChange, range, html);
}

function wrapDetails(textarea, value, onChange, range) {
    const { start, end } = range;
    const selected = value.slice(start, end).trim();
    const html = selected
        ? `<details>\n<summary>Título</summary>\n${selected}\n</details>\n`
        : '<details>\n<summary>Título</summary>\n<p>Contenido oculto…</p>\n</details>\n';
    insertAtRange(textarea, value, onChange, range, html);
}

function ToolBtn({ title, children, onClick }) {
    return (
        <button
            type="button"
            title={title}
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className="px-2 py-1 rounded-lg border theme-border text-[10px] font-black uppercase tracking-wide theme-text-main hover:bg-gray-50 dark:hover:bg-zinc-800"
        >
            {children}
        </button>
    );
}

export default function EditorDescripcionHtml({
    value = '',
    onChange,
    label = 'Descripción',
    minHeight = 120,
}) {
    const taRef = useRef(null);
    const rangeRef = useRef({ start: 0, end: 0 });
    const [mode, setMode] = useState('edit');

    const rememberRange = () => {
        const ta = taRef.current;
        if (!ta) return;
        rangeRef.current = { start: ta.selectionStart, end: ta.selectionEnd };
    };

    const currentRange = () => {
        const ta = taRef.current;
        if (ta && document.activeElement === ta) {
            return { start: ta.selectionStart, end: ta.selectionEnd };
        }
        return rangeRef.current;
    };

    const runTool = (tool) => {
        const ta = taRef.current;
        if (!ta) return;
        const range = currentRange();

        if (tool.link) {
            const url = window.prompt('URL del enlace', 'https://');
            if (!url) return;
            const open = `<a href="${url.replace(/"/g, '&quot;')}">`;
            wrapRange(ta, value, onChange, range, open, '</a>', 'texto del enlace');
            return;
        }
        if (tool.list) {
            wrapAsList(ta, value, onChange, range, tool.list);
            return;
        }
        if (tool.details) {
            wrapDetails(ta, value, onChange, range);
            return;
        }
        if (tool.snippet) {
            insertAtRange(ta, value, onChange, range, tool.snippet);
            return;
        }
        if (tool.wrap) {
            wrapRange(ta, value, onChange, range, tool.wrap[0], tool.wrap[1]);
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <label className={`${labelClass} mb-0`}>{label}</label>
                <div className="inline-flex rounded-xl border theme-border overflow-hidden">
                    <button
                        type="button"
                        onClick={() => setMode('edit')}
                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest ${
                            mode === 'edit' ? 'text-white' : 'theme-text-muted'
                        }`}
                        style={mode === 'edit' ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Code2 className="w-3.5 h-3.5" /> Editar
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('preview')}
                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest ${
                            mode === 'preview' ? 'text-white' : 'theme-text-muted'
                        }`}
                        style={mode === 'preview' ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        <Eye className="w-3.5 h-3.5" /> Vista previa
                    </button>
                </div>
            </div>

            {mode === 'edit' ? (
                <>
                    <div className="flex flex-wrap gap-1.5">
                        {TOOLS.map((tool) => (
                            <ToolBtn key={tool.id} title={tool.title} onClick={() => runTool(tool)}>
                                {tool.label}
                            </ToolBtn>
                        ))}
                    </div>
                    <textarea
                        ref={taRef}
                        className={inputClass}
                        style={{ minHeight }}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        onSelect={rememberRange}
                        onKeyUp={rememberRange}
                        onMouseUp={rememberRange}
                        onBlur={rememberRange}
                        spellCheck={false}
                    />
                    <p className="text-[10px] theme-text-muted m-0">
                        Selecciona texto y pulsa un botón: la etiqueta envuelve esa selección.
                    </p>
                </>
            ) : (
                <div
                    className="theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main overflow-auto
                        [&_h2]:text-lg [&_h2]:font-bold [&_h2]:mt-3 [&_h2]:mb-2
                        [&_h3]:text-base [&_h3]:font-bold [&_h3]:mt-2 [&_h3]:mb-1
                        [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5
                        [&_li]:my-0.5 [&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_blockquote]:italic [&_blockquote]:theme-text-muted
                        [&_a]:underline [&_hr]:my-3 [&_hr]:border-t [&_hr]:theme-border
                        [&_details]:my-2 [&_details]:border [&_details]:theme-border [&_details]:rounded-lg [&_details]:p-2
                        [&_summary]:cursor-pointer [&_summary]:font-bold"
                    style={{ minHeight }}
                >
                    {value?.trim() ? (
                        <div dangerouslySetInnerHTML={{ __html: sanitizePreviewHtml(value) }} />
                    ) : (
                        <p className="theme-text-muted m-0 text-xs">Sin contenido.</p>
                    )}
                </div>
            )}
        </div>
    );
}
