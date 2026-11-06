# Dirección visual de GELIA para agentes

Decisión posterior del 4 de noviembre para Tienda: mantener dos entradas iniciales (consulta en Tienda o CEDIS). TIENDA MD se solicita al confirmar transferencia después de cerrar la consulta; no ofrecer una tercera entrada ni mostrar el desglose del proceso. Usar una nota breve con horario configurado. El selector de sucursal de consulta reutiliza los almacenes PDV habilitados del catálogo. Configuración operativa extiende los paneles y controles temáticos de Configuración del sistema y Plazos, sin una cadena nueva de skills. Véase [configuración operativa](gestion-pedidos-docs/CONFIGURACION_OPERATIVA_TIENDA.md).

Continuación operativa del 4 de noviembre de 2026: [T-02 — transferencia y custodia](gestion-pedidos-docs/TIENDA_T02_IMPLEMENTACION.md) reutiliza los paneles de Registrar pedidos, galerías de evidencia y primitivas GELIA. Presentar procedencia y sucursal de recolección como datos distintos, y separar preparación, salida, recepción, ubicación de custodia y entrega. Conservar tema, acento y fuente; el plazo de 20 días identifica gestión pendiente, sin liberar mercancía pagada automáticamente. La referencia registra el alcance y los límites de comprobación visual.

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

### Adaptación de skills a GELIA

Ajuste del 5 de noviembre de 2026:

- Se retiraron las skills locales `visual-refresh`, `visual-discovery` y `visual-implementation`, junto con su regla de entrada. No recuperarlas por referencias históricas ni sustituirlas por otra cadena automática. Las propuestas visuales siguen siendo posibles cuando el usuario las solicite.
- Al usar `frontend-design`, interpretar su plan de color y tipografía como un mapa de los tokens y la fuente configurada existentes. No crear una paleta, elegir otra familia ni añadir un hero por seguir su receta. Su búsqueda de carácter se aplica a la composición del encargo dentro de GELIA.
- Las categorías SaaS/ERP o ecommerce describen tareas; no fijan la apariencia. Las referencias GELIA y el encargo vigente gobiernan la composición.
- El cambio mínimo debe resolver el problema visual completo solicitado. Una crítica a la composición requiere revisar jerarquía, agrupación, densidad y acciones del conjunto afectado.
- Impeccable queda disponible para revisión manual cuando exista una necesidad concreta. Su hook automático se desactiva en la configuración compartida del proyecto; no reactivarlo como efecto secundario. Sus hallazgos se evalúan contra el encargo y las referencias, sin convertir preferencias genéricas en obligaciones.

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

Este servidor sirve los assets de `public/build`, conforme a `AGENTS.md`. No iniciar Vite ni dejar `public/hot`. Agrupar los cambios y ejecutar `./vendor/bin/sail npm run build` cuando sea necesario verlos en la aplicación; comprobar que la página consume ese build antes de evaluar el resultado. No compilar después de cada edición. Ejecutar verificaciones de estilo y pruebas pertinentes al alcance; no crear pruebas que solo repitan el CSS.

Si falta un archivo de referencia de los verificadores, informar la limitación y revisar los archivos tocados con las herramientas disponibles. No regenerar listas de excepciones para ocultar una comprobación fallida.

### 6. Dejar evidencia útil para continuar

Al cerrar, indicar la referencia utilizada, los cambios de composición, las comprobaciones realizadas y las limitaciones reales. Distinguir lectura de una skill, ejecución de sus herramientas, revisión visual e integración funcional.

Guardar las referencias duraderas dentro del repositorio y enlazarlas desde esta guía. `.cursor/` puede versionarse tras el ajuste del usuario del 2 de noviembre de 2026; sus reglas deben apuntar a esta guía común. `.codex/` y `docs/` siguen ignorados actualmente: una decisión que deba viajar con el proyecto no debe existir solo allí. Los archivos nuevos requieren incorporarse al próximo commit para compartirse.

Si el usuario aprueba una nueva dirección o corrige una anterior, actualizar esta guía y las referencias afectadas con fecha y alcance. Conservar una única dirección común evita reglas contradictorias entre agentes.

