# Reconstruir estilos y comportamiento

## Fuente de verdad

Leer instrucciones locales, manifiestos y scripts. Usar `rg --files` y búsquedas acotadas para localizar:
- CSS/SCSS global, imports, `:root`, temas, `@theme`, presets/configuración Tailwind según versión real.
- Tokens, constantes de clases, shells, botones, campos, tablas, badges, tipografía, iconos y movimiento.
- Reglas de diseño, marca, modo claro/oscuro, contraste, densidad.
- Vista, parciales, hooks, servicios y contratos; páginas vecinas como referencia sin ampliar alcance.

Seguir imports, especificidad y overrides. Un token encontrado pero no importado no es necesariamente efectivo. Comprobar estilos computados si hay navegador. Distinguir código confirmado de runtime confirmado.

Registrar función visual → token/clase/componente → fuente → valor/variante → uso propuesto. Cubrir superficies, texto, bordes, acento, semántica, tipografía, espaciado, radios, sombras, breakpoints, capas y movimiento utilizados.

Conservar tokens correctos y componentes útiles; mejorar jerarquía sin copiar defectos. Proponer extensiones semánticas centralizadas si faltan tokens. Cambiar variables globales solo si ese alcance está autorizado y se revisaron consumidores. No modificar paleta global para resolver una vista.

## Pistas GELIA, verificar

Buscar `estilos-gelia.mdc`, reglas aplicables, `geliaTheme`, `theme-*`, `GeliaPageShell`, `GeliaTituloCard`, `geliaCardClass`, `gelia-segment`, `GeliaPaginacion`, `GELIA_ESTADO_VIVO` y tonos. Son pistas conocidas, no garantía de existencia/versión. No inventar props, rutas ni colores.

Para Operación Tienda / Preparación Tienda, comprobar `resources/js/Pages/ControlPedidos/Tienda/`, Index, Show y parciales vigentes; leer bandejas, filtros, CTAs, permisos, polling, persistencia y estados. No confundir con gestión de inventarios.

En otros proyectos sustituir esas pistas por fuentes propias; evitar referencias GELIA en su código o informe.

## Contrato funcional

Mapear acción → condición/permiso → efecto/ruta → evidencia → aceptación. Cubrir validaciones, params, estado, persistencia, tiempo real, adjuntos e integraciones pertinentes.

No inventar métricas sin fuente o botones sin acción existente. No cambiar precios, descuentos, stock, permisos o flujo de pedido como consecuencia de un rediseño.
