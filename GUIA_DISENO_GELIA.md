# Dirección visual de GELIA para agentes

Esta guía recoge la decisión del usuario del 2 de noviembre de 2026: las skills de diseño son útiles si mantienen el rumbo de GELIA. La calidad se evalúa por el resultado completo y su relación con la aplicación existente.

## Punto de partida

GELIA es una herramienta de operación comercial, logística y administrativa. Debe permitir identificar al cliente, comprender el caso, reconocer pendientes y ejecutar la siguiente acción con claridad. Su personalidad se expresa en la composición, los encabezados, la tipografía, los iconos, los estados y la jerarquía de las acciones.

**Las skills sirven a esta dirección.** Una recomendación de simplificar, modernizar, añadir carácter o reducir ruido se interpreta dentro del sistema GELIA y del encargo vigente. No habilita otra estética por defecto.

Las decisiones explícitas más recientes del usuario actualizan esta guía. Las referencias son evidencia para diseñar, no una obligación de copiar defectos o de mantener una solución incompatible con el proceso requerido.

## Referencias que deben inspeccionarse

Elegir una o dos referencias relevantes para la tarea. No es necesario recorrer todos los módulos en cada corrección.

| Referencia | Qué observar | Entrada en el código |
| --- | --- | --- |
| Operación general de Punto de venta | Jerarquía operativa, agrupación, iconos y estados | [OperacionGeneral.jsx](resources/js/Pages/PuntoVenta/Operacion/OperacionGeneral.jsx) y [EncabezadoGestionVendedores.jsx](resources/js/Pages/PuntoVenta/Operacion/Partials/EncabezadoGestionVendedores.jsx) |
| Solicitudes de tag y lista | Captura, cliente seleccionado, campos y acciones | [ModalFormSolicitud.jsx](resources/js/Pages/Solicitudes/Partials/ModalFormSolicitud.jsx) |
| Solicitudes de facturas | Cabecera, documentos, captura y estados de respuesta | [ModalFormFactura.jsx](resources/js/Pages/Facturas/Partials/ModalFormFactura.jsx) |
| Cancelaciones y cotizaciones | Identidad del encabezado y campos con iconos | [ModalFormOperativa.jsx](resources/js/Pages/CancelacionesCotizaciones/Partials/ModalFormOperativa.jsx) |
| Resguardos en Punto de venta | Contexto, identificación, estado y acciones de una tarea | [ModalRecepcionResguardo.jsx](resources/js/Pages/PuntoVenta/Resguardos/Partials/ModalRecepcionResguardo.jsx) |
| Registrar pedido — Tienda | Composición del modal revisado con el usuario: datos, cliente, atención, documento y pie de acciones | [FormularioRegistrarTienda.jsx](resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda.jsx) |

La referencia visual de Registrar pedido se conserva en el repositorio:

- [Escritorio, con captura](resources/design-references/registrar-pedido/escritorio-oscuro.png).
- [Escritorio, captura vacía y tema claro](resources/design-references/registrar-pedido/nuevo-claro.png).
- [Móvil, datos](resources/design-references/registrar-pedido/movil-datos.png) y [móvil, atención](resources/design-references/registrar-pedido/movil-atencion.png).

Son capturas de una comprobación aislada del componente realizada el 2 de noviembre de 2026, con datos de ejemplo y un documento ficticio. El nombre del cliente reproduce el ejemplo de la conversación. No acreditan integración con servidor ni imponen el color o la fuente de esa captura: ambos siguen la configuración de GELIA. Ante cambios posteriores, comparar las imágenes con el código y los estilos efectivos antes de utilizarlas.

## Ruta de trabajo

### 1. Entender el encargo y su alcance

Identificar la pantalla, quién la utiliza, qué necesita hacer y cuál es la consecuencia de su acción. Consultar requisitos operativos vigentes cuando se afecte el flujo. Revisar cambios locales antes de editar para preservar trabajo existente.

Clasificar el trabajo:

- **Corrección puntual:** resolver el defecto manteniendo la composición y el lenguaje del conjunto.
- **Nueva superficie o recomposición:** extender los patrones de GELIA al caso, con una dirección visual concreta.
- **Exploración solicitada:** presentar alternativas aisladas cuando la elección de dirección siga abierta.

