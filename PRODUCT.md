# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Freelancers, creadores y pequeños negocios de habla hispana (España y Latinoamérica) que usan herramientas digitales e IA para trabajar. Llegan casi siempre desde un buscador con una tarea concreta en mente («¿qué herramienta uso para subtitular videos?», «alternativas a Canva»), a menudo desde el móvil, y quieren decidir rápido qué herramienta les sirve, cuánto cuesta de verdad y si funciona en español.

## Product Purpose

DigitalNest ayuda a elegir y usar bien herramientas digitales y de IA en el trabajo: fichas de herramientas probadas, flujos de trabajo paso a paso, plantillas listas para copiar y guías. El éxito es que el visitante resuelva su decisión o su tarea y vuelva, no que consuma noticias.

## Positioning

Probado por una persona real y escrito en español neutro: cada ficha indica la calidad real en español, el precio en USD, si acepta pagos desde Latinoamérica, si el plan gratis sirve de verdad y cuándo se probó. Organizado por tarea y profesión, no por tecnología. Los grandes directorios en inglés tienen volumen; DigitalNest tiene profundidad y contexto hispano.

## Operating Context

- Contenido publicado desde el panel Filament por el equipo de DigitalNest.
- Secciones: Herramientas (listado con filtros, categoría, ficha, alternativas), IA, Flujos de trabajo, Plantillas y Guías (blog). Flujos y Plantillas aún no existen.
- Monetización por enlaces de afiliado, siempre señalados.

## Capabilities and Constraints

- Laravel 13, Blade, Tailwind CSS 4 y Vite; sin framework JS en el frontend público (Livewire/Alpine disponibles si hacen falta).
- Consentimiento de cookies obligatorio antes de cargar analítica.
- Fuera de alcance: noticias, newsletter, sección de ofertas, sección de repositorios, comentarios, cuentas públicas.
- Detalle completo en `docs/11-PLAN-DIGITALNEST.md`.

## Brand Commitments

- Nombre: DigitalNest. Identidad visual nueva; no se conserva el logo ni los colores del sitio anterior.
- Color primario obligatorio: celeste. Combinado con azul marino (texto y acciones) y coral como complementario de acento.
- Tipografía obligatoria: Poppins.
- Firma: la marca DigitalNest, sin una persona visible.
- Voz: tuteo, cercana y directa; útil antes que entusiasta. Español neutro.

## Evidence on Hand

- No hay pruebas reales todavía. Las 13 fichas locales (`DigitalNestDemoSeeder`) son datos de ejemplo para previsualizar el diseño: precios, notas y textos no son verificados y no deben presentarse como reales.
- No hay testimonios, cifras de audiencia, prensa ni logo. No inventarlos.

## Product Principles

1. La decisión del visitante primero: precio, plan gratis y español visibles sin buscar.
2. Transparencia: fecha de prueba visible y afiliados siempre señalados.
3. Profundidad sobre volumen: pocas piezas, bien hechas y actualizadas.
4. Todo enlaza con todo: herramientas, alternativas, flujos y plantillas se conectan.

## Accessibility & Inclusion

WCAG 2.2 AA: contraste, navegación por teclado, foco visible, lectores de pantalla y respeto de `prefers-reduced-motion`. Debe funcionar bien en móviles de gama media y conexiones lentas.
