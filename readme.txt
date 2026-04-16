=== TIC Suite · Gráficos ===
Contributors: gobernaciondenarino
Tags: charts, d3plus, shortcode, dataviz, tic-suite
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.4.3
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

= 1.4.3 =
* Leyenda fijada siempre al borde inferior: `viz.legendPosition("bottom")` se llama en el base config del renderer para que todos los gráficos (bar, line, pie, donut, treemap, geomap, etc.) muestren la leyenda debajo del SVG, independiente del aspect ratio del contenedor. El modo "iconos" ya estaba debajo por orden del DOM.

= 1.4.2 =
* Cobertura completa de los 12 proyectos TIC del Informe Gestión 2024. Vistas nuevas: Índice de Presencia, Conectividad (Hogares), Despliegue 5G, Escuelas Potencia 5G, Juntas (Pre-reg), Juntas (Diseño/Impl.), SENATIC (I.E.), Colombia Programa, AvanzaTEC.
* `vista-comparativo-municipios.json` ahora incluye los 11 programas en formato wide (64 filas × 11 medidas), útil para barras apiladas y comparaciones multiproyecto.
* Todas las vistas llevan los 64 municipios de Nariño + el flag booleano `pdet`.
* Script `scripts/regen-views.py` extendido con 10 funciones `regen_*()` (una por programa + índice + comparativo).

= 1.4.1 =
* Vistas regeneradas sin filtro "Top N": las 4 vistas que mostraban recortes (Top 12/15/16/20) ahora incluyen los **64 municipios** de Nariño extraídos del archivo maestro `vista-analisis-municipios.json`.
* Títulos actualizados para reflejar el alcance departamental completo.
* Nuevo script `scripts/regen-views.py` para regenerar las 4 vistas derivadas a partir del maestro — útil si el maestro cambia y hay que sincronizar.
* `zcp_por_municipio` ahora incluye los 64 municipios con el flag `pdet` booleano (los municipios no-PDET tienen `zcp_asignadas=0`).

= 1.4.0 =
* Tooltips: cada gráfico ahora muestra un tbody con todas las dimensiones y medidas en español, valores formateados con `Intl.NumberFormat('es-CO')` (separadores de miles correctos), porcentajes con 1 decimal y unidades detectadas automáticamente del nombre del campo (`(Millones COP)`, `(%)`).
* Títulos de ejes: nuevos atributos `x_title` y `y_title` en el shortcode. Si se omiten, el renderer infiere el título a partir del nombre del campo y le añade la unidad apropiada. Para charts apilados el título Y muestra "Cantidad" o "Valor (Millones COP)" según las medidas que se estén apilando.
* Charts sin ejes (pie/donut/treemap/geomap/network/rings/sankey/tree) se saltan automáticamente la configuración de ejes.
* Geomap: el tooltip muestra el nombre original del municipio con acentos (`SAN ANDRÉS DE TUMACO`) en vez del id normalizado.
* Stacked bar/area: el tooltip combina nombre del municipio + métrica + valor.
* Builder admin: dos inputs nuevos en el fieldset "Opciones" — Eje X / Eje Y, con placeholder "auto" y actualización en vivo de la preview y el shortcode generado.

= 1.3.0 =
* Topojson regenerado con detalle completo: toposimplify desactivado, solo quantización. El mapa ahora conserva todas las delimitaciones originales (49 KB, 188 arcs únicos, 349 referencias compartidas entre municipios vecinos).
* Nuevas opciones de shortcode: `legend` (true/false), `legend_style` (text/icons), `toolbar` (true/false), `actions` (lista separada por comas con detalle, compartir, datos, imagen, descarga).
* Modo "iconos" para la leyenda: tira de swatches de color sin texto, con tooltip mostrando la etiqueta.
* Barra de acciones sobre el gráfico con 5 botones:
  - Detalle: modal con metadatos (tipo, categoría, dimensiones, medidas, filas).
  - Compartir: usa `navigator.share` si existe, sino copia la URL del gráfico al portapapeles.
  - Datos: modal con tabla completa de los datos.
  - Imagen: exporta el SVG a PNG en 2x (retina) usando canvas.
  - Descarga: descarga el payload (vista + datos) como JSON.
* Admin builder: nuevo fieldset "Opciones" con switches y chips para configurar todas las opciones anteriores. Los cambios se reflejan en vivo tanto en la vista previa como en el shortcode generado.
* La preview del admin ahora usa el mismo CSS que el frontend para mostrar una réplica exacta del resultado final.

= 1.2.0 =
* Vistas: el data provider ahora acepta dos formatos — el nativo del plugin (id/name/category/dimensions/measures/data) y el formato de publicación TIC Suite (vista/titulo/descripcion/tipo_grafico_sugerido/municipios|datos). Dimensiones, medidas y categoría se infieren automáticamente de la primera fila.
* Compatibilidad de gráficos ampliada: las vistas geográficas ahora son compatibles con bar, stacked_bar, pie, donut, treemap, tree, box_whisker y geomap (antes solo geomap).
* Mapa coroplético: el renderer normaliza nombres de municipios en el cliente (NFD + quitar acentos + upper) para unirlos contra el id del topojson — así "SAN ANDRÉS DE TUMACO" se une a "SAN ANDRES DE TUMACO" sin configurar nada extra.
* Topojson local: `data/topo/narino_municipios.topojson` (14 KB, generado desde el geojson de 354 KB con simplificación geométrica), con 64 municipios de Nariño y propiedades limpias (id, nombre, divipola). Servido via `plugins_url()`.
* Reshape wide→long automático para `stacked_bar` y `stacked_area`: series derivadas (total, pct_, participacion, cobertura) se omiten para evitar doble conteo.

= 1.1.1 =
* Cambio crítico de CDN: `@d3plus/core@3.1.4/umd/d3plus-core.js` (que requería 30+ peer deps en window y fallaba en silencio) → `@d3plus/core@3.1.4/umd/d3plus-core.full.js` (bundle standalone de 2 MB que embebe todas las dependencias).
* Ahora sí se expone `window.d3plus.BarChart`, `.LinePlot`, `.Pie`, `.Donut`, `.Treemap`, `.Geomap`, `.Network`, `.Tree`, `.Sankey`, `.Rings`, `.BoxWhisker`, `.Priestley`, `.StackedArea`, `.AreaPlot` — las 15 clases que usa el plugin.
* Verificado contra el UMD descargado y contra el campo `browser` del package.json del paquete.

= 1.1.0 =
* Migrado a @d3plus/core v3.1.4 (antes apuntaba a un CDN legacy inexistente).
* Ahora los gráficos renderizan correctamente tanto en el admin como en el shortcode.
* Cargador defensivo `waitForD3plus()` con timeout; mensaje de error útil si el script no llega.
* Responsive: `detectResize(true)` + `ResizeObserver` del contenedor; SVG reescala en ventana y en cambios de layout.
* `stacked_bar` usa ahora `BarChart` con `.stacked(true)` (no hay clase `StackedBarChart` en v3).
* CSS: contenedores con altura explícita para que d3plus pueda medir el parent.

= 1.0.0 =
* Versión inicial.
