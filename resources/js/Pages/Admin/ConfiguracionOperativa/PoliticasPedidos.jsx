import { ArrowUp, ArrowDown } from 'lucide-react';
import { THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';

export function BancosPedidos({ bancos, catalogo, departamentos, onChange, errors = {}, disabled = false }) {
    const cambiar = (i, campo, valor) => onChange(bancos.map((banco, indice) => indice === i ? { ...banco, [campo]: valor } : banco));
    return <div className="space-y-4">
        <p className="text-sm theme-text-muted m-0">Elige los bancos que se podrán utilizar en Gestión de pedidos y sus departamentos autorizados. Los bancos nuevos quedan deshabilitados hasta configurarlos. Los pagos anteriores conservan su banco.</p>
        {bancos.length === 0 && <p className="text-sm theme-text-muted">Primero agrega bancos al catálogo.</p>}
        {bancos.map((banco, i) => {
            const referencia = catalogo.find(item => item.id === banco.id);
            const mensajes = Object.entries(errors).filter(([clave]) => clave === `bancos.${i}` || clave.startsWith(`bancos.${i}.`)).map(([, mensaje]) => mensaje);
            return <fieldset key={banco.id} className="min-w-0 border theme-border rounded-xl p-4 space-y-3" disabled={disabled}>
                <legend className="px-1 text-sm font-semibold theme-text-main break-words">{referencia?.nombre}{referencia?.activo === false ? ' · Inactivo en catálogo' : ''}</legend>
                <label className="flex items-center gap-3 text-sm theme-text-main min-h-11">
                    <input type="checkbox" className="gelia-checkbox" checked={banco.habilitado} onChange={e => cambiar(i, 'habilitado', e.target.checked)} />Disponible en Gestión de pedidos
                </label>
                {banco.habilitado && <>
                    <label className="flex items-center gap-3 text-sm theme-text-main min-h-11">
                        <input type="checkbox" className="gelia-checkbox" checked={banco.compartido} onChange={e => cambiar(i, 'compartido', e.target.checked)} />Todos los departamentos
                    </label>
                    {!banco.compartido && <div className="space-y-2">
                        <p className="text-xs theme-text-muted m-0">Selecciona al menos un departamento autorizado.</p>
                        <div className="grid sm:grid-cols-2 gap-x-4 gap-y-1">
                            {departamentos.map(depto => <label key={depto.id} className="flex items-center gap-3 text-sm theme-text-main min-h-11 min-w-0">
                                <input type="checkbox" className="gelia-checkbox shrink-0" checked={banco.departamento_ids.includes(depto.id)} onChange={e => cambiar(i, 'departamento_ids', e.target.checked ? [...banco.departamento_ids, depto.id] : banco.departamento_ids.filter(id => id !== depto.id))} />
                                <span className="break-words min-w-0">{depto.nombre}{depto.activo === false ? ' · Inactivo' : ''}</span>
                            </label>)}
                        </div>
                    </div>}
                </>}
                {referencia?.activo === false && <p className="text-xs theme-text-muted m-0">Para utilizarlo también debe estar activo en Catálogos.</p>}
                {mensajes.length > 0 && <p role="alert" className="text-sm theme-text-peligro m-0">{mensajes.join(' ')}</p>}
            </fieldset>;
        })}
        {errors.bancos && <p role="alert" className="text-sm theme-text-peligro">{errors.bancos}</p>}
    </div>;
}

export function PrioridadesPedidos({ value, tipos, onChange, errors = {}, disabled = false }) {
    const mover = (i, desplazamiento) => {
        const nuevo = [...value];
        [nuevo[i], nuevo[i + desplazamiento]] = [nuevo[i + desplazamiento], nuevo[i]];
        onChange(nuevo);
    };
    const mensajes = Object.entries(errors).filter(([clave]) => clave === 'prioridades' || clave.startsWith('prioridades.')).map(([, mensaje]) => mensaje);
    return <div className="space-y-3">
        <p className="text-sm theme-text-muted m-0">La primera posición tiene mayor prioridad. Dentro de cada nivel se atiende primero la solicitud más antigua.</p>
        <ol className="list-none p-0 m-0 space-y-2" aria-label="Orden de atención">
            {value.map((tipo, i) => <li key={tipo} className="grid grid-cols-[2rem_minmax(0,1fr)] sm:flex items-center gap-x-3 gap-y-2 border theme-border rounded-xl p-3 min-w-0">
                <span className="inline-flex items-center justify-center w-8 h-8 shrink-0 rounded-lg theme-element theme-text-primario font-semibold" aria-label={`Prioridad ${i + 1}`}>{i + 1}</span>
                <span className="text-sm font-medium theme-text-main flex-1 min-w-0 break-words">{tipos[tipo]}</span>
                <div className="col-start-2 flex justify-end gap-1 shrink-0">
                    <button type="button" className={`${THEME_BTN_SECONDARY} min-w-11 min-h-11 p-2`} aria-label={`Subir ${tipos[tipo]}`} disabled={disabled || i === 0} onClick={() => mover(i, -1)}><ArrowUp size={18} aria-hidden /></button>
                    <button type="button" className={`${THEME_BTN_SECONDARY} min-w-11 min-h-11 p-2`} aria-label={`Bajar ${tipos[tipo]}`} disabled={disabled || i === value.length - 1} onClick={() => mover(i, 1)}><ArrowDown size={18} aria-hidden /></button>
                </div>
            </li>)}
        </ol>
        <p role="status" aria-live="polite" className="sr-only">{value.map((tipo, i) => `${i + 1}. ${tipos[tipo]}`).join('; ')}</p>
        {mensajes.length > 0 && <p role="alert" className="text-sm theme-text-peligro">{mensajes.join(' ')}</p>}
    </div>;
}
