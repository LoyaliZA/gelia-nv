---
name: visual-refresh
description: Coordinar una renovación visual de una vista o módulo existente con análisis, detección de estilos globales, prototipo y posterior implementación. Usar para modernizar GELIA u otro proyecto hacia SaaS/ERP o ecommerce y aprovechar skills de diseño instaladas. Para auditoría aislada usar visual-discovery; para aplicar un diseño elegido usar visual-implementation.
---

# Renovar experiencia visual

Dirigir el proceso como un flujo de diseño con evidencia. Una skill no es una llamada a una función: localizar y leer sus instrucciones y ejecutar sus pasos con las herramientas disponibles.

## Resolver el punto de entrada

1. Identificar vista, ruta, proyecto y objetivo por archivos y contexto. Si existen dos módulos plausibles sin evidencia suficiente, preguntar solo por el objetivo; continuar inspeccionando estilos mientras tanto.
2. Leer instrucciones locales aplicables y cambios existentes. No sobrescribir trabajo ajeno.
3. Clasificar por tarea: operación interna → SaaS/ERP; compra pública → ecommerce; administración de una tienda → normalmente SaaS/ERP. Separar superficies híbridas; no aplicar patrones de catálogo a una cola operativa.
4. Localizar las skills de la suite por catálogo o por `name` del frontmatter. En Cursor revisar `.cursor/skills`, `.agents/skills`, `.claude/skills`, `.codex/skills`, equivalentes personales y plugins expuestos. Leer `visual-discovery` y `visual-implementation` según la etapa. No depender del nombre físico de carpeta en entornos que lo reescriban.
5. Si falta una skill, buscar su instalación real; si sigue ausente, informar y usar [workflow.md](references/workflow.md), sin afirmar que se utilizó.

## Dirigir las etapas

Leer [workflow.md](references/workflow.md) para entregables y continuidad.

- **Análisis o exploración:** ejecutar `visual-discovery`; entregar diagnóstico y propuesta visual sin editar vistas activas.
- **Modernización genérica:** hacer descubrimiento y prototipo; presentar un resultado concreto para elegir dirección. No interpretar “me gusta el análisis” como elección de un diseño todavía no mostrado.
- **Diseño elegido e implementación autorizada:** ejecutar `visual-implementation`, reutilizando el análisis. No pedir otra confirmación si el alcance está autorizado en la sesión.
- **Trabajo de principio a fin con implementación expresamente autorizada:** hacer descubrimiento, elegir una dirección fundamentada y continuar dentro del alcance. No imponer una aprobación intermedia que el usuario haya omitido expresamente.
- **Revisión o ajustes locales:** revisar solo las decisiones que pudieron cambiar; no reiniciar toda la exploración.

## Coordinar recursos

Hacer que la etapa activa descubra y lea `frontend-design`, `ui-ux-pro-max`, capacidades pertinentes de Impeccable y `web-design-guidelines` cuando estén disponibles. Un plugin puede exponer varias skills y comandos sin una skill llamada `impeccable`.

En una renovación, leer del conjunto de movimiento solo las skills del caso: `emil-design-eng` para decidir si animar, `find-animation-opportunities` para buscar huecos, `animate` para implementar en web (`animate-expo` solo en React Native o Expo), `review-animations` si ya hay movimiento, `animation-performance` si se traba, `apple-design` en gestos o profundidad, `mobile-native` en viewport chico y `break-ui` si hay datos variables. No cargar el paquete entero en una corrección pequeña. Esa corrección sigue la regla de UX del proyecto y no reinicia este flujo ni `improve-animations` de todo el repositorio.

Registrar nombre, ruta/recurso leído, operación efectuada y resultado. Distinguir lectura de scripts ejecutados y revisión manual de auditoría ejecutada. No instalar recursos, activar hooks ni usar servicios de pago por defecto.

Entregar vista confirmada, etapa terminada, evidencia visual, limitaciones reales y siguiente acción. No asegurar que una skill o regla se ejecutará siempre: son instrucciones para el agente, no un motor determinista.
