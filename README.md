# TIC Suite · Gráficos

**v1.4.0** — Plugin profesional para **TIC Suite** (Gobernación de Nariño)
que permite generar **15 tipos de gráficos interactivos con
[@d3plus/core v3.1.4](https://d3plus.org/)** e insertarlos en cualquier
página o entrada mediante **shortcode**.

## Características

- **15 tipos de gráficos** (d3plus.js): barras, barras apiladas, línea, área, área apilada, pastel, dona, treemap, mapa coroplético, red, árbol, sankey, anillos, box-whisker, Priestley (línea temporal).
- **Detección de compatibilidad**: al seleccionar una vista, solo aparecen los tipos de gráficos coherentes con sus dimensiones, medidas, aristas o rango temporal.
- **Constructor visual** en tres paneles (Vista → Tipo → Vista previa + shortcode) con copia al portapapeles de un clic.
- **Galería de shortcodes**: bloque por vista con todas sus combinaciones posibles y preview tabular de los datos subyacentes.
- **Navegador de datos**: vista tabular cruda de cualquier vista registrada.
- **REST API propia** (`/wp-json/tic-suite/v1`) con permisos, nonces y whitelists.
- **Arquitectura segura**: sanitización profunda, escapado sistemático, path traversal protegido, permisos por capability, sin `eval` ni JSON inyectado en el HTML.
- **Experiencia minimalista** en admin y frontend con Dashicons y paleta contenida.

## Instalación

```bash
# Clonar en wp-content/plugins/
git clone https://github.com/GobernaciondeNarino/tic-suite.git tic-suite-graficos
```

Activa **TIC Suite · Gráficos** en Plugins → Instalados.

## Uso

### Desde el admin

1. **Gráficos → Constructor**: elige una vista a la izquierda; los tipos de gráfico compatibles aparecen en el centro; selecciónalos para ver la vista previa y obtener el shortcode.
2. **Gráficos → Shortcodes**: galería con todas las combinaciones listas para copiar, con una tabla de los datos de la vista debajo.
3. **Gráficos → Datos de vista**: navegador tabular de cualquier vista.

### Shortcode

```text
[tsg_grafico view="conectividad-municipios" type="bar" height="420" title="Conectividad por municipio" theme="tic-suite"]
```

Atributos:

| Atributo | Requerido | Descripción |
|----------|-----------|-------------|
| `view`   | sí        | Id de una vista en `/data/views/*.json` |
| `type`   | sí        | Uno de los 15 tipos registrados |
| `height` | no        | Altura mínima en píxeles (160–1600) |
| `title`  | no        | Encabezado del gráfico |
| `theme`  | no        | `tic-suite` (claro) o `dark` |

## Añadir una vista

Crea un archivo en `data/views/<slug>.json`:

```json
{
  "id": "mi-vista",
  "name": "Mi vista",
  "description": "Descripción corta.",
  "category": "categorical",
  "dimensions": ["campo_dim_1", "campo_dim_2"],
  "measures":   ["metrica_1"],
  "data": [
    { "campo_dim_1": "A", "campo_dim_2": "X", "metrica_1": 12 }
  ]
}
```

Categorías soportadas: `categorical`, `temporal`, `geographic`, `hierarchical`, `network`, `statistical`.

## Arquitectura

```
tic-suite-graficos.php        → Headers + bootstrap
includes/
  class-tsg-plugin.php        → Singleton que cablea todo
  class-tsg-security.php      → Sanitización, nonces, capabilities
  class-tsg-chart-types.php   → Registro de 15 tipos + compatibilidad
  class-tsg-data-provider.php → Lectura/caché de vistas JSON
  class-tsg-shortcode.php     → Shortcode [tsg_grafico]
  class-tsg-rest-api.php      → Rutas REST /tic-suite/v1
  class-tsg-admin.php         → Menús y pantallas de admin
templates/admin/              → Vistas del admin
assets/js/                    → admin.js, frontend.js, renderer.js
assets/css/                   → admin.css, frontend.css
data/views/*.json             → Vistas de ejemplo
```

## Seguridad

- Capability `manage_options` para todo acceso administrativo.
- Nonces `X-WP-Nonce` en cada llamada REST.
- `sanitize_key`, `sanitize_text_field`, `absint`, `sanitize_html_class` aplicados a todas las entradas.
- Whitelist estricta de `view` y `type` antes de cualquier consulta.
- Protección explícita contra path traversal al leer JSON (`realpath` dentro de `data/views/`).
- El shortcode **no** vuelca los datos en el HTML; el navegador los pide con nonce.
- `uninstall.php` limpia opciones sin tocar contenido del usuario.

## Integración con Claude Code

Para replicar el entorno con los skills de diseño recomendados, en tu terminal:

```bash
npx skills add anthropics/skills --skill frontend-design
npx skills add https://github.com/nextlevelbuilder/ui-ux-pro-max-skill --skill ui-ux-pro-max
```

Estos skills aportan guías de diseño que se aplican cuando Claude escribe CSS/HTML, manteniendo la experiencia minimalista y accesible.

## Changelog

### 1.4.0
- **Tooltips informativos**: cada gráfico ahora muestra un `tbody` con todas las dimensiones y medidas. Etiquetas en español (humanizadas + unidad detectada del nombre del campo: `(Millones COP)`, `(%)`); valores con `Intl.NumberFormat('es-CO')` (separadores de miles correctos); porcentajes con 1 decimal forzado.
- **Títulos de ejes**:
  - Atributos nuevos `x_title="..."` y `y_title="..."` en el shortcode.
  - Auto-detect: si están vacíos, el renderer genera el título a partir del nombre del campo (`humanizeKey`) más una sufijo de unidad si lo detecta. Ej: `inversion_millones_cop` → `Inversion (Millones COP)`.
  - Para `stacked_bar` / `stacked_area` el título Y muestra `Cantidad` o `Valor (Millones COP)` según el conjunto de medidas apiladas.
  - Charts sin ejes (`pie`, `donut`, `treemap`, `geomap`, `network`, `rings`, `sankey`, `tree`) se saltan la configuración de ejes automáticamente.
- **Tooltip por chart**:
  - `geomap`: título = nombre original del municipio con acentos.
  - `stacked_bar` / `stacked_area`: título = "Métrica — Municipio".
  - `network` / `rings` / `sankey`: título = id del nodo.
  - resto: título = valor de la primera dimensión.
- **Estilos del tooltip**: fondo blanco, borde `#cbd5e1`, padding 12/14, título en negrita con underline, body en `#334155 12px`.
- **Admin builder**: dos inputs nuevos en el fieldset "Opciones" para `Eje X` y `Eje Y`, con placeholder `auto` y actualización en vivo de la preview y el shortcode generado.

### 1.3.0
- **Topojson con detalle completo**: regenerado desde el geojson oficial sin `toposimplify`, solo con quantización. Los 64 municipios ahora conservan todas las delimitaciones originales (49 KB, 188 arcs únicos, 349 referencias compartidas entre vecinos — cada borde compartido aparece una sola vez).
- **Opciones de shortcode**: `legend`, `legend_style` (text|icons), `toolbar`, `actions` (lista). Ejemplo: `[tsg_grafico view="..." type="..." legend_style="icons" actions="datos,imagen"]`.
- **Leyenda en modo iconos**: tira de swatches de color sin texto, con tooltip al pasar el mouse (como en los dashboards de economia.gob).
- **Barra de acciones** encima del gráfico con 5 botones:
  - **Detalle** → modal con metadatos (tipo, categoría, dimensiones, medidas, filas)
  - **Compartir** → `navigator.share` si existe, sino copia URL
  - **Datos** → modal con tabla completa formateada (Intl.NumberFormat 'es-CO')
  - **Imagen** → exporta a PNG 2x usando canvas
  - **Descarga** → descarga payload (vista + datos) como JSON
- **Admin builder**: fieldset "Opciones" con switches (toggle) y chips (acciones). Los cambios reconfiguran la preview en vivo y regeneran el shortcode.
- **CSS compartido**: la preview del admin carga `frontend.css` para ser pixel-perfect idéntica al resultado publicado.

### 1.2.0
- **Vistas TIC Suite nativas**: el data provider acepta dos formatos — el nativo del plugin y el formato de publicación TIC Suite (`vista` / `titulo` / `descripcion` / `tipo_grafico_sugerido` / `municipios` | `datos`). Dimensiones / medidas / categoría se infieren de la primera fila.
- **Compatibilidad de gráficos ampliada**: las vistas geográficas ya no son exclusivas de `geomap` — ahora soportan también `bar`, `stacked_bar`, `pie`, `donut`, `treemap`, `tree`, `box_whisker`. Las 6 vistas del usuario producen 48 combinaciones válidas (promedio de 8 gráficos por vista).
- **Topojson local de Nariño**: `data/topo/narino_municipios.topojson` (14 KB, 4% del geojson original) con los 64 municipios, propiedades limpias (id, nombre, divipola) y geometría simplificada (toposimplify=0.002).
- **IDs normalizados para join**: el topojson usa id = `normalize(MPIO_CNMBR)` (uppercase, NFD sin acentos, whitespace colapsado). El renderer aplica la misma normalización al campo `municipio` de cada fila de datos y genera `_municipio_id` para que d3plus.Geomap haga el join sin configuración adicional.
- **Reshape wide→long**: `stacked_bar` y `stacked_area` ahora reshape filas anchas (`en_operacion`/`en_instalacion`/`en_planeacion`) a formato largo (una fila por métrica). Series derivadas (`total`, `pct_*`, `participacion_*`, `cobertura_*`) se omiten para evitar doble conteo.
- **humanizeKey()** convierte `en_operacion` → `En operacion` en las leyendas.

### 1.1.1
- **Fix crítico de CDN**: pasa de `/umd/d3plus-core.js` a `/umd/d3plus-core.full.js`. El primero requería 30+ peer deps (d3-array, d3-scale, d3-sankey, topojson-client, @floating-ui/dom, …) presentes en `window`, lo que hacía que `window.d3plus` se cargara como `{}` vacío y el renderer cayera en el fallback *"Tipo de gráfico no soportado"*.
- El bundle `full` (2 MB) embebe todas las dependencias y expone las 15 clases que usa el plugin: `BarChart`, `LinePlot`, `AreaPlot`, `StackedArea`, `Pie`, `Donut`, `Treemap`, `Geomap`, `Network`, `Tree`, `Sankey`, `Rings`, `BoxWhisker`, `Priestley`.

### 1.1.0
- Migrado a `@d3plus/core` v3.1.4 desde el CDN oficial (`cdn.jsdelivr.net/npm/@d3plus/core@3.1.4/umd/d3plus-core.js`).
- Renderer reescrito a la API v3 (chainable `.select()`, `.data()`, `.groupBy()`, etc.) con `.detectResize(true)` para responsive automático.
- `stacked_bar` ahora usa `BarChart` + `.stacked(true)` (en v3 no existe `StackedBarChart`).
- Cargador `waitForD3plus()` con timeout de 8 s y mensaje de error claro.
- CSS: `.tsg-chart` y `.tsg-preview__canvas` con altura explícita y SVG responsive al 100 %.
- Shortcode: añade `height` además de `min-height` para que el parent sea medible.

### 1.0.0
- Versión inicial.

## Licencia

GPL-2.0-or-later.
