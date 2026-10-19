import React, { useState } from 'react';
import { AlertCircle, CalendarDays, Check, Copy, MessageCircle, Sun, UserRound } from 'lucide-react';
import GeliaAiMarkdown from './GeliaAiMarkdown';

const FASES = [['salida', 'Salida', 'La primera impresión'], ['corazon', 'Corazón', 'El carácter del perfume'], ['fondo', 'Fondo', 'Lo que permanece al secar']];
const USOS = [['preferencia', 'Para quién', UserRound], ['ocasiones', 'Cuándo ofrecerlo', CalendarDays], ['clima', 'Clima y temporada', Sun], ['uso', 'Cómo usarlo', MessageCircle]];

export default function FichaPerfumeCard({ ficha }) {
    const [copiado, setCopiado] = useState(false);
    const [errorCopia, setErrorCopia] = useState('');
    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(ficha.speech.replace(/\*\*|`/g, ''));
            setCopiado(true);
            setErrorCopia('');
        } catch {
            setCopiado(false);
            setErrorCopia('No se pudo copiar. Puedes seleccionar el texto del speech.');
        }
    };

    return (
        <article className="gelia-ai-perfume" aria-label="Ficha de venta del perfume">
            <header className="gelia-ai-perfume-header"><h3>{ficha.titulo}</h3><span>Guía para recomendar</span></header>
            <section className="gelia-ai-perfume-speech" aria-label="Speech para el cliente">
                <div className="gelia-ai-perfume-speech-head">
                    <h4><MessageCircle className="w-4 h-4" aria-hidden />Díselo así</h4>
                    <button type="button" className="gelia-ai-btn" onClick={copiar} aria-label="Copiar speech de venta">{copiado ? <Check className="w-3.5 h-3.5" aria-hidden /> : <Copy className="w-3.5 h-3.5" aria-hidden />}{copiado ? 'Copiado' : 'Copiar'}</button>
                </div>
                <GeliaAiMarkdown text={ficha.speech} />
                <span className="sr-only" role="status">{copiado ? 'Speech copiado' : ''}</span>
                {errorCopia && <p className="gelia-ai-perfume-copy-error" role="alert">{errorCopia}</p>}
            </section>
            <section aria-label="Notas olfativas">
                <h4 className="gelia-ai-perfume-section-title">Así evoluciona su aroma</h4>
                <dl className="gelia-ai-perfume-notas">
                    {FASES.map(([clave, label, ayuda]) => <div key={clave}><dt>{label}<span>{ayuda}</span></dt><dd><GeliaAiMarkdown text={ficha.notas[clave] || 'Sin información confirmada'} /></dd></div>)}
                </dl>
                {ficha.notasExtra && <GeliaAiMarkdown text={ficha.notasExtra} />}
            </section>
            {(Object.keys(ficha.usos).length > 0 || ficha.usosExtra) && <section aria-label="Sugerencias de uso">
                <h4 className="gelia-ai-perfume-section-title">Encuentra el momento para ofrecerlo</h4>
                <p className="gelia-ai-perfume-caption">Sugerencias según su perfil aromático.</p>
                <dl className="gelia-ai-perfume-usos">{USOS.filter(([clave]) => ficha.usos[clave]).map(([clave, label, Icon]) => <div key={clave}><dt><Icon className="w-4 h-4" aria-hidden />{label}</dt><dd><GeliaAiMarkdown text={ficha.usos[clave]} /></dd></div>)}</dl>
                {ficha.usosExtra && <GeliaAiMarkdown text={ficha.usosExtra} />}
            </section>}
            {ficha.aviso && <aside className="gelia-ai-perfume-aviso"><AlertCircle className="w-4 h-4" aria-hidden /><div><strong>Ten en cuenta</strong><GeliaAiMarkdown text={ficha.aviso} /></div></aside>}
            {(ficha.fuentes || ficha.identificacion) && <details className="gelia-ai-perfume-fuentes"><summary>Ver fuentes y detalle del producto</summary>{ficha.identificacion && <GeliaAiMarkdown text={ficha.identificacion} />}{ficha.fuentes && <GeliaAiMarkdown text={ficha.fuentes} />}</details>}
        </article>
    );
}