## Señales de que se perdió el rumbo

Decisión del usuario del 3 de noviembre de 2026, acotada a Preparación Tienda y ajustes de piezas: conservar el espacio operativo con documento, escáner/buscador, atributos y galería al integrar revisión por excepciones. No superponer un formulario genérico que los oculte ni dar por conservada una evidencia solo porque existe un selector de archivos. Para anexos, priorizar PDF/captura y ofrecer selección por SKU/nombre como alternativa. La selección de piezas para retiro se conserva y se pule. El recorrido vigente está en [Tienda — decisiones vigentes y estado verificado](gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md), con el alcance de las cinco fases y sus límites de comprobación.

Seguimiento en Registrar pedidos, misma fecha: Resultado del apartado y Ajuste de piezas usan el panel de Datos del pedido, con superficie sólida e icono junto al título. El retiro admite varias capturas de la petición del cliente, sin tope de archivos en la aplicación, con el rótulo «Evidencia petición cliente»; no es obligatoria. El motivo sale de un catálogo y solo «Otro» abre texto. Si la respuesta solo trae piezas con detalle, la pieza a retirar se busca por nombre o SKU. Cobertura y las secciones siguientes aparecen después de cerrar la consulta.

Decisión del 4 de noviembre de 2026, limitada a importes y pago de tienda: después del cierre vigente de disponibilidad, presentar total de mercancía, conceptos aplicables, saldo a favor y desglose antes de registrar el pago. La vendedora registra transferencias al banco del catálogo y cobros presenciales a Caja PDV; crédito omite la exhibición de pago. Ofrecer una acción que llene el importe pendiente y mostrar faltante, cobertura completa o excedente calculados. Mantener los paneles con superficie sólida e icono de Datos del pedido; resolver este ajuste por proceso, sin iniciar otra cadena de skills de diseño. Las reglas de importes y pago, junto con sus comprobaciones y pendientes, están en la referencia vigente de Tienda; su documentación no acredita por sí sola implementación ni pruebas.

- El resultado respeta tokens, pero parece pertenecer a otra aplicación.
- Se eliminó la identidad del encabezado o se uniformaron todos los elementos por aplicar una recomendación genérica.
- Se corrigió un defecto de distribución y se dio por resuelta una crítica a la composición completa.
- Se incorporaron iconos, tarjetas, texto o movimiento sin una función reconocible.
- Se dieron por ejecutadas skills, auditorías o pruebas que solo se leyeron o planificaron.

Ante estas señales, volver a la referencia pertinente, identificar la decisión que produjo la desviación y corregirla dentro del alcance autorizado. La salida es una composición coherente y comprobable, no más capas de herramientas.

Seguimiento del 4 de noviembre de 2026: la referencia operativa es [Tienda — decisiones vigentes y estado verificado](gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md). Los tres planes de esta entrega fueron retirados después de consolidar sus decisiones y pendientes. La referencia sustituye las instrucciones antiguas de prototipo y bloqueo general. El usuario retiró el panel separado «Decisión del cliente»: los cambios se gestionan con anexo/retiro y Cerrar consulta. No restaurarlo desde planes históricos. Conservar el espacio de preparación y limitar la captura/respuesta al trabajo pendiente real; responder un ajuste no equivale a apartarlo físicamente.

Referencias de comprobación del 4 de noviembre de 2026: [importes](resources/design-references/tienda-recorrido/importes-escritorio-oscuro.png), [anexo móvil](resources/design-references/tienda-recorrido/anexo-movil-claro.png) y [entrega móvil](resources/design-references/tienda-recorrido/entrega-movil-oscuro.png). Son componentes reales con datos ficticios, no una sesión autenticada; [alcance y reproducción](scripts/qa/tienda-recorrido/README.md). Se sustituyeron las capturas de host bloqueado y se añadió una comprobación que impide publicarlas como evidencia válida.

