<p align="center">
  <img src="./public/favicon-192.png" width="88" height="88" alt="Isotipo de GELIA">
</p>

<h1 align="center">GELIA · NV</h1>

<p align="center">
  <strong>Una plataforma para coordinar la operación diaria.</strong><br>
  Clientes, pedidos, entregas y procesos internos en un mismo lugar.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Uso-interno-EA580C?style=flat-square" alt="Sistema de uso interno">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/React-19-149ECA?style=flat-square&logo=react&logoColor=white" alt="React 19">
  <img src="https://img.shields.io/badge/Inertia-3-9553E9?style=flat-square" alt="Inertia 3">
  <img src="https://img.shields.io/badge/Tailwind_CSS-4-0F766E?style=flat-square" alt="Tailwind CSS 4">
</p>

<p align="center">
  <a href="#qué-es-gelia">Acerca de GELIA</a> ·
  <a href="#módulos">Módulos</a> ·
  <a href="#novedades">Novedades</a> ·
  <a href="#desarrollo">Desarrollo</a> ·
  <a href="#documentación">Documentación</a>
</p>

---

## Qué es GELIA

**GELIA-NV** es una aplicación de uso interno que reúne herramientas para la operación comercial, logística y administrativa de Bellaroma. Ayuda a las áreas a consultar información, atender solicitudes y dar seguimiento a sus procesos desde una plataforma compartida.

Su enfoque es facilitar el trabajo diario: gestionar clientes, coordinar pedidos entre áreas, registrar entregas y resguardos, y mantener el seguimiento de las actividades operativas.