Si el usuario cuestiona el estilo o la personalidad completos, revisar la composición completa. No reducir ese señalamiento a ajustar espacios, bordes o un texto roto.

### 2. Reconocer la identidad antes de usar skills

Inspeccionar una referencia de la tabla y responder, brevemente:

- ¿Cómo presenta el título, el contexto y los estados?
- ¿Cómo identifica al cliente sin repetir información?
- ¿Cómo agrupa campos, documentos y decisiones?
- ¿Qué distingue la acción principal de guardar, volver o cerrar?
- ¿Qué comparte con el resto de GELIA y qué varía por la tarea?

Consultar las fuentes efectivas en este orden:

1. [Tokens](resources/css/gelia/tokens.css), incluida la personalización aplicada por [AppLayout](resources/js/Layouts/AppLayout.jsx).
2. [Primitivas](resources/css/gelia/primitives.css) y [utilidades](resources/css/gelia/utilities.css).
3. [Tema y constantes JSX](resources/js/utils/geliaTheme.js) y componentes compartidos de `resources/js/Components/`.
4. Estilos del módulo y orden de importación en [app.css](resources/css/app.css).

El [contrato de consumo](docs/style-system/contrato-diseno-fase-2.md) amplía estas reglas cuando esté disponible en el entorno. Esta guía y las fuentes anteriores contienen el mínimo necesario; la ausencia de documentación local no obliga a inventar un sistema nuevo.

### 3. Elegir ayuda con un propósito

Antes de cargar una skill, formular la necesidad: «Necesito ayuda para ___, conservando ___ de GELIA». Si no hay una necesidad concreta, continuar con los patrones existentes.

| Necesidad | Apoyo posible | Límite |
| --- | --- | --- |
| Composición de una superficie nueva | `frontend-design`; otras skills disponibles si aportan algo específico | Extender la identidad del producto, sin introducir una estética de plantilla |
| Jerarquía, densidad o claridad | Impeccable, de forma opcional | Evaluar sus recomendaciones contra la referencia y el objetivo; no convertirlas en reglas estéticas universales |
| Revisión de accesibilidad y uso | `web-design-guidelines` | Corregir problemas observados; no afirmar certificación por haber leído una guía |
| Texto y datos extremos | `break-ui`, si se necesita una exploración de casos | Comprobar desbordamiento y comprensión con datos plausibles; no añadir una pantalla de demostración sin necesidad del encargo |
| Comportamiento móvil | `mobile-native` | Adaptar la tarea al espacio disponible sin perder información ni acciones |
| Movimiento que ayuda a una interacción | `emil-design-eng`, `animate`, `review-animations` o `animation-performance`, según el problema | No iniciar una búsqueda de animaciones en cada cambio; respetar reducción de movimiento |
| Alternativas visuales solicitadas | `prototype` o el flujo de exploración disponible | No repetir una elección que el usuario ya hizo |

Leer las instrucciones reales de la skill elegida. Si no está disponible, buscarla en las ubicaciones del entorno y continuar con los recursos permitidos cuando sea posible, explicando la limitación. No instalar skills, activar hooks, incorporar dependencias o iniciar auditorías amplias como efecto secundario de un ajuste visual.

Cuando una recomendación choque con una decisión vigente del usuario o con la identidad acordada, adaptar la recomendación y seguir la precedencia de instrucciones del entorno. No modificar paquetes de skills para resolver una preferencia específica de GELIA: documentar esa preferencia aquí y en sus puntos de entrada.

### 4. Definir la composición y realizar el cambio

Para una recomposición, expresar antes de editar una dirección breve: referencia elegida, qué se reutiliza, qué se reorganiza y por qué. Esto informa al usuario; no añade una solicitud de aprobación cuando el trabajo ya está autorizado.