Seguimiento del 4 de noviembre de 2026 para T-01: el cierre de consulta muestra «Separación aprobada» y avisa al auxiliar. La confirmación física se realiza por pedido completo sin volver a capturar piezas individuales. Conservar el documento y la respuesta como referencia; los controles por pieza de otros flujos no se eliminan. Pago al recoger se cobra y remisiona en PDV: importe «Se paga en tienda», contador desde la modalidad confirmada, entrega con número de remisión y foto (ticket opcional), liberación explícita del auxiliar con aviso a Ventas. Ocultar conceptos de envío sin aplicación y mantener SAF con checkbox GELIA. Las reglas y límites de comprobación están en [la referencia vigente de Tienda](gestion-pedidos-docs/TIENDA_DECISIONES_VIGENTES.md); las capturas aisladas están en [T-01](resources/design-references/tienda-t01/).

Referencias de Configuración operativa (4 de noviembre): [escritorio claro](resources/design-references/configuracion-operativa/escritorio-light.png) y [móvil oscuro](resources/design-references/configuracion-operativa/movil-dark.png), además de sus variantes de tema. Render aislado del build publicado con datos ficticios; no acredita sesión autenticada ni recorrido de consulta/pago. La composición reutiliza Configuración del sistema, Plazos y primitivas GELIA.

Decisión posterior del 4 de noviembre para respuesta y ajustes: priorizar etiquetas breves, evitar explicaciones automáticas duplicadas y no combinar Sin existencias con atributos de una pieza disponible. Agregar y Retirar conservan catálogo y documentos. Los adjuntos reutilizan miniatura y visor compartidos, con instrucciones compactas. Referencias aisladas del build: [respuesta y agregado móvil](resources/design-references/tienda-ajustes/movil-light.png) y [visor de evidencia](resources/design-references/tienda-ajustes/visor-escritorio-dark.png). No representan un recorrido autenticado; el alcance queda en las decisiones vigentes de Tienda.

Pago al recoger, ajuste posterior del 4 de noviembre: dentro del panel Pago, después de modalidad guardada y vigencia, mostrar «Confirmación del pedido» con «Enviar confirmación a tienda» o badge «Enviada a tienda». Mantener la separación entre selección, confirmación de Ventas y apartado físico del auxiliar. [Referencia móvil](resources/design-references/tienda-confirmacion/modalidad-movil-light.png), con respuestas ficticias y reloj fijado para la revisión aislada; no acredita sesión operativa.

Consulta de disponibilidad CEDIS, continuación de T-02: reutilizar el espacio de Preparación en tienda (contexto, captura/documento en paralelo, pie de acciones y galería). Estado excluyente del conjunto; capturar solo excepciones, con búsqueda/escaneo y alternativa sin catálogo. Lectura breve, separada de la preparación física. [Escritorio](resources/design-references/cedis-disponibilidad/captura-escritorio-dark.png) y [móvil](resources/design-references/cedis-disponibilidad/captura-movil-light.png) son referencias aisladas del build con datos ficticios. Alcance en TIENDA_T02_IMPLEMENTACION.md; no acreditan recorrido autenticado.

Referencia del 5 de noviembre: [Traslado entre sucursales](gestion-pedidos-docs/TIENDA_T03_IMPLEMENTACION.md), con [solicitud en escritorio](resources/design-references/tienda-traslado-sucursal-2026-11-05/solicitud-escritorio-dark.png) y [recepción móvil](resources/design-references/tienda-traslado-sucursal-2026-11-05/recepcion-movil-light.png). Extiende encabezados GELIA, panel de Registrar pedidos, galería y controles de tema; los wireframes orientan la tarea sin fijar fuente/acento. Capturas aisladas con datos ficticios, sin sesión autenticada.

Revisión del 5 de noviembre para disponibilidad/MD: acciones rápidas dentro de la pieza, sin restaurar un panel independiente de decisión. «Modificar pedido» despliega el ajuste detallado; cambios enviados visibles aunque esté plegado. Los botones secundarios operativos usan `GELIA_BTN_OUTLINE` con iconos. Priorizar el pendiente actual en MD y plegar referencia/historial. La alerta de consulta cerrada con faltantes usa rojo con contraste claro/oscuro y la reapertura en el mismo modal. En móvil, resultado legible sin repartir una palabra entre columnas. Véanse [implementación](gestion-pedidos-docs/DISPONIBILIDAD_ACCIONES_RAPIDAS.md) y [referencias](resources/design-references/tienda-disponibilidad-acciones-2026-11-05/).

