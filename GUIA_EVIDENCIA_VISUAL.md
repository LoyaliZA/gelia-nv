# Evidencia visual de GELIA

Aplica a todo GELIA-NV. Se detiene solo si se pide un rediseño. Cómo ejecutar Sail y el build está en [AGENTS.md](AGENTS.md). La dirección de interfaz sigue en [GUIA_DISENO_GELIA.md](GUIA_DISENO_GELIA.md).

Conservar los componentes, el CSS, los tokens y los temas claro y oscuro que ya usa la pantalla. Conservar fuente, colores, medidas, iconos y animaciones. No añadir estilos globales que alcancen otros módulos, no sustituir la interfaz por HTML genérico y no cambiar lo visual que no forme parte de la corrección.

Una prueba HTML aislada puede aislar un fallo técnico y debe decir que es simplificada. No es una captura de la aplicación y no basta para dar por válida la interfaz.

La evidencia de un cambio de frontend es la aplicación real: Laravel aislado, React compilado con Vite, CSS de GELIA, datos ficticios y usuarios de prueba. Comprobar la interacción, no solo una imagen: abrir y cerrar, scroll, formularios, errores, carga, datos actualizados y captura sin guardar. Anchos 1440, 1024 y 390. Tema claro y oscuro cuando el cambio los toque. Revisar la consola.

Si el entorno no puede ejecutar GELIA, no se declara la validación completa. Se dice qué falta —dependencias, base de datos, servidor, build, autenticación, datos o navegador— y queda el procedimiento para repetirlo donde sí corra.
