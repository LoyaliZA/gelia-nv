# 001 — Fundir la apertura y el cierre de los permisos

- **Status**: DONE
- **Commit**: 188701a
- **Severity**: MEDIUM
- **Category**: Missed opportunities
- **Estimated scope**: 2 files, un componente pequeño y CSS

## Problem

En el drawer de colaboradores, las secciones, los módulos y los submódulos de permisos aparecen y desaparecen de golpe. El chevron también salta. Quien abre Logística o Gestión de pedidos ve el contenido teletransportarse. Es un panel ocasional, no un atajo de teclado: un fundido corto evita el salto sin retrasar la lectura.

El cuerpo se monta solo mientras está abierto y se desmonta en el mismo instante del cierre. No hay transición.

```jsx
/* resources/js/Pages/Admin/Partials/PermisosAtomicos.jsx:317 — current */
{abierto && (
    <div className={`border-t theme-border ${contentClassName}`}>
        {children}
    </div>
)}
```

Las secciones hacen lo mismo, fuera de `AcordeonAnimado`:

```jsx
/* resources/js/Pages/Admin/Partials/PermisosAtomicos.jsx:446 — current */
{abierta && !keyPrefix && seccion.id === 'punto_venta' && (
    <ResumenCapacidadesPdv
        permisos={activos}
        sucursalesCatalogo={sucursalesCatalogo}
        sucursalIds={sucursalIds}
        sucursalPrincipalId={sucursalPrincipalId}
    />
)}
{abierta && (
<div className="space-y-2">
    {seccion.modulos.map(({ modulo, permisos }) => {
```

El cierre de ese bloque está en la línea 490: `)}`.

El chevron cambia de clase sin transición propia. Hay cuatro copias:

```jsx
/* PermisosAtomicos.jsx:261 — submódulo */
<ChevronRight
    className={`w-4 h-4 theme-text-muted shrink-0 ${abierto ? 'rotate-90' : ''}`}
    aria-hidden
/>

/* PermisosAtomicos.jsx:362 — módulo */
<ChevronRight
    className={`w-4 h-4 theme-text-muted shrink-0 ${expandido ? 'rotate-90' : ''}`}
    aria-hidden
/>

/* PermisosAtomicos.jsx:428 — sección */
<ChevronRight className={`w-4 h-4 theme-text-muted shrink-0 ${abierta ? 'rotate-90' : ''}`} aria-hidden />

/* PermisosAtomicos.jsx:776 — plantilla */
<ChevronRight
    className={`w-4 h-4 theme-text-muted shrink-0 ${plantillaExpandida ? 'rotate-90' : ''}`}
    aria-hidden
/>
```

No animar la altura. Gestión de pedidos mide miles de píxeles; interpolar `height`, `max-height` o `grid-template-rows` recalcula el layout y mueve el texto que se está leyendo. Tampoco escalar ese cuerpo: un `scale` en una lista larga se lee como un zoom, no como una pista. La pista espacial es el giro del chevron. El cuerpo solo cambia de opacidad.

## Target

Curva y duraciones, copiadas del selector de sección que ya está en el mismo CSS. No crear variables `--ease-*`: este archivo no las usa.

- Cuerpo: solo `opacity`, 200ms, `cubic-bezier(0.23, 1, 0.32, 1)`. Cerrado en 0, abierto en 1.
- Chevron: solo `transform`, 160ms, la misma curva, de `none` a `rotate(90deg)`.
- Cierre: el nodo sigue montado hasta que termina la opacidad y entonces se desmonta. La altura cae de una vez al desmontar. Ese salto de altura es intencional.
- Durante el fundido de cierre, `pointer-events: none` para no pulsar casillas que ya se están yendo.
- Interrupción: transición CSS, no `@keyframes`. Si se cierra a medias y se vuelve a abrir, la opacidad continúa desde el valor actual.
- `prefers-reduced-motion: reduce`: el chevron no se anima (`transition: none`) pero sigue en 90° cuando está abierto. El cuerpo conserva un fundido de opacidad de 150ms `linear`. No quitar el fundido por completo.
- La búsqueda no necesita un caso aparte. El fundido corre solo cuando `abierto` pasa de falso a verdadero. Seguir escribiendo no debe reiniciarlo.

