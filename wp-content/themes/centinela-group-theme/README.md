# Centinela Group Theme

Tema de WordPress optimizado para rendimiento, compatible con **Elementor** (versión gratuita) y preparado para integrar la API de Syscom Colombia. Diseño responsive con **Tailwind CSS**.

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- (Opcional) Elementor para edición visual de páginas

## Instalación

1. Activa el tema en **Apariencia → Temas**.
2. Configura los menús en **Apariencia → Menús** (Menú principal, Menú pie de página).
3. Para editar la portada con Elementor: crea una página, asígnala como «Página de inicio» en **Ajustes → Lectura**, y edítala con Elementor.

## Desarrollo: Tailwind CSS

El tema usa Tailwind CSS con CSS purgado para buen rendimiento. Si modificas clases en los archivos PHP o en `assets/js/theme.js`, vuelve a compilar el CSS.

```bash
# Desde la carpeta del tema
cd wp-content/themes/centinela-group-theme

# Instalar dependencias (solo la primera vez)
npm install

# Compilar CSS (producción)
npm run build:css

# Compilar y vigilar cambios (desarrollo)
npm run watch:css
```

El archivo generado es `assets/css/tailwind.min.css`. No edites ese archivo a mano; usa `input.css` y las clases en los PHP, y vuelve a ejecutar `npm run build:css`.

## Desarrollo: SCSS y BEM

Los estilos de **componentes, plugins y elementos** del tema viven en SCSS con metodología **BEM** (Block__Element--Modifier). Se compilan a `assets/css/theme.min.css` para mantener buen rendimiento.

### Estructura SCSS

```
assets/scss/
├── _variables.scss    # Colores y tipografía (Roboto)
├── _typography.scss   # Base tipográfica y clases de peso
├── theme.scss         # Entrada principal (compilar este)
├── components/        # Componentes BEM del tema
│   ├── _index.scss    # Importar aquí cada componente
│   └── _example-bem.scss
└── plugins/           # Estilos para plugins o bloques
    └── _index.scss    # Importar estilos de plugins aquí
```

### Variables del sitio (`_variables.scss`)

| Variable | Valor | Uso |
|----------|--------|-----|
| `$blue-color` | #021C37 | Azul principal |
| `$blue-color-2` | #1543A0 | Azul secundario |
| `$green-color` | #229379 | Verde |
| `$grey-color` | #54595F | Gris |
| `$white-color` | #FFFFFF | Blanco |
| `$black-color` | #000000 | Negro |
| `$font-family-base` | Roboto, sans-serif | Tipografía del sitio |
| `$font-weight-thin` … `$font-weight-black` | 100, 300, 400, 500, 700, 900 | Pesos de Roboto |

También se exponen como variables CSS en `:root` (ej. `--centinela-blue`, `--centinela-font`) para usar en Tailwind o inline.

### Tipografía: Roboto

El tema usa **Roboto** con todos sus pesos (100, 300, 400, 500, 700, 900). Se carga desde Google Fonts. Clases de utilidad: `.font-thin`, `.font-light`, `.font-regular`, `.font-medium`, `.font-bold`, `.font-black`.

### BEM: añadir componentes o plugins

1. **Componente:** crea `assets/scss/components/_mi-componente.scss` usando BEM (`.block__element--modifier`) y variables de `_variables.scss` vía `@use '../variables' as *;`. Luego en `components/_index.scss` añade `@use 'mi-componente';`.
2. **Plugin:** crea el partial en `assets/scss/plugins/` e impórtalo en `plugins/_index.scss`.
3. Compila: `npm run build:scss` (o `npm run watch:scss` en desarrollo).

No edites `assets/css/theme.min.css` a mano; siempre recompila desde SCSS.

### Scripts npm

```bash
npm run build:scss   # Compilar SCSS → theme.min.css (producción)
npm run watch:scss   # Vigilar cambios y recompilar
npm run build       # Tailwind + SCSS
npm run watch       # Vigilar Tailwind y SCSS
```

## Estructura

- `assets/css/` — Tailwind (input.css, tailwind.min.css) y SCSS compilado (theme.min.css)
- `assets/scss/` — Variables, tipografía, componentes y plugins (BEM)
- `assets/js/` — theme.js / theme.min.js (menú móvil)
- `inc/` — template-header.php, template-footer.php
- `template-parts/` — content.php, content-single.php, content-none.php

## Performance

- Tailwind purgado (solo clases usadas).
- No se carga Tailwind en el preview de Elementor para evitar conflictos.
- Script mínimo (menú móvil) y carga en el footer.
- Soporte para `loading="lazy"` en iframes del contenido.
- Compatible con plugins de caché y optimización.

## Screenshot

Puedes añadir `screenshot.png` (1200×900 px) en la raíz del tema para que aparezca en **Apariencia → Temas**.
