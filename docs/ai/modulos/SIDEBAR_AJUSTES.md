# Sidebar y Ajustes

- [verificado] Sidebar por defecto: profesional (`professional_left`). Componentes en `resources/js/Components/Sidebar/`, CSS en `resources/css/gelia/features/sidebar-professional.css`. Los estilos flotante/fijo siguen en `Components/Sidebar.jsx` (legacy).
- [verificado] El árbol vive en `resources/js/config/sidebarNavigation.js`: secciones con encabezado (`seccion_*`) → grupos raíz → enlaces. Un encabezado solo aparece si el usuario ve algún grupo debajo.
- [verificado] Secciones: Principal (Inicio) · Operación (Comercial, Logística, Punto de venta) · Finanzas y reportes (Finanzas, Reportes) · Inventario y catálogo (Almacenes, Vinculaciones) · Organización (Gestión Interna) · Sistema (Herramientas, Soporte, Sistema).
- [verificado] Ubicación de módulos: Traspasos y Traspasos CEDIS en Logística; Cambio de Lista y Tags, Cancelación y cotización y Plantilla Pedidos en Comercial; Ejercicio Escalonamiento en Finanzas; Listados en Reportes; Asistencia en Gestión Interna; Avisos Mercancía en Almacenes; Productos solo en Almacenes.
- [verificado] Orden de hijos alfabético salvo `inicio`, `logistica`, `almacenes` y `gestion_pedidos` (orden de trabajo, `GRUPOS_ORDEN_EXPLICITO`).
- [verificado] Mapa Logístico y el panel de Soporte viven en su sección y no se repiten en Sistema (mismo permiso).
- [verificado] Rail contraído: tooltips en portal (`SidebarTipLayer`), no con CSS (el aside recorta con `overflow` y `contain`). Los grupos plegados son `inert`; el cajón móvil cerrado es `visibility: hidden`.
- [verificado] Contraer/expandir: el icono de cada grupo raíz queda en el mismo punto en ambos estados (centro del rail); solo se anima el ancho del aside y el texto se desvanece. El contenido principal no anima su padding (era la causa del lag).
- [verificado] Flyout del rail: su alto sigue a las opciones y solo lo limita la pantalla; la cabecera lleva icono y nombre de la sección.
- [verificado] Menú de usuario: Mi perfil, Ajustes, Novedades, Tema, Cerrar sesión. Se mantiene montado `USER_MENU_EXIT_MS` para animar su salida.

## Ajustes (modal)

- [verificado] Ya no existe la página de preferencias. `Components/Ajustes/AjustesModal.jsx` se monta en `AppLayout` y se abre con `abrirAjustes(tab)` (evento `gelia-ajustes-open`) o con `?ajustes=<apariencia|alertas|navegacion>` en la URL.
- [verificado] Puntos de entrada: botón de engranaje en la tira de utilidades del sidebar, «Ajustes» en el menú de usuario, menú de cuenta de los sidebars legacy y «Más opciones» de Mi perfil.
- [verificado] `GET /perfil/preferencias` (`profile.preferencias`) se conserva y redirige al panel principal con `?ajustes=apariencia`.
- [verificado] Pestañas: Apariencia, Alertas, Navegación. Todo cambio se previsualiza al instante (localStorage + `:root`); «Guardar cambios» hace `POST profile.update` por axios (respuesta JSON, sin redirección ni recarga) con `tema_visual` y reenvía nombre y apellido del usuario (el endpoint los exige). El botón pasa a verde con doble palomita.
- [verificado] Cerrar con cambios sin guardar pide confirmación (seguir editando, descartar o guardar y cerrar); sin cambios cierra con animación. Descartar restaura localStorage, el documento y las preferencias de alertas con las que se abrió.
- [verificado] Foto de perfil, datos personales y contraseña quedan en Mi perfil, no en Ajustes.
- [por verificar] Revisión visual a 1440, 1024 y 390 px en claro y oscuro, y recorrido de guardado con cuenta real: no se ejecutó (sin PHP ni build en el entorno de la sesión).
- Última verificación: 2026-11-26, sin commit, árbol de trabajo; pruebas vitest de sidebar, navegación y Ajustes.
- [verificado] Abarcamiento «Completo»/personalizado en el panel principal: cada panel guardado lleva `rw` (ancho en px con el que se diseñó; sin `rw` se asume 1300). `reflowLayoutForWidth` reacomoda en vista al ensanchar o estrechar. Al entrar a editar, el layout se convierte al ancho actual y se sella: lo editado es lo guardado. El bloque analítico (KPIs y gráficas) usa cuadrículas fluidas por container query (`dashboard.css`). Otras páginas con columnas fijas siguen estirándose (por migrar).