```css
/* target — resources/css/gelia/features/usuarios-drawer.css */
.usuarios-permiso-cuerpo {
    transition: opacity 200ms cubic-bezier(0.23, 1, 0.32, 1);
}

.usuarios-permiso-cuerpo[data-estado="cerrado"] {
    opacity: 0;
    pointer-events: none;
}

.usuarios-permiso-cuerpo[data-estado="abierto"] {
    opacity: 1;
}

.usuarios-permiso-chevron {
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
}

.usuarios-permiso-chevron[data-abierto="true"] {
    transform: rotate(90deg);
}

@media (prefers-reduced-motion: reduce) {
    .usuarios-permiso-chevron {
        transition: none;
    }

    .usuarios-permiso-cuerpo {
        transition: opacity 150ms linear;
    }
}
```

No añadir `transform`, `scale`, `height`, `max-height` ni `grid-template-rows` al cuerpo.

## Repo conventions to follow

El ejemplar es el chevron y el panel del selector de sección. Imitar la transición y el `data-estado`. No imitar el `scale(0.97)` de ese panel: ese scale es de un menú corto, no de una lista de permisos.

```css
/* resources/css/gelia/features/usuarios-drawer.css:377 — exemplar, no copiar el scale */
.usuarios-selector--seccion .usuarios-selector-chevron {
    transition: transform 160ms cubic-bezier(0.23, 1, 0.32, 1);
}

.usuarios-selector--seccion .usuarios-selector-panel[data-estado="cerrado"] {
    opacity: 0;
    pointer-events: none;
}
```

El movimiento reducido del mismo archivo está en la línea 490. Añadir las reglas nuevas dentro de ese `@media`, no abrir otro.

La presencia hasta el fin de la transición ya existe en `resources/js/Pages/Admin/Partials/SelectorConBusqueda.jsx`: estado `presente`, `requestAnimationFrame` para pasar a abierto, `onTransitionEnd` filtrando `propertyName === 'opacity'`, y un `setTimeout` de 260ms por si el evento no dispara. Repetir ese contrato. No instalar una librería de movimiento.

Los botones del drawer ya tienen presión propia. No tocarla:

```css
/* resources/css/gelia/features/usuarios-drawer.css:567 */
.usuarios-drawer button {
    transition: transform 140ms cubic-bezier(0.23, 1, 0.32, 1);
}

.usuarios-drawer button:active {
    transform: scale(0.97);
}
```

Esa transición es del botón, no del SVG. El chevron lleva la suya en `.usuarios-permiso-chevron`.

## Steps

1. En `resources/css/gelia/features/usuarios-drawer.css`, después de `.usuarios-permiso-fila input` (cerca de la línea 688), añadir las reglas de `.usuarios-permiso-cuerpo` y `.usuarios-permiso-chevron` del apartado Target, sin el bloque `@media`.
2. Dentro del `@media (prefers-reduced-motion: reduce)` que empieza en la línea 490, añadir solo las dos reglas de chevron y cuerpo del Target. No poner `transform: none` en el chevron abierto: tiene que seguir girado para indicar el estado.
3. En `resources/js/Pages/Admin/Partials/PermisosAtomicos.jsx`, ampliar el import de React con `useRef`.
4. Encima de `function AcordeonAnimado`, añadir este componente y usarlo tal cual:

```jsx
function CuerpoRevelado({ abierto, className = '', children }) {
    const abiertoRef = useRef(abierto);
    abiertoRef.current = abierto;
    const [presente, setPresente] = useState(abierto);
    const [estado, setEstado] = useState(abierto ? 'abierto' : 'cerrado');

    useEffect(() => {
        if (abierto) {
            setPresente(true);
            const marco = requestAnimationFrame(() => setEstado('abierto'));
            return () => cancelAnimationFrame(marco);
        }
        setEstado('cerrado');
        const timer = window.setTimeout(() => {
            if (!abiertoRef.current) setPresente(false);
        }, 260);
        return () => window.clearTimeout(timer);
    }, [abierto]);

    if (!presente) return null;

    return (
        <div
            className={`usuarios-permiso-cuerpo ${className}`}
            data-estado={estado}
            onTransitionEnd={(event) => {
                if (event.target !== event.currentTarget || event.propertyName !== 'opacity') return;
                if (!abiertoRef.current) setPresente(false);
            }}
        >
            {children}
        </div>
    );
}
```

