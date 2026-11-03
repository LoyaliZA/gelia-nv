---
name: visual-discovery
description: Analizar una vista existente y preparar propuestas o prototipos visuales SaaS/ERP o ecommerce antes de implementarlos. Usar para auditoría UI/UX, exploración visual, detección de tokens globales y componentes, o diseño de Index/Show en GELIA u otro proyecto aprovechando skills disponibles. No usar para desplegar ni aplicar un diseño ya elegido.
---

# Analizar y diseñar interfaz

## Reconstruir contexto

Leer [project-context.md](references/project-context.md). Confirmar vista por ruta, componentes y flujo, no solo por nombre de carpeta. Leer instrucciones locales, estilos y archivos del flujo.

Identificar usuario final, tarea, dispositivos y restricciones por evidencia. Clasificar según tarea, no la palabra “tienda”. Leer [profiles.md](references/profiles.md); separar superficies híbridas.

## Descubrir y usar skills

Buscar nombres y descripciones en catálogo, directorios de proyecto/usuario y plugins expuestos. Comparar `name` del frontmatter, no solo carpetas. Leer instrucciones reales antes de usar:
- `frontend-design`: composición, dirección estética y soluciones visuales distintivas.
- `ui-ux-pro-max`: investigación de patrones; ejecutar búsquedas/scripts según su documentación real.
- Impeccable: localizar las skills/comandos que exponga para crítica, auditoría, normalización o refinamiento. No asumir una skill única ni sintaxis universal.
- `web-design-guidelines`: seguir la fuente y procedimiento de revisión de la instalación vigente.

Si falta un recurso, registrar la ausencia y continuar con revisión propia y los disponibles. No instalar plugins, activar hooks ni contratar MCP por iniciativa propia. No cargar todas las referencias si no aplican. Registrar nombre, recurso leído, pasos ejecutados y aporte concreto; mencionar una skill no equivale a usarla. Evitar recursión si un recurso remite a esta suite. Resolver conflictos respetando al usuario, instrucciones aplicables y contratos del proyecto.

## Auditar y explorar

Reconstruir acciones, permisos, validación, estados y efectos. Revisar jerarquía, densidad, navegación, filtros, resultados, CTA, lectura, teclado, foco, mensajes, responsive y temas. Separar observaciones de navegador, evidencia del código e hipótesis. No afirmar que se vieron pantallas si solo se leyó JSX.

Proponer mejoras ligadas a tareas concretas; conservar reglas de negocio. Separar ideas que exijan backend nuevo. No convertir todas las tarjetas en tablas ni imponer una landing page a un módulo operativo.

En exploración, proponer dos direcciones visuales distintas y recomendar una. Si ya hay dirección elegida, desarrollarla sin forzar alternativas. Especificar tipografía, composición, densidad, superficies, iconos, estados, movimiento y móvil, mapeados a tokens/componentes reales.

## Crear una propuesta revisable

Crear `brief.md` y prototipo aislado en el área del proyecto correspondiente; si no existe, usar `docs/visual-refresh/<vista>/`. No modificar pantallas activas, rutas de producción ni tokens globales en esta etapa.

Usar HTML autónomo o sandbox existente sin modificar el bundle activo. Usar datos ficticios indicados. Reutilizar valores efectivos de tokens para reflejar identidad; documentar el puente si las clases no cargan fuera de la app. No introducir una paleta paralela en producción.

Representar principal y detalle si ambos están en alcance; escritorio y móvil; controles navegables y estados críticos. Al explorar, visualizar una pantalla clave de cada dirección y desarrollar la recomendada. No dejar controles aparentemente interactivos sin comportamiento o aviso de simulación.

Ejecutar preview con herramientas disponibles. Revisar tamaños y guardar evidencia visual. Si no puede abrirse, entregar prototipo e instrucciones y declarar la limitación concreta; no quedarse solo en un documento técnico ni afirmar verificación que no ocurrió.

## Entregar continuidad

Entregar brief, prototipo, comparación/recomendación, registro de skills y comprobaciones. Dejar contrato funcional y de estilos para `visual-implementation`.

Si se pidió exploración, presentar resultado y elección pendiente. Si la implementación de una dirección está autorizada, pasar a esa etapa sin confirmaciones repetidas.
