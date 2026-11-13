# 12 · Plan de reestructuración: DigitalNest como app de ofertas, cupones y top apps

Fecha: 13 de noviembre de 2026. Estado: **aprobado y fases 0–8 implementadas** (13 de noviembre de 2026). Sustituye la dirección de producto de [11 · Plan DigitalNest](11-PLAN-DIGITALNEST.md). Lo marcado como «Posterior» sigue requiriendo aprobación.

## 1. Visión

DigitalNest pasa de ser un sitio editorial/corporativo a una **PWA tipo app móvil** donde el usuario descubre, compara y ahorra en herramientas digitales de todos los sectores: IA, vídeo, audio, imágenes, diseño, hosting, VPN, productividad, desarrollo, marketing, educación, etc.

Tres pilares de contenido:

1. **Ofertas** — descuentos y promociones con precio, % de ahorro y caducidad.
2. **Cupones** — códigos copiables o enlaces de activación, verificados y con fecha.
3. **Top apps** — rankings por sector («Las 10 mejores IA para vídeo»), fichas de app y comparativas.

Sensación objetivo: app nativa premium (estilo App Store / Revolut / Arc): barra inferior, transiciones suaves, tarjetas con profundidad, modo oscuro de serie, carga instantánea, instalable.

## 2. Arquitectura de información

### Navegación (móvil primero)

Barra inferior fija con 5 pestañas:

| Pestaña | Ruta | Contenido |
|---|---|---|
| Inicio | `/` | Hero de oferta destacada, «Caducan hoy», categorías en carrusel, top de la semana, nuevos cupones |
| Explorar | `/explorar` | Grid de sectores → subcategorías → apps; filtros (gratis, con cupón, precio, plataforma) |
| Ofertas | `/ofertas` | Feed de ofertas y cupones con chips de filtro (todas · cupones · lifetime · gratis · caducan pronto) |
| Rankings | `/top` | Listas Top por sector y comparativas |
| Guardados | `/guardados` | Favoritos y cupones guardados (localStorage, sin cuentas en v1) |

En escritorio: la barra inferior se convierte en barra lateral izquierda compacta + buscador fijo arriba (layout «app de escritorio», no web clásica).

### Páginas

- `/app/{slug}` — **Ficha de app**: logo, valoración, precio, plataformas, pros/contras, ofertas y cupones activos, alternativas, CTA de afiliado.
- `/sector/{slug}` y `/sector/{slug}/{subcategoria}` — **Página de sector**: top del sector, ofertas activas, todas las apps.
- `/oferta/{slug}` — **Detalle de oferta/cupón** (hoja inferior en móvil; página propia para SEO y compartir).
- `/top/{slug}` — **Ranking** («Mejores IA para generar imágenes 2026»).
- `/comparar/{a}-vs-{b}` — **Comparativa** (fase posterior).
- `/buscar` — búsqueda instantánea (apps, ofertas, sectores).
- `/go/{oferta}` — **redirección de afiliado** con conteo de clics (sin datos personales).
- Legales, Cómo probamos, Afiliados, Sobre: se conservan reutilizando `LegalPage`.
- Blog: se reconvierte en «Guías» secundarias, fuera de la barra principal.

## 3. Modelo de datos

Reutilizar lo que ya existe y añadir solo lo necesario.

| Entidad | Origen | Notas |
|---|---|---|
| `Sector` | Reutilizar `ToolCategory` (renombrar en UI) | Jerárquico (parent_id), icono, color de acento, orden |
| `App` | Reutilizar `Tool` | Añadir: valoración, modelo de precio (gratis/freemium/pago/lifetime), precio desde, plataformas, URL de afiliado, pros/contras, destacado |
| `Deal` (nuevo) | Migración nueva | `tool_id`, tipo (oferta/cupón/lifetime/prueba gratis), título, código (nullable), descuento %, precio original/oferta, moneda, URL, empieza/caduca, verificado_en, exclusivo, destacado, estado, clics |
| `Ranking` (nuevo) | Migración nueva | título, slug, sector, intro, SEO, publicado |
| `ranking_tool` (pivote) | Migración nueva | posición, veredicto corto, «mejor para…» |
| `Template`, `Workflow`, `Project`, `Service`, `Testimonial`, `Faq`, asistente | Existentes | Se ocultan del frontal; **no se borran** sin decisión explícita |

Campos exactos se proponen y aprueban antes de crear cada migración (regla de AGENTS.md).

Reglas de negocio clave:
- Una oferta caducada desaparece de los feeds automáticamente (scope `active()`), pero su URL sigue viva con aviso «Caducada» + alternativas (SEO).
- Tarea programada diaria que marca caducadas y limpia destacados vencidos.
- Clics de `/go` agregados por día, sin IP ni cookies.

## 4. Panel (Filament 5)

- Recursos: Sectores, Apps, Ofertas/Cupones, Rankings (con relation manager ordenable de apps).
- Acciones rápidas: duplicar oferta, «marcar verificada hoy», ampliar caducidad.
- Widget de dashboard: ofertas que caducan en 7 días, top clics, apps sin oferta activa.
- Policies para cada recurso nuevo (regla 5).

## 5. Frontend y diseño

### Stack (sin dependencias nuevas salvo aprobación)

- Blade + Tailwind 4 + Vite (ya instalados) con componentes Blade reutilizables.
- Livewire (ya presente en el proyecto) solo donde aporta: búsqueda instantánea y filtros.
- Navegación tipo app: `wire:navigate` de Livewire (transiciones sin recarga) + View Transitions API para animaciones entre páginas.
- **Propuesta a aprobar**: ninguna librería JS extra; service worker escrito a mano (pequeño y controlado) en vez de un paquete PWA.