- Dar al encabezado una identidad y una jerarquía reconocibles en GELIA. Reutilizar variantes existentes de títulos, contenedores de iconos y contexto según la superficie.
- Agrupar por tareas y decisiones. Usar contraste de superficies, separación y títulos con intención; evitar una sucesión de tarjetas indistinguibles.
- Mostrar al cliente con nombre legible, número y lista cuando correspondan. Evitar duplicar su ficha en la cabecera y en el cuerpo sin una razón operativa.
- Integrar iconos en controles y grupos donde aporten reconocimiento. Usar las familias y tamaños existentes de forma consistente.
- Hacer que una selección se reconozca por forma, marca y texto, además de color. Distinguir selección, pendiente y confirmación real.
- Mantener visibles y estables las acciones pertinentes. Guardar borrador y enviar una solicitud deben comunicar consecuencias diferentes. Evitar controles cuyo tamaño cambie al pasar el cursor en una captura operativa.
- Reservar al documento un espacio proporcionado, con estado, nombre, vista previa y acciones comprensibles.
- Reutilizar antes de extender; extender antes de crear. Respetar tema, acento y fuente configurados; las referencias no fijan una paleta ni una tipografía paralelas.
- Dimensionar según el espacio real del componente. Un modal estrecho dentro de una ventana amplia necesita su propia adaptación; una regla basada solo en la ventana puede comprimir el contenido.
- Mantener la lógica de negocio fuera de los ajustes de estilo. No añadir requisitos, cambiar validaciones ni inventar estados para que la pantalla se vea más sencilla.

### 5. Comparar el conjunto renderizado

La revisión tiene dos preguntas distintas: «¿Funciona y se puede usar?» y «¿Se reconoce como GELIA junto a las referencias?». Ambas necesitan evidencia.

Comprobar, de forma proporcional al cambio:

- Escritorio, ancho real del modal y móvil; incluir una altura reducida si hay cabeceras o pies persistentes.
- Temas claro y oscuro cuando el cambio afecte superficies, contraste o estados.
- Captura vacía y con datos, nombres largos, documentos y mensajes pertinentes.
- Jerarquía del título y las acciones, densidad, coherencia de iconos y lectura del cliente.
- Foco, teclado, etiquetas y errores; acciones accesibles aunque el cuerpo tenga desplazamiento.
- Ausencia de texto letra por letra, desbordamientos, solapamientos y saltos de tamaño en controles.

Usar el servidor de desarrollo existente si está disponible; iniciar uno solo si hace falta. No ejecutar una compilación de producción únicamente para revisar estilos. Ejecutar verificaciones de estilo y pruebas pertinentes al alcance; no crear pruebas que solo repitan el CSS.

Si falta un archivo de referencia de los verificadores, informar la limitación y revisar los archivos tocados con las herramientas disponibles. No regenerar listas de excepciones para ocultar una comprobación fallida.

### 6. Dejar evidencia útil para continuar

Al cerrar, indicar la referencia utilizada, los cambios de composición, las comprobaciones realizadas y las limitaciones reales. Distinguir lectura de una skill, ejecución de sus herramientas, revisión visual e integración funcional.

Guardar las referencias duraderas dentro del repositorio y enlazarlas desde esta guía. `.cursor/` puede versionarse tras el ajuste del usuario del 2 de noviembre de 2026; sus reglas deben apuntar a esta guía común. `.codex/` y `docs/` siguen ignorados actualmente: una decisión que deba viajar con el proyecto no debe existir solo allí. Los archivos nuevos requieren incorporarse al próximo commit para compartirse.

Si el usuario aprueba una nueva dirección o corrige una anterior, actualizar esta guía y las referencias afectadas con fecha y alcance. Conservar una única dirección común evita reglas contradictorias entre agentes.

## Señales de que se perdió el rumbo

- El resultado respeta tokens, pero parece pertenecer a otra aplicación.
- Se eliminó la identidad del encabezado o se uniformaron todos los elementos por aplicar una recomendación genérica.
- Se corrigió un defecto de distribución y se dio por resuelta una crítica a la composición completa.
- Se incorporaron iconos, tarjetas, texto o movimiento sin una función reconocible.
- Se dieron por ejecutadas skills, auditorías o pruebas que solo se leyeron o planificaron.

Ante estas señales, volver a la referencia pertinente, identificar la decisión que produjo la desviación y corregirla dentro del alcance autorizado. La salida es una composición coherente y comprobable, no más capas de herramientas.
