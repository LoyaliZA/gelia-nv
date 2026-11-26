# GELIA — identidad visual

**Referencia viva: la vista Listados** ([Listados.jsx](resources/js/Pages/FuncionesOperativas/Listados.jsx), ruta `/funciones/listados`). Es la que mejor conserva la identidad original de GELIA. Ábrela antes de diseñar y busca ahí la solución a tu caso; si otra pantalla se ve distinta, la que se acerca a Listados es la que está bien.

> **Libertad de diseño.** Listados fija el concepto, no cada medida. Si ves una forma de que se vea o se entienda mejor (composición, radios, densidad, jerarquía), propónla y aplícala, siempre que conserve el concepto de abajo. Explica el cambio en una línea. No hace falta copiar Listados al píxel.

## Principio

**Lo gráfico antes que el texto.** Cada elemento se reconoce por su icono, su color y su forma; el texto confirma, no es lo único que hay. Un bote de basura se entiende más rápido que la palabra «Eliminar» en gris pálido. Un botón debe verse como botón.

## Qué hace Listados (y por tanto GELIA)

- **Cada elemento tiene su identidad de color e icono.** Plantillas base: un icono distinto con su color (azul, morado, ámbar, verde, rojo). Plantillas propias: el usuario elige color e icono, y la tarjeta lo usa en una barra superior, en el contenedor del icono (fondo al 10 %, borde al 30 %) y al pasar el cursor.
- **Etiquetas con sistema.** Cada tipo de dato siempre usa el mismo color e icono: Existencias azul, Precios verde, Costos morado, restricciones ámbar. Son estados o categorías reales, no decoración. Forma: píldora pequeña, fondo tenue, borde fino, icono + una palabra.
- **Acciones con icono.** Editar (lápiz), Borrar (bote), Descargar, Subir, Destinatarios, Ajustes. El icono es el protagonista; la palabra es breve y acompaña.
- **Una acción principal con el color de acento** (relleno sólido, ej. «Nueva plantilla»). Las secundarias son botones con borde que se iluminan con el acento al pasar el cursor.
- **Composición.** Tarjetas grandes de esquinas muy redondeadas por sección, encabezado con icono en contenedor tenue + título en mayúsculas cursivas con guion bajo (`Plantillas base_`) y una línea de apoyo; cuadrículas de tarjetas con pequeña elevación al pasar el cursor.
- **Zonas de carga** con borde discontinuo, icono grande y un botón claro; cambian a verde con la marca de archivo cargado.

## Reglas

- Se usan los tokens y primitivas existentes: `theme-surface`, `theme-border`, `theme-text-main`, `theme-text-muted`, `var(--color-primario)`, `GELIA_PALETTE`, y los iconos de Lucide (`lucide-react`). Tema claro/oscuro, fuente y acento son configurables; no fijes hex ni tipografías.
- Un color significa una sola cosa en todo el producto. Para estados operativos (pendiente, en custodia, entregado) añade siempre icono o texto además del color.
- Los iconos acompañan a toda acción repetida y a toda categoría. No mezclar librerías ni usar emojis.
- No añadir pantallas de demostración ni secciones explicativas que el encargo no pidió.
- Prohibido: glassmorphism, degradados arcoíris, ilustraciones de caricatura, scrollbars anidados, textos de acción sin forma de botón.
- Accesibilidad: contraste de 4.5:1 en texto normal, objetivos táctiles de 44 px, foco visible, `prefers-reduced-motion`. Las acciones solo con icono llevan `aria-label` y tooltip.
- Acciones destructivas: confirmación explícita con el nombre de lo que se elimina.
- Copy: español directo, sentence case en párrafos; los botones empiezan con verbo y nombran la operación, nunca códigos de ficha. Las personas se nombran por rol o acción, sin género («Registrado por», «Responsable»).
- Movimiento: breve y con propósito (hover, foco, cambio de estado). Iconos animados de Lucide solo para la acción principal o un estado confirmado.

## Cómo trabajar

1. Abre Listados y una vista del mismo tipo que la tuya; identifica icono, color y jerarquía que ya usan.
2. Reutiliza componentes y tokens. Extiende antes de crear.
3. Haz el cambio completo que pide el usuario. Si cuestiona la apariencia, revisa la composición entera, no solo espacios.
4. Verifica en la app real a 1440, 1024 y 390 px, en claro y oscuro ([GUIA_EVIDENCIA_VISUAL.md](GUIA_EVIDENCIA_VISUAL.md)).

Las skills de diseño (`frontend-design`, `design-system`) y Superpowers son bienvenidas cuando ayuden a un problema concreto. Usa una o dos, no una cadena, y mantén el concepto de GELIA. Para exploraciones de varias alternativas, espera a que el usuario las pida.