Ajuste del 6 de noviembre de 2026: el selector inicial de Tienda se denomina **Sucursal de destino**. Ventas elige dónde llegará el resguardo; CEDIS conserva su función de almacén de origen. El campo persiste como `sucursal_destino_id`, sin cambiar consulta, traslado ni recepción.

Revisión del 6 de noviembre para disponibilidad CEDIS: el pedido de Ventas se abre desde **Ver pedido** al inicio en el visor ampliado; sustituye el documento desplegado y «Volver a la revisión» en esta consulta. Contexto con badges e iconos **Origen → Destino**, folio identificado, paneles opacos del tema y alerta ámbar. Fotos por unidad separadas de **Captura del pedido**, con el recuadro de cámara como disparador y hasta cinco fotos. Las filas de catálogo se presentan contraídas con etiquetas y retiro rápido; cada lectura registra una unidad independiente. Un solo campo busca y escanea. Única pieza, Mejor ejemplar y Sin existencias son interruptores apagados por defecto; Sin existencias excluye atributos, detalle y foto. Reutilizar escáner de cámara, galería y visor existentes. [Evidencia y alcance](gestion-pedidos-docs/TIENDA_T02_IMPLEMENTACION.md#captura-de-disponibilidad-cedis--6-de-noviembre-de-2026).

Corrección posterior del 6 de noviembre: el desplegable de `SelectorProducto` usa `theme-surface-solid theme-no-blur` para conservar opacidad con cristal activado. Verificar también `html.glass-active`: un fondo opaco con el tema base no acredita su resultado personalizado. [Comprobación con cristal](resources/design-references/cedis-catalogo-cristal-2026-11-06/verificacion.json).

Validación posterior del 6 de noviembre en CEDIS: Única pieza y/o Mejor ejemplar muestran **Observaciones (opcional)** y admiten envío sin texto, conservando foto y atributos. Un defecto sin esos atributos exige descripción; Sin existencias omite descripción escrita y evidencia individual. La cantidad es interna por unidad: no mostrar ni reclamar su recaptura. Véase la comprobación en TIENDA_T02_IMPLEMENTACION.md.

Revisión posterior del 6 de noviembre — respuestas recibidas y ajustes: adaptar la estructura del concepto aportado por la codesarrolladora usando superficies sólidas, iconos, estados y acento configurado de GELIA. **Respuesta de disponibilidad** comparte cabecera de responsable/origen, productos con estado y atributos, miniatura/visor, acciones por unidad y resumen con captura del pedido. Tabla en ancho suficiente y tarjetas compactas según el contenedor real, incluido el modal de 544 px. **Modificar pedido** abre opciones de tipo de ajuste e identificación, con captura contigua en escritorio y apilada en estrecho. El historial queda fuera. Los menús de acciones deben abrirse dentro del área visible del modal, conservar teclado/Escape y excluir Confirmar estado en faltantes.

El panel **Destino del pedido** muestra badges **Origen → Destino** únicamente para consulta CEDIS; cambiar el destino despliega su selector, sin repetirlo permanentemente. Tienda conserva la confirmación de su cobertura cuando corresponde y no muestra esa ruta. Mantener la distinción entre consulta, decisiones, confirmación, pago y movimiento físico. [Referencia comprobada del modal completo](resources/design-references/tienda-respuestas-2026-11-06/) y alcance en TIENDA_T02_IMPLEMENTACION.md.

## Mensajería — revisión del 6 de noviembre de 2026

La petición de modernización conserva el tema, fuente y acento configurados. Diferenciar la lista, encabezados, conversación y compositor con superficies opacas, una base suavemente teñida y selección clara; usar avatares con iniciales, herramientas con iconos y estados vacíos útiles. Los archivos se adaptan al ancho real del área de conversación y los stickers conservan accesible el compositor. Evidencia y límites de comprobación: [mensajería](resources/design-references/mensajeria-2026-11-06/README.md), con datos ficticios.
