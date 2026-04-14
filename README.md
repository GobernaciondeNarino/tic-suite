# TIC Suite · Gráficos

**v1.1.1** — Plugin profesional para **TIC Suite** (Gobernación de Nariño)
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