5. En `AcordeonAnimado`, sustituir el bloque `{abierto && ( <div className={...border-t...}>...</div> )}` por:

```jsx
<CuerpoRevelado abierto={abierto} className={`border-t theme-border ${contentClassName}`}>
    {children}
</CuerpoRevelado>
```

6. En los cuatro `ChevronRight` de las líneas 261, 362, 428 y 776, quitar la clase condicional `rotate-90`. Dejar la clase fija `usuarios-permiso-chevron w-4 h-4 theme-text-muted shrink-0` y el atributo `data-abierto` en `"true"` o `"false"` según `abierto`, `expandido`, `abierta` o `plantillaExpandida`. No usar la utilidad `rotate-90`: en este proyecto puede ser la propiedad CSS `rotate` y no animaría junto con `transform`.
7. En `SeccionesSidebarPermisos`, quitar los dos condicionales `{abierta && ...}` de las líneas 446 y 454. Envolver el resumen de punto de venta y la lista de módulos en un solo `CuerpoRevelado`:

```jsx
<CuerpoRevelado abierto={abierta} className="space-y-2">
    {!keyPrefix && seccion.id === 'punto_venta' && (
        <ResumenCapacidadesPdv
            permisos={activos}
            sucursalesCatalogo={sucursalesCatalogo}
            sucursalIds={sucursalIds}
            sucursalPrincipalId={sucursalPrincipalId}
        />
    )}
    {seccion.modulos.map(({ modulo, permisos }) => {
        /* el map actual, sin cambiar AcordeonModulo ni MatrizPermisos */
    })}
</CuerpoRevelado>
```

El resumen de punto de venta no debe seguir dependiendo de `abierta` por dentro: si no, desaparece antes del fundido. `CuerpoRevelado` ya lo oculta al desmontar.

Si `colapsarSecciones` es falso, `abierta` nace verdadero. El estado inicial de `CuerpoRevelado` tiene que ser `presente` verdadero y `data-estado="abierto"`, para no fundir desde cero en el primer pintado.

## Boundaries

- No tocar casillas, «Seleccionar todos», conteos `0/66`, insignias de procedencia ni el botón Guardar.
- No cambiar qué permiso queda marcado ni qué sección empieza cerrada.
- No animar la altura ni escalar el cuerpo de permisos.
- No añadir dependencias ni tokens globales de easing.
- No modificar `.usuarios-drawer button` ni el selector de sección.
- No cambiar `ModalUsuarioForm.jsx`.
- Si el código de `AcordeonAnimado` o de los condicionales `{abierta &&` no coincide con los extractos de este plan, parar y reportarlo. No improvisar otro mecanismo.

## Verification

- **Mechanical**: desde la raíz del repo, `npm run build`. Tiene que terminar con código 0 y el texto «Compilación publicada». No ejecutar migraciones.
- **Feel check**: publicar el build y abrir el drawer de un colaborador, pestaña Rol y Permisos, con el drawer estrecho.
  - Operaciones y Logística nacen cerradas. Al abrir Logística, los módulos (Entregas, Gestión de pedidos) aparecen fundidos, no de golpe, y el chevron gira hasta apuntar hacia abajo. Gestión de pedidos nace cerrado.
  - Al cerrar Logística, el contenido se desvanece y después desaparece el hueco. El hueco no se encoge de forma continua.
  - Abrir y cerrar Gestión de pedidos y, dentro, Registrar pedidos: el mismo fundido y el mismo giro. La lista no hace zoom.
  - Pulsar el encabezado otra vez antes de que termine el fundido: la opacidad da la vuelta desde donde va, no reinicia desde invisible.
  - En DevTools, Animations al 10 %: la transición del cuerpo es solo `opacity` a 200ms; la del chevron es solo `transform` a 160ms. No aparece `height`.
  - Con `prefers-reduced-motion: reduce`, el chevron cambia de orientación sin recorrer el giro, y el cuerpo todavía se funde, más corto.
  - Buscar un permiso y seguir escribiendo: los apartados que ya se abrieron no vuelven a fundirse en cada tecla.
  - En un drawer ancho, las pestañas Datos Generales / Rol y Permisos / Organización siguen igual.
- **Done when**: abrir y cerrar sección, módulo y submódulo funde la opacidad en 200ms, el chevron gira en 160ms, y el CSS del cuerpo no declara `height`, `max-height`, `grid-template-rows` ni `transform`.