### Sistema de diseño premium

- Tokens en CSS (`@theme` de Tailwind 4): colores, radios, sombras, espaciado, tipografía.
- Oscuro por defecto con modo claro; superficies en capas (fondo → tarjeta → hoja), bordes sutiles de 1px, desenfoque en barras (glass).
- Tipografía: una sans geométrica de alta calidad para títulos y cifras (evaluar mantener Poppins o pasar a Inter/Geist); números tabulares en precios.
- Un acento de marca + color por sector (para chips e iconos).
- Componentes: `app-card`, `deal-card` (con badge −40 %, cuenta atrás, botón «Copiar código»), `coupon-sheet` (hoja inferior), `ranking-row`, `sector-tile`, `chip-filter`, `bottom-nav`, `top-bar`, `skeleton`, `empty-state`, `toast`.
- Microinteracciones: copiar código con háptica (`navigator.vibrate`) y toast, pulsación con escala, deslizar carruseles con scroll-snap, pull-to-refresh visual opcional.
- Accesibilidad: contraste AA, foco visible, objetivos táctiles ≥ 44 px, `prefers-reduced-motion`.

### Responsive

- Diseñado a 375 px primero; breakpoints: móvil (bottom nav), tablet (grid 2–3 columnas), escritorio (sidebar + contenido centrado a 1200 px).
- Respeto de `safe-area-inset` (notch e indicador de inicio en iOS).

## 6. PWA

- `manifest.webmanifest`: nombre, iconos (192/512/maskable), `display: standalone`, `theme_color`, accesos directos (Ofertas, Buscar, Guardados), capturas.
- Service worker: precache del shell (CSS/JS/fuentes/iconos), estrategia *stale-while-revalidate* para páginas, página offline propia, sin cachear `/go` ni el panel.
- Pantalla de bienvenida e icono iOS (`apple-touch-icon`, meta tags).
- Aviso de instalación propio y discreto (tras 2.ª visita).
- Fase posterior (requiere aprobación): notificaciones push de ofertas por sector.

## 7. SEO y monetización

- Datos estructurados: `SoftwareApplication` + `Offer` (fichas), `ItemList` (rankings), `BreadcrumbList`.
- Sitemaps por tipo, canónicas, metas OG con imagen generada por oferta.
- Divulgación de afiliados visible en cada CTA; enlaces `rel="sponsored nofollow"`.
- Reutilizar `Redirect`, `SearchSetting`, consentimiento de cookies y `CustomScript` existentes.

## 8. Fases de implementación

Cada fase: rama propia, pruebas (`php artisan test`), Pint y reporte.

| Fase | Entregable | Depende de |
|---|---|---|
| **0. Decisiones** | Aprobar este plan, nombre de rutas, sectores iniciales, tipografía y paleta | — |
| **1. Datos** | Migraciones `deals`, `rankings`, `ranking_tool`, campos nuevos en `tools`; modelos, factories, seeders con sectores reales; scopes y tests | 0 |
| **2. Panel** | Recursos Filament de Sectores, Apps, Ofertas, Rankings + policies + widget | 1 |
| **3. Sistema de diseño** | Tokens, layout app (top bar, bottom nav, sidebar), componentes base, modo oscuro/claro | 0 |
| **4. Pantallas núcleo** | Inicio, Explorar, Sector, Ficha de app, Ofertas, Detalle de oferta, `/go` | 1, 3 |
| **5. Rankings y búsqueda** | Top, ranking, búsqueda instantánea, Guardados | 4 |
| **6. PWA** | Manifest, service worker, offline, instalación, iconos | 3 |
| **7. SEO y pulido** | Datos estructurados, sitemaps, OG, rendimiento (LCP < 2 s en 4G), auditoría Lighthouse ≥ 90 | 4–6 |
| **8. Limpieza** | Retirar vistas públicas antiguas, redirecciones 301 de URLs viejas a las nuevas, actualizar README/AGENTS/docs | 7 |
| **Posterior** | Comparativas, push, cuentas de usuario, alertas de precio | aprobación |

Las fases 1–2 y 3 pueden avanzar en paralelo (archivos distintos).

## 9. Qué se rehace y qué se conserva

- **Se rehace**: todo `resources/views/public`, layout, CSS público, navegación, rutas públicas y páginas de inicio.
- **Se conserva**: autenticación, roles/permisos, panel Filament, biblioteca multimedia, ajustes generales/correo/SEO, redirecciones, scripts personalizados, legales, consentimiento de cookies.
- **Se oculta (no se borra)**: servicios, portafolio, testimonios, FAQ, asistente, plantillas y workflows.

## 10. Desviaciones respecto al plan

- Las páginas visitadas no quedan disponibles sin conexión: el sitio envía `Cache-Control: no-store` por seguridad (token CSRF y estado de cookies), así que el service worker solo cachea recursos estáticos y sirve `/offline`.
- Flujos y plantillas redirigen a `/explorar`; siguen editables en el panel.
- Se mantiene Poppins; `resources/css/app.css` se conserva solo para el portafolio corporativo oculto y la vista previa del asistente.
- Rendimiento: no se midió con Lighthouse en esta sesión.

## 11. Decisiones abiertas

1. Cambio de alcance en AGENTS.md: el producto pasa a ser un agregador de ofertas; hay que actualizar la regla 13 y el plan 11.
2. ¿Guardados sin cuenta (localStorage) en v1? Propuesta: sí.
3. Moneda y mercado: ¿USD, EUR o ambos? ¿Contenido solo en español?
4. ¿Las ofertas se cargan solo a mano o se prevé importación (CSV/feeds de redes de afiliados) más adelante?
5. Tipografía y color de marca definitivos (se presentarán 2 propuestas visuales en la fase 3).
