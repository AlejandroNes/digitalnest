---
name: DigitalNest
description: Herramientas digitales y de IA probadas, explicadas en español.
colors:
  sky: "#4cc3f0"
  sky-soft: "#e6f6fd"
  sky-deep: "#08739f"
  navy: "#0b2340"
  navy-soft: "#4a5b70"
  coral: "#ff7a45"
  coral-soft: "#fff0e9"
  line: "#dbe5ee"
  paper: "#f6f9fc"
  white: "#ffffff"
  pass: "#12804a"
  fair: "#9a6200"
  poor: "#c0322b"
typography:
  display:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2.6rem, 1.5rem + 4.2vw, 4.75rem)"
    fontWeight: 820
    lineHeight: 0.98
    letterSpacing: "-0.045em"
    fontVariation: "'wdth' 94"
  headline:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.65rem, 1.2rem + 1.5vw, 2.4rem)"
    fontWeight: 800
    lineHeight: 1.05
    letterSpacing: "-0.035em"
  title:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.1rem"
    fontWeight: 720
    lineHeight: 1.25
  body:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 700
    lineHeight: 1.3
rounded:
  sm: "10px"
  md: "14px"
  lg: "18px"
  xl: "24px"
  pill: "999px"
spacing:
  gutter: "1.25rem"
  container: "74rem"
  section: "clamp(3rem, 2rem + 3vw, 5rem)"
components:
  button-primary:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    rounded: "{rounded.pill}"
    padding: "0 1.4rem"
    height: "48px"
  button-primary-hover:
    backgroundColor: "#12345c"
  button-secondary:
    backgroundColor: "{colors.white}"
    textColor: "{colors.navy}"
    rounded: "{rounded.pill}"
    height: "48px"
  chip:
    backgroundColor: "{colors.white}"
    textColor: "{colors.navy}"
    rounded: "{rounded.pill}"
    height: "44px"
  chip-active:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
  panel:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.xl}"
  tag-featured:
    backgroundColor: "{colors.coral-soft}"
    textColor: "#a8401a"
    rounded: "{rounded.pill}"
  logo-tile:
    backgroundColor: "{colors.sky-soft}"
    textColor: "{colors.sky-deep}"
    rounded: "{rounded.md}"
    size: "2.75rem"
---

# Design System: DigitalNest

## Overview

**Creative North Star: "Cielo despejado"**

DigitalNest existe para que alguien decida rápido qué herramienta le sirve. El sistema despeja el camino: un campo celeste abre la página con aire y optimismo, el azul marino da firmeza a lo que se lee y se pulsa, y el coral aparece solo para señalar lo que merece atención. Los datos (nota, precio, plan gratis, español) siempre se ven de un vistazo, en tablas limpias dentro de paneles blancos.

La densidad es media: el inicio respira, los listados y fichas son compactos y escaneables. Lo moderno se expresa con superficies muy redondeadas, sombras en capas suaves, una cabecera flotante y una sola animación de entrada, nunca con efectos sueltos. Se rechaza expresamente el «directorio de IA» oscuro con neón y la cuadrícula de tarjetas iguales con icono.

**Key Characteristics:**
- Celeste como campo de marca a página completa, no como acento disperso.
- Texto y acciones en azul marino; nunca texto blanco sobre celeste.
- Coral raro y con significado: recomendada, ofertas, «mejor busca otra si».
- Tablas de resultados como forma principal de comparar.
- Esquinas generosas (14–24px), píldoras para acciones y chips.

## Colors

Cielo claro con tinta marina y un punto coral; los colores de veredicto solo comunican calidad.

