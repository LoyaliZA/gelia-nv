import React, { useEffect, useId, useRef, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Check, X } from 'lucide-react';
import { fechaCalendario, fechaLocal, fechaIsoLocal, ZONA_FECHA_NEGOCIO } from '../../utils/fechaCalendario';

const meses = Array.from({ length: 12 }, (_, mes) => new Date(2026, mes, 1).toLocaleDateString('es-MX', { month: 'long' }));
const fechaLarga = fecha => fecha.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });

/** Selector controlado de fecha civil (YYYY-MM-DD). Se despliega en el flujo para no recortarse en modales. */
export default function SelectorFecha({ id, value = '', onChange, disabled = false, 'aria-label': etiqueta = 'Fecha', 'aria-describedby': ayuda, zonaHoraria = ZONA_FECHA_NEGOCIO }) {
    const uid = useId();
    const campoId = id || uid;
    const panelId = `${campoId}-calendario`;
    const [abierto, setAbierto] = useState(false);
    const [foco, setFoco] = useState(value || fechaCalendario(new Date(), zonaHoraria));
    const contenedor = useRef(null);
    const disparador = useRef(null);
    const panel = useRef(null);
    const hoy = fechaCalendario(new Date(), zonaHoraria);
    const fecha = fechaLocal(foco);
    const anio = fecha.getFullYear();
    const mes = fecha.getMonth();
    const primero = new Date(anio, mes, 1, 12);
    const desfase = (primero.getDay() + 6) % 7;
    const dias = new Date(anio, mes + 1, 0).getDate();

    const cerrar = (devolverFoco = false) => {
        setAbierto(false);
        if (devolverFoco) disparador.current?.focus({ preventScroll: true });
    };
    const elegir = valor => { onChange(valor); cerrar(true); };
    const cambiarMes = incremento => {
        const destino = new Date(anio, mes + incremento, 1, 12);
        destino.setDate(Math.min(fecha.getDate(), new Date(destino.getFullYear(), destino.getMonth() + 1, 0).getDate()));
        setFoco(fechaIsoLocal(destino));
    };

    useEffect(() => { if (disabled) setAbierto(false); }, [disabled]);
    useEffect(() => {
        if (!abierto) return undefined;
        panel.current?.querySelector('[tabindex="0"]')?.focus({ preventScroll: true });
        // Antes del listener del modal: Escape cierra únicamente el calendario.
        const tecla = evento => {
            if (evento.key !== 'Escape') return;
            evento.preventDefault();
            evento.stopImmediatePropagation();
            cerrar(true);
        };
        const fuera = evento => { if (!contenedor.current?.contains(evento.target)) cerrar(); };
        window.addEventListener('keydown', tecla, true);
        document.addEventListener('pointerdown', fuera);
        return () => {
            window.removeEventListener('keydown', tecla, true);
            document.removeEventListener('pointerdown', fuera);
        };
    }, [abierto]);
    useEffect(() => {
        if (abierto) panel.current?.querySelector('[tabindex="0"]')?.focus({ preventScroll: true });
    }, [foco]);

    const navegar = evento => {
        const saltos = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7, Home: -((fecha.getDay() + 6) % 7), End: 6 - ((fecha.getDay() + 6) % 7) };
        if (evento.key in saltos) {
            evento.preventDefault();
            const siguiente = fechaLocal(foco);
            siguiente.setDate(siguiente.getDate() + saltos[evento.key]);
            setFoco(fechaIsoLocal(siguiente));
        } else if (evento.key === 'PageUp' || evento.key === 'PageDown') {
            evento.preventDefault();
            cambiarMes((evento.key === 'PageUp' ? -1 : 1) * (evento.shiftKey ? 12 : 1));
        }
    };

    return <div className="gelia-selector-fecha" ref={contenedor} onBlur={evento => {
        if (evento.relatedTarget && !evento.currentTarget.contains(evento.relatedTarget)) cerrar();
    }}>
        <div className="gelia-selector-fecha-controles">
            <button id={campoId} ref={disparador} type="button" className="theme-input gelia-selector-fecha-campo" aria-label={etiqueta} aria-describedby={ayuda} aria-expanded={abierto && !disabled} aria-controls={panelId} disabled={disabled} onClick={() => {
                if (abierto) cerrar();
                else { setFoco(value || hoy); setAbierto(true); }
            }}>
                <CalendarDays aria-hidden="true" size={20} />
                <span>{value ? fechaLarga(fechaLocal(value)) : 'Seleccionar fecha'}</span>
                <ChevronRight aria-hidden="true" size={16} className={abierto ? 'rotate-90' : ''} />
            </button>
            <button type="button" className="gelia-selector-fecha-hoy" disabled={disabled} onClick={() => elegir(hoy)} aria-label={`Usar fecha de hoy: ${fechaLarga(fechaLocal(hoy))}`}>Hoy</button>
        </div>
        {abierto && !disabled && <div id={panelId} ref={panel} className="gelia-calendario" role="group" aria-label={`Calendario: ${etiqueta}`}>
            <div className="gelia-calendario-cabecera">
                <button type="button" aria-label="Mes anterior" onClick={() => cambiarMes(-1)}><ChevronLeft size={20} /></button>
                <strong aria-live="polite">{meses[mes]} <span>{anio}</span></strong>
                <button type="button" aria-label="Mes siguiente" onClick={() => cambiarMes(1)}><ChevronRight size={20} /></button>
                <button type="button" aria-label="Cerrar calendario" onClick={() => cerrar(true)}><X size={18} /></button>
            </div>
            <div role="grid" aria-label={`${meses[mes]} ${anio}`} onKeyDown={navegar}>
                <div role="row" className="gelia-calendario-semana">{['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'].map(dia => <span role="columnheader" key={dia}>{dia}</span>)}</div>
                {Array.from({ length: Math.ceil((desfase + dias) / 7) }, (_, semana) => <div role="row" className="gelia-calendario-semana" key={semana}>
                    {Array.from({ length: 7 }, (_, columna) => {
                        const dia = semana * 7 + columna - desfase + 1;
                        if (dia < 1 || dia > dias) return <span role="gridcell" key={columna} />;
                        const fechaDia = new Date(anio, mes, dia, 12);
                        const iso = fechaIsoLocal(fechaDia);
                        return <div role="gridcell" aria-selected={value === iso} key={columna}><button type="button" tabIndex={foco === iso ? 0 : -1} className="gelia-calendario-dia" data-seleccionado={value === iso} aria-current={iso === hoy ? 'date' : undefined} aria-label={fechaLarga(fechaDia)} onFocus={() => setFoco(iso)} onClick={() => elegir(iso)}>{dia}{value === iso && <Check size={10} aria-hidden="true" />}</button></div>;
                    })}
                </div>)}
            </div>
            <p className="gelia-calendario-pie">{value ? `Fecha seleccionada: ${fechaLarga(fechaLocal(value))}` : 'Selecciona el día de emisión'}</p>
        </div>}
    </div>;
}
