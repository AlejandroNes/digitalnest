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
  page: "#f3f7fb"
  pass: "#12804a"
  fair: "#9a6200"
  poor: "#c0322b"
typography:
  display:
    fontFamily: "Poppins, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(2rem, 1.3rem + 3.2vw, 3.6rem)"
    fontWeight: 700
    lineHeight: 1.12
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Poppins, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.35rem, 1.15rem + 0.9vw, 1.85rem)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Poppins, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.05rem"
    fontWeight: 600
    lineHeight: 1.35
  body:
    fontFamily: "Poppins, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
  label:
    fontFamily: "Poppins, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.8rem"
    fontWeight: 500
    lineHeight: 1.4
rounded:
  sm: "10px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  pill: "999px"
spacing:
  gutter: "1.25rem"
  container: "74rem"
  section: "clamp(3rem, 2rem + 3vw, 5rem)"
components:
  button-primary:
    backgroundColor: "{colors.navy}"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "0 1.4rem"
    height: "48px"
  button-primary-hover:
    backgroundColor: "#12345c"
  button-secondary:
    backgroundColor: "{colors.white}"
    textColor: "{colors.navy}"
    rounded: "{rounded.md}"
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
  tool-card:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.xl}"
    padding: "1.25rem"
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

**Display Font:** Poppins (autoalojada en `/fonts/poppins/`, pesos 400, 500, 600 y 700, OFL), con `ui-sans-serif, system-ui` de respaldo.
**Body Font:** Poppins.

**Character:** geométrica, amable y muy legible; los titulares van en 700 con el espaciado ligeramente cerrado y el texto en 400 con interlineado amplio. Elegida por el propietario. Las cifras usan números tabulares.

### Hierarchy
- **Display** (700, clamp de 2rem a 3.6rem, 1.12, -0.025em): titular del hero y títulos de página.
- **Headline** (700, clamp de 1.35rem a 1.85rem, 1.2, -0.02em): títulos de sección.
- **Title** (600, 1.05rem, 1.35): nombres de herramientas, títulos de bloque y de lista.
- **Body** (400, 1rem en móvil y 1.0625rem desde 64rem, 1.65): texto corrido, máximo 68ch.
- **Label** (500–600, 0.75–0.82rem): etiquetas de datos, píldoras y contadores.

### Named Rules
**The Tabular Numbers Rule.** Precios, notas y conteos usan `font-variant-numeric: tabular-nums`.

## Layout

Fondo de página gris azulado (#f3f7fb) para que las superficies blancas destaquen. Contenedor centrado de 72rem con márgenes de 1.25rem (1rem por debajo de 30rem). El hero celeste ocupa todo el ancho con esquinas inferiores de 32px; las cabeceras de página usan celeste neblina con esquinas de 28px. Las herramientas se muestran en una cuadrícula de tarjetas `repeat(auto-fill, minmax(19rem, 1fr))`: 3 columnas en escritorio, 2 en tableta y 1 en móvil. Por debajo de 48rem: menú hamburguesa, chips de filtro en una fila desplazable, botones del hero a ancho completo y filtros apilados. La comparativa de alternativas es la única tabla y se desplaza en horizontal dentro de su panel.

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

Esquinas suaves y consistentes: 10px en insignias de nota, 12px en botones, selects, logos y enlaces del menú, 16px en buscador y bloques de encaje, 20px en tarjetas y paneles, 28–32px en hero, cabeceras de página y pie. Chips, etiquetas y píldoras de datos son redondeados completos (999px).

## Components

### Buttons
- **Primario:** rectángulo redondeado (12px) marino, texto blanco, 48px (54px en el hero). Al pasar el ratón se aclara a #12345c, sube 1px y gana sombra.
- **Secundario:** píldora blanca con texto marino; sobre el hero, junto al primario.
- **Foco:** contorno de 3px en celeste profundo con 3px de separación, en todos los elementos interactivos.

### Chips
Píldoras de 44px, blancas con borde de línea y un contador en celeste. Activo: fondo marino y texto blanco. Sobre el hero: sólidas, sin borde visible.

### Cards / Containers
**Panel:** blanco, radio de 24px, sombra de panel y overflow oculto; cabecera y pie separados por la línea, con el pie en papel. Nunca se anidan paneles.

### Inputs / Fields
Select de 44px con radio de 10px y borde #b9c9d8; casillas con `accent-color` en celeste profundo. Las etiquetas van encima, en estilo Label.

### Navigation
Cabecera blanca fija a todo el ancho, translúcida con desenfoque, con línea inferior. Enlaces con radio de 12px; el activo usa fondo neblina y texto celeste profundo. En móvil, botón hamburguesa (44px) que despliega un panel con los enlaces a 48px de alto; se cierra con Escape o al pulsar fuera.

### Tool card (signature)
Tarjeta blanca de 20px con logo, nombre, categoría y nota arriba; la frase en medio; y abajo, separadas por una línea, píldoras de precio, plan gratis, español y «Recomendada». Toda la tarjeta es clicable y se eleva 3px al pasar el ratón.

### Comparison table
Solo en la página de alternativas: logo, nombre, nota, precio, plan gratis y español; desplazable en horizontal en pantallas estrechas.

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
