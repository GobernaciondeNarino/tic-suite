=== TIC Suite · Gráficos ===
Contributors: gobernaciondenarino
Tags: charts, d3plus, shortcode, dataviz, tic-suite
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin profesional para generar 15 tipos de gráficos interactivos con d3plus.js e insertarlos en cualquier página de TIC Suite mediante shortcode.

== Description ==

TIC Suite · Gráficos convierte cualquier vista JSON registrada en un gráfico interactivo. Incluye:

* 15 tipos de visualizaciones basadas en d3plus.js (barras, líneas, área, pastel, dona, treemap, mapa coroplético, red, árbol, sankey, anillos, box plot, Priestley, apiladas).
* Detección automática de compatibilidad: cada vista muestra solo los gráficos adecuados para su estructura.
* Constructor visual en el admin con vista previa en vivo y generación de shortcode en un clic.
* Galería de shortcodes con copia al portapapeles y previsualización de los datos de cada vista.
* Shortcode `[tsg_grafico view="..." type="..." height="..." title="..." theme="..."]`.
* REST API interna con nonces, permisos y sanitización estricta.
* Experiencia minimalista con iconos dashicons, tanto en el admin como en el frontend.

== Installation ==

1. Copia el plugin a `wp-content/plugins/tic-suite-graficos/`.
2. Activa "TIC Suite · Gráficos" desde el listado de plugins.
3. Abre el menú "Gráficos" en el admin para construir tu primer gráfico.
4. Pega el shortcode generado en cualquier entrada o página.

== Seguridad ==

* Todas las rutas administrativas requieren la capacidad `manage_options`.
* Las rutas REST validan nonces (`X-WP-Nonce`).
* Los identificadores de vista y tipo se saniten contra whitelists.
* El shortcode nunca vuelca JSON en el HTML: los datos se piden a la API tras la carga.
* `uninstall.php` limpia las opciones al desinstalar.

== Changelog ==

= 1.1.0 =
* Migrado a @d3plus/core v3.1.4 (antes apuntaba a un CDN legacy inexistente).
* Ahora los gráficos renderizan correctamente tanto en el admin como en el shortcode.
* Cargador defensivo `waitForD3plus()` con timeout; mensaje de error útil si el script no llega.
* Responsive: `detectResize(true)` + `ResizeObserver` del contenedor; SVG reescala en ventana y en cambios de layout.
* `stacked_bar` usa ahora `BarChart` con `.stacked(true)` (no hay clase `StackedBarChart` en v3).
* CSS: contenedores con altura explícita para que d3plus pueda medir el parent.

= 1.0.0 =
* Versión inicial.
