# Guía de diseño de GELIA

La identidad visual, sus reglas y la forma de trabajar están en [DESIGN.md](DESIGN.md). Esta guía solo lista dónde mirar.

## Referencias en el código

| Referencia | Qué observar | Archivo |
| --- | --- | --- |
| **Listados (principal)** | Color e icono por elemento, etiquetas, acciones con icono, tarjetas | [Listados.jsx](resources/js/Pages/FuncionesOperativas/Listados.jsx) |
| Operación general de Punto de venta | Jerarquía operativa, agrupación y estados | [OperacionGeneral.jsx](resources/js/Pages/PuntoVenta/Operacion/OperacionGeneral.jsx) |
| Solicitudes de tag y facturas | Captura, cliente seleccionado, documentos | [ModalFormSolicitud.jsx](resources/js/Pages/Solicitudes/Partials/ModalFormSolicitud.jsx), [ModalFormFactura.jsx](resources/js/Pages/Facturas/Partials/ModalFormFactura.jsx) |
| Cancelaciones y cotizaciones | Encabezado y campos con iconos | [ModalFormOperativa.jsx](resources/js/Pages/CancelacionesCotizaciones/Partials/ModalFormOperativa.jsx) |
| Resguardos | Contexto, estado y acciones de una tarea | [ModalRecepcionResguardo.jsx](resources/js/Pages/PuntoVenta/Resguardos/Partials/ModalRecepcionResguardo.jsx) |
| Registrar pedido — Tienda | Modal con datos, cliente, atención, documento y pie de acciones | [FormularioRegistrarTienda.jsx](resources/js/Pages/ControlPedidos/Partials/FormularioRegistrarTienda.jsx) |

Capturas de Registrar pedido (datos de ejemplo, 2 de noviembre de 2026): [escritorio oscuro](resources/design-references/registrar-pedido/escritorio-oscuro.png), [claro](resources/design-references/registrar-pedido/nuevo-claro.png), [móvil](resources/design-references/registrar-pedido/movil-datos.png). Comparar con el código actual antes de usarlas.

## Fuentes de estilo, en orden

1. [Tokens](resources/css/gelia/tokens.css) y la personalización de [AppLayout](resources/js/Layouts/AppLayout.jsx).
2. [Primitivas](resources/css/gelia/primitives.css) y [utilidades](resources/css/gelia/utilities.css).
3. [geliaTheme.js](resources/js/utils/geliaTheme.js) y `resources/js/Components/`.
4. Estilos del módulo y el orden de imports en [app.css](resources/css/app.css).

Evidencia de una interfaz: [GUIA_EVIDENCIA_VISUAL.md](GUIA_EVIDENCIA_VISUAL.md). Versión anterior de esta guía: [archivo de decisiones](gestion-pedidos-docs/ARCHIVO_DECISIONES_AGENTES.md).