Este repositorio contiene la **aplicación web y su backend**, incluida la API utilizada por el módulo móvil. La aplicación móvil se mantiene por separado en [gelia-nv-mobile](https://github.com/LoyaliZA/gelia-nv-mobile).

## Módulos

| Área | Funcionalidades |
| --- | --- |
| **Clientes y comercial** | Consulta y gestión de clientes, visitas programadas, solicitudes y escalonamiento. |
| **Pedidos y logística** | Gestión de pedidos, revisión, CEDIS, preparación en tienda, seguimiento por delegado y direcciones de envío. |
| **Entregas** | Seguimiento de entregas y funciones operativas relacionadas. |
| **Punto de venta** | Turnos, visitas programadas, resguardos, recepción, entregas y reportes. |
| **Almacenes y activos** | Gestión de almacenes, traspasos y activos. |
| **Administración y finanzas** | Solicitudes de facturación, cobranza, contabilidad, saldos a favor y reportes. |
| **Gestión interna y RH** | Procesos internos y herramientas de recursos humanos. |
| **Comunicación** | Mensajería interna, historial de notificaciones y alertas. |
| **Integraciones y medios** | Herramientas para WooCommerce, Tiendanube y gestión de medios. |
| **Administración del sistema** | Usuarios, roles, permisos, catálogos, auditorías y personalización. |

La disponibilidad de cada módulo depende de los permisos de la cuenta y de la configuración del entorno.

## Características

- **Acceso por roles y permisos:** funciones distribuidas según las responsabilidades de cada usuario.
- **Personalización visual:** sistema de temas y estilos compartidos entre módulos.
- **Comunicación en tiempo real:** Laravel Reverb y Echo para eventos y notificaciones.
- **Notificaciones Web Push:** alertas mediante Service Worker, sujetas a configuración y permisos del navegador.
- **API versionada:** rutas bajo `/api/v1` para integraciones y acceso móvil.
- **Soporte de sincronización móvil en el backend:** endpoints de descarga inicial y consulta de cambios.
- **Documentos y evidencias:** herramientas para PDFs, códigos QR, archivos y firmas en los flujos que los requieren.

## Novedades

### Cambios recientes en el repositorio · 12 de octubre de 2026

| Módulo | Actualización | Referencia |
| --- | --- | --- |
| **Resguardos** | Nuevas validaciones para el estado físico de las piezas y el manejo de evidencias. | [Ver cambio](https://github.com/LoyaliZA/gelia-nv/commit/8851523fc99cc4b2a12fa1530f19b3caeee2af83) |
| **Solicitudes** | Validación del tipo de cliente durante la creación y actualización de solicitudes. | [Ver cambio](https://github.com/LoyaliZA/gelia-nv/commit/e826cbb7654e43b867510e853e69b5f5b1bee537) |
| **Listas de descuento** | Configuración de las listas que se muestran en solicitudes. | [Ver cambio](https://github.com/LoyaliZA/gelia-nv/commit/8315ee21a573af76f43ed599faa965233f0e4b54) |
| **Facturación** | Configuración de bancos habilitados para facturación y motivos de bloqueo. | [Ver cambio](https://github.com/LoyaliZA/gelia-nv/commit/13abfa8cc4101175a1aa1311376c45044ff9189e) |
| **Almacenes** | Configuración para permitir la salida de mercancía en pedidos. | [Ver cambio](https://github.com/LoyaliZA/gelia-nv/commit/ab77dcd59b1f682a64c90396570d2c690547cce4) |

Este resumen describe cambios incorporados al código; su disponibilidad en el sistema depende del despliegue correspondiente.

[Consultar el historial completo de cambios](https://github.com/LoyaliZA/gelia-nv/commits/main/)


Formato sugerido para el historial:

```markdown
## [Versión o fecha] - AAAA-MM-DD

### Agregado
- 

### Mejorado
- 

### Corregido
- 
```


</details>

## Tecnologías

| Capa | Tecnologías |
| --- | --- |
| **Backend** | PHP y Laravel 13 |
| **Interfaz web** | React 19 e Inertia.js 3 |
| **Estilos y compilación** | Tailwind CSS 4 y Vite 8 |
| **Autenticación y permisos** | Laravel Sanctum, Spatie Laravel Permission y WebAuthn |
| **Tiempo real y tareas** | Laravel Reverb, Echo y colas de Laravel |
| **Servicios del entorno Docker** | MySQL y Redis |
| **Documentos y almacenamiento** | Dompdf, FastExcel y adaptador de almacenamiento compatible con S3 |
| **Pruebas** | PHPUnit y Vitest |

Las restricciones de dependencias están en [composer.json](composer.json) y [package.json](package.json); las versiones resueltas se registran en sus archivos de bloqueo.

## Desarrollo

<details>
<summary><strong>Requisitos y preparación del entorno</strong></summary>

### Requisitos

- Git y Composer 2.
- PHP compatible con las dependencias bloqueadas. El entorno Docker del proyecto utiliza **PHP 8.4**.
- **Node.js 24** para alinearse con el Dockerfile del proyecto, y npm.
- Base de datos y servicios configurados para el entorno local.
- Docker con Compose si se utiliza el entorno de contenedores.

### Preparación

1. Clonar el repositorio:

   ```bash
   git clone https://github.com/LoyaliZA/gelia-nv.git
   cd gelia-nv
   ```

2. Preparar un archivo `.env` válido para desarrollo con la configuración del entorno: aplicación, base de datos, sesiones, caché, colas y servicios requeridos.

   **Nota sobre la plantilla actual:** `.env.example` contiene notas y bloques Markdown sobre WebAuthn y R2; requiere adaptación y las variables generales de la aplicación antes de utilizarse como un `.env`. Los archivos de [config/](config/) definen las variables consumidas por el sistema.

3. Instalar dependencias:

   ```bash
   composer install
   npm ci
   ```

4. En una instalación local nueva, generar la clave y preparar la base de datos de desarrollo:

   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan storage:link
   ```

5. Revisar los seeders necesarios para inicializar roles, permisos y catálogos. `DatabaseSeeder` también ejecuta `DemoSeeder`; la carga de datos debe elegirse según el entorno.

6. Iniciar el servidor local y Vite:

   ```bash
   composer dev
   ```

   Este script inicia el servidor PHP, el consumidor de colas, los logs y Vite. Reverb se inicia por separado cuando se necesita tiempo real:

   ```bash
   php artisan reverb:start
   ```

Esta es una guía base de desarrollo. Las integraciones, notificaciones y otros servicios requieren su configuración adicional.

### Entorno con Docker

[compose.yaml](compose.yaml) define el entorno Sail y sus servicios, incluidos MySQL, Redis, Reverb, colas y scheduler. [Dockerfile](Dockerfile) y [docker-compose.prod.yaml](docker-compose.prod.yaml) contienen la configuración de contenedores destinada al entorno de producción.

</details>

<details>
<summary><strong>Comandos de trabajo y comprobación</strong></summary>

| Comando | Uso |
| --- | --- |
| `composer dev` | Iniciar el entorno de desarrollo con PHP local y Vite. |
| `npm run build` | Compilar los recursos del frontend mediante el script del proyecto. |
| `composer test` | Ejecutar las pruebas de PHP. |
| `npm test` | Ejecutar las pruebas del frontend con Vitest. |
| `npm run check:style` | Revisar las reglas de estilos del proyecto. |
| `npm run lint:style-css` | Revisar los estilos CSS de los módulos con Stylelint. |

Con Sail, ejecutar los comandos de PHP y npm dentro de los contenedores correspondientes.

</details>

<details>
<summary><strong>Dónde está cada parte del proyecto</strong></summary>

| Directorio | Contenido |
| --- | --- |
| `app/` | Lógica del backend, controladores, modelos y servicios. |
| `resources/js/` | Páginas y componentes de React. |
| `resources/css/gelia/` | Temas, componentes visuales y estilos por módulo. |
| `routes/web/` | Rutas web organizadas por área y módulo. |
| `routes/api.php` | API versionada e interfaces para el módulo móvil. |
| `database/` | Migraciones, seeders y factories. |
| `tests/` | Pruebas del backend. |
| `docs/` | Documentación técnica y operativa. |
| `scripts/` | Utilidades de mantenimiento y comprobación. |

</details>

## Documentación

- [Notificaciones Push Web](docs/PUSH_NOTIFICATIONS.md).
- [Documentación de control de pedidos](docs/control-pedidos/).
- [Checklist de interfaz y direcciones de envío](docs/control-pedidos/pase-ui-checklist.md).
- [Documentación de operaciones](docs/operaciones/).
- [Repositorio de la aplicación móvil](https://github.com/LoyaliZA/gelia-nv-mobile).

<!--
CAPTURAS: cuando existan imágenes seleccionadas, añadirlas bajo docs/images/
y enlazarlas aquí. Usar datos de demostración.
Mantener las etiquetas de tecnologías alineadas con las dependencias del proyecto.
-->

## Mantenimiento

Al modificar un módulo, actualizar su documentación cuando cambie el uso, la configuración o el flujo operativo. Registrar las novedades relevantes en la sección de cambios y ejecutar las comprobaciones que correspondan.

Para reportar una incidencia, indicar el módulo, los pasos para reproducirla, el resultado esperado y el resultado obtenido. Los problemas que incluyan información interna deben comunicarse por los canales autorizados de la organización.

## Licencia

Las condiciones de uso del código de GELIA deben definirse expresamente para este proyecto. El framework Laravel y las demás dependencias conservan sus respectivas licencias.

---

<p align="center">
  <strong>GELIA-NV</strong><br>
  Herramientas para conectar las áreas y dar seguimiento a la operación.
</p>