### Primary
- **Celeste DigitalNest** (#4cc3f0): campo del hero, logotipo, selección de texto, `theme-color`. Siempre con texto marino encima.
- **Celeste profundo** (#08739f): enlaces, foco, chips de conteo, nota «Bueno». Es el celeste legible sobre blanco.
- **Celeste neblina** (#e6f6fd): fondos de cabeceras de página, logos sin imagen, estado activo de la navegación.

### Secondary
- **Coral señal** (#ff7a45): punto del logotipo y acentos puntuales. Sus versiones de texto (#a8401a, #8a3412) sobre **coral suave** (#fff0e9) marcan «Recomendada», ofertas y advertencias de encaje.

### Neutral
- **Marino noche** (#0b2340): texto principal, botones primarios, pie de página.
- **Marino gris** (#4a5b70): texto secundario, etiquetas de tabla y de datos.
- **Línea** (#dbe5ee): separadores y bordes de tabla.
- **Papel** (#f6f9fc): cabeceras de tabla, pies de panel, bloques del bento.

### Verdict
- **Aprobado** (#12804a) para Excelente (9–10), **celeste profundo** para Bueno (7–8), **ámbar** (#9a6200) para Aceptable (5–6) y **rojo** (#c0322b) para Flojo (1–4). También para ✓, – y ✗.

### Named Rules
**The Navy Ink Rule.** Sobre celeste, el texto es marino. El blanco sobre celeste no alcanza contraste AA y está prohibido.

**The Coral Means Something Rule.** El coral solo aparece cuando señala algo (recomendada, oferta, advertencia). Nunca como decoración.

**The Verdict Colors Are Semantic Rule.** Verde, ámbar y rojo se reservan para la calidad y los ✓/✗. No se usan como colores de marca.

## Typography

**Display Font:** Archivo variable (autoalojada en `/fonts/archivo/`, OFL), con `ui-sans-serif, system-ui` de respaldo.
**Body Font:** Archivo.

**Character:** una grotesca de trabajo con titulares pesados y apretados; moderna y directa sin perder legibilidad en los datos. Las cifras usan números tabulares.

### Hierarchy
- **Display** (820, clamp hasta 4.75rem, 0.98, -0.045em, ancho 94%): titular del hero y títulos de página.
- **Headline** (800, clamp hasta 2.4rem, 1.05, -0.035em): títulos de sección.
- **Title** (720, 1.1rem, 1.25): títulos de bloque, nombres en listas.
- **Body** (400, 1.0625rem, 1.6): texto corrido, máximo 68ch en prosa.
- **Label** (700, 0.78–0.82rem): cabeceras de tabla, etiquetas de datos, chips de conteo. En minúsculas, sin mayúsculas forzadas.

### Named Rules
**The Tabular Numbers Rule.** Precios, notas y conteos usan `font-variant-numeric: tabular-nums`.

## Layout

Contenedor centrado de 74rem con un margen lateral mínimo de 1.25rem. Las secciones se separan con `clamp(3rem, 2rem + 3vw, 5rem)`. El hero ocupa todo el ancho y se sitúa bajo la cabecera flotante (margen negativo de 5.25rem). Desde 64rem, el hero usa dos columnas (1.25fr / 1fr). El bento de método usa 1.4fr / 1fr con el primer bloque ocupando dos filas desde 56rem. La ficha técnica pasa de 1 a 2 y a 3 columnas (40rem y 64rem). Por debajo de 48rem, las tablas de resultados se convierten en filas apiladas con las etiquetas de cada dato visibles.

## Elevation & Depth

Profundidad por capas suaves: paneles blancos con un anillo casi invisible y sombras difusas desplazadas hacia abajo. Nada de sombras duras ni halos de color.

### Shadow Vocabulary
- **Panel** (`box-shadow: 0 0 0 1px #fff, 0 1px 2px #0b23400a, 0 8px 24px -12px #0b23401f, 0 32px 64px -32px #0b234033`): paneles de tablas, ficha, recomendadas.
- **Header** (`box-shadow: 0 1px 1px #0b23400d, 0 10px 30px -14px #0b234040`): la cabecera flotante.
- **Lift** (`box-shadow: 0 24px 48px -28px #0b234040` + `translateY(-3px)`): bloques del bento al pasar el ratón.
- **Chip** (`box-shadow: 0 1px 2px #0b234014, 0 6px 16px -10px #0b234040`): chips sobre el hero.

### Named Rules
**The Functional Blur Rule.** El desenfoque solo existe en la cabecera flotante, donde el contenido pasa por debajo. No se usa cristal decorativo.

## Shapes

Esquinas generosas y consistentes: 10px en insignias de nota y selects, 14px en logos y bloques de encaje, 18px en la nota grande, 24px en paneles y bento, 32px en las esquinas superiores del pie. Botones, chips, etiquetas y navegación son píldoras (999px).

## Components

### Buttons
- **Primario:** píldora marina, texto blanco, 48px (54px en el hero). Al pasar el ratón se aclara a #12345c, sube 1px y gana sombra.
- **Secundario:** píldora blanca con texto marino; sobre el hero, junto al primario.
- **Foco:** contorno de 3px en celeste profundo con 3px de separación, en todos los elementos interactivos.

### Chips
Píldoras de 44px, blancas con borde de línea y un contador en celeste. Activo: fondo marino y texto blanco. Sobre el hero: sólidas, sin borde visible.

### Cards / Containers
**Panel:** blanco, radio de 24px, sombra de panel y overflow oculto; cabecera y pie separados por la línea, con el pie en papel. Nunca se anidan paneles.

### Inputs / Fields
Select de 44px con radio de 10px y borde #b9c9d8; casillas con `accent-color` en celeste profundo. Las etiquetas van encima, en estilo Label.

### Navigation
Cabecera flotante en píldora, translúcida con desenfoque, pegada a 0.75rem del borde superior. Los enlaces son píldoras; el activo usa fondo neblina y texto celeste profundo.

### Results Table (signature)
Tabla de comparación con logo, nombre, tagline y etiqueta «Recomendada», seguida de nota, precio, plan gratis y español. La fila completa es clicable mediante el `::after` del nombre. En móvil cada fila se reorganiza con los datos etiquetados.

### Score badge (signature)
Nota «9/10» en una insignia tintada con el color de su veredicto y la etiqueta debajo. La versión grande de la ficha usa un bloque de 24px de radio con el anillo del mismo tono.

## Do's and Don'ts

### Do:
- **Do** usar el celeste como campo completo en heroes y cabeceras de página.
- **Do** mostrar nota, precio en USD, plan gratis y español en cualquier listado de herramientas.
- **Do** dibujar los iconos como SVG de trazo 2.2 (`public/partials/icon`).
- **Do** respetar `prefers-reduced-motion`: la entrada del hero es solo un desplazamiento y empieza visible.

### Don't:
- **Don't** poner texto blanco sobre celeste.
- **Don't** usar kickers ni etiquetas sobre los titulares, ni numerar secciones (01/02/03).
- **Don't** usar cristal decorativo, texto con degradado ni sombras duras desplazadas.
- **Don't** construir páginas con cuadrículas de tarjetas iguales de icono y texto.
- **Don't** usar emoji ni caracteres Unicode como iconos.
