# 11 · Plan de producto DigitalNest

Actualizado: 10 de noviembre de 2026. Define el producto que se construye sobre esta base Laravel. Sustituye al sitio WordPress actual (digitalnestweb.com), cuyo contenido no se migra salvo las excepciones indicadas. Este documento describe alcance y orden de trabajo; no autoriza por sí mismo migraciones sobre datos, instalaciones ni despliegues.

## Propuesta de valor

**Aprende a usar la IA y las herramientas digitales en tu trabajo. Probado, explicado y en español.**

Todo contenido responde a: *¿cómo hago X mejor y más rápido con herramientas digitales?* Si no la responde, no entra.

Diferenciación frente a los directorios grandes (There's An AI For That, Futurepedia, AlternativeTo):

1. **Español real:** español neutro, calidad de la herramienta en español, precios en USD y pagos desde Latinoamérica.
2. **Probado por una persona:** capturas propias, resultados reales y fecha de prueba (experiencia E-E-A-T).
3. **Organizado por tarea y profesión**, no por tecnología.
4. **Pocas piezas y profundas:** calidad sobre volumen; nada de contenido de relleno.

Público principal: freelancers, creadores y pequeños negocios.

## Estructura del sitio

```
Inicio
├── Herramientas
│   ├── /herramientas                         listado con filtros por categoría y precio
│   ├── /herramientas/categoria/{slug}
│   ├── /herramientas/{slug}                  ficha
│   ├── /herramientas/{slug}/alternativas
│   └── /comparar/{x}-vs-{y}
├── IA                                         categoría de Guías con portada propia
│   ├── Empieza aquí
│   ├── Qué modelo usar                        guía viva, revisión mensual
│   ├── Asistentes y agentes                   GPTs, proyectos, skills, agentes, MCP
│   ├── Automatización                         n8n, Make, Zapier con IA
│   └── IA local                               Ollama, LM Studio, privacidad
├── Flujos de trabajo
│   ├── /flujos                                filtro por profesión y tarea
│   └── /flujos/{slug}                         paso a paso
├── Plantillas
│   ├── /plantillas                            filtro por tipo
│   └── /plantillas/{slug}                     contenido copiable o descargable
└── Guías                                      blog: tutoriales, "mejores X", explicaciones

Páginas fijas: Sobre mí, Cómo pruebo las herramientas, Afiliados y transparencia, Contacto, Legales.
```

Menú principal: **Herramientas · IA · Flujos · Plantillas · Guías**. Sin submenús de tres niveles.

## Contenido por sección

### Herramientas

Ficha: veredicto en una frase, para quién es (y para quién no), prueba con capturas, modelo de precio (gratis, freemium, pago, open source), plan gratis real, precio en USD, pago desde Latinoamérica, calidad en español, plataformas, privacidad, pros y contras, alternativas, flujos donde aparece, enlace (afiliado cuando exista) y fechas de prueba y actualización. Las ofertas se mencionan dentro de la ficha, no en una sección propia.

Categorías por tarea: escribir, imagen, video, audio y voz, diseño, programar, marketing y SEO, productividad, automatización, presentaciones, estudio, negocio y freelance.

### Flujos de trabajo (sección diferencial)

Caso real de principio a fin: objetivo, herramientas (enlazadas a fichas), pasos con capturas, prompts o plantilla, resultado, tiempo y coste. Clasificados por profesión y tarea.

### Plantillas

Contenido listo para copiar o descargar con vista previa: skills e instrucciones de sistema, asistentes/GPTs, flujos de automatización (JSON de n8n/Make) y prompts. Los prompts solo se publican vinculados a un flujo o caso de uso.

### IA

Guías del blog con una portada propia. No hay catálogo de modelos: una única página "Qué modelo usar", con fecha visible e historial de cambios.

### Guías

El blog actual: tutoriales, comparativas narrativas, "mejores X para Y" construidas desde fichas reales y explicaciones.

## Inicio

1. Propuesta de valor y buscador.
2. "¿Qué quieres hacer?": accesos por tarea.
3. Flujos destacados.
4. Plantillas nuevas.
5. Herramientas recomendadas.
6. "Qué modelo usar" con su fecha de actualización.
7. Novedades del mes: herramientas probadas y plantillas recientes.

Sin carruseles ni bloques de noticias.

## Fuera de alcance

| Elemento | Motivo |
| --- | --- |
| Noticias | Caducan, exigen ritmo diario y compiten con medios grandes y respuestas de buscadores |
| Newsletter | Descartado por decisión del propietario |
| Sección de ofertas | Alto mantenimiento y pérdida de confianza con códigos caducados; se mencionan en fichas |
| Sección de repositorios de GitHub | Cubierta por GitHub Trending y listas "awesome"; los repos útiles entran como herramientas |
| Recursos gratis genéricos (iconos, fuentes, bancos) | Sin diferenciación |
| Catálogo de modelos y prompts sueltos | Mantenimiento inviable / valor bajo |
| Ejercicios de programación y tutoriales de desarrollo genéricos | Otro público; diluyen el foco |
| Comentarios, votos, cuentas de usuario públicas, comparador interactivo | Requieren audiencia previa |

Se mantienen además las exclusiones generales de [AGENTS.md](../AGENTS.md).

## Módulos existentes

| Módulo | Uso en DigitalNest |
| --- | --- |
| Blog y categorías | Guías e IA |
| Biblioteca multimedia | Capturas, logos y archivos de plantillas |
| SEO, sitemap, robots, redirecciones | Se reutilizan; se amplían datos estructurados |
| Consentimiento e integraciones | Se reutilizan |
| Contacto | Contacto y, opcionalmente, "Sugiere una herramienta" |
| Legales | Se reutiliza; se añade página de afiliados |
| Servicios, Portafolio, Testimonios, FAQ, Asistente | Se ocultan del frontend; no se borra código sin decisión explícita |

## Módulos nuevos

| Módulo | Modelos previstos |
| --- | --- |
| Herramientas | `Tool`, `ToolCategory`, alternativas (relación muchos a muchos entre herramientas) |
| Flujos | `Workflow`, pasos y relaciones con herramientas y plantillas |
| Plantillas | `Template` con tipo, contenido copiable y archivo opcional |

Los campos exactos se propondrán y aprobarán antes de cada fase, con migraciones reversibles, policies, recursos Filament, vistas públicas y pruebas, siguiendo las reglas de [AGENTS.md](../AGENTS.md).

## SEO

- Enlazado interno sistemático: flujo → herramientas → alternativas → comparativas → plantillas.
- Datos estructurados: `SoftwareApplication`, `Review`, `HowTo`, `FAQPage`, `BreadcrumbList`.
- Autoría visible y método de prueba publicado.
- Fechas de prueba y actualización reales; revisión trimestral de fichas.
- Páginas ligeras e imágenes optimizadas.

## Migración desde WordPress

El tráfico actual es bajo, así que se empieza limpio. Antes del cambio de DNS se exportan desde Search Console las URLs con visitas. Solo las que tengan tráfico y encajen (por ejemplo, las guías de Ghibli con ChatGPT o Ideogram) se reescriben y redirigen con 301; el resto devuelve 410.

## Monetización

1. Afiliados en fichas, comparativas y flujos, siempre con aviso de transparencia.
2. Plantillas premium o packs (opcional, largo plazo).
3. Publicidad discreta solo en guías; evitarla al inicio.

## Hoja de ruta

| Fase | Trabajo | Criterio de terminado | Estado |
| --- | --- | --- | --- |
| 1 · Fundamentos | Plan documentado; público, idioma y moneda decididos; falta método de prueba e identidad visual | Método de prueba publicado y guía visual aprobada | En curso |
| 2 · Herramientas | Modelo, panel, ficha pública, categorías, alternativas, SEO estructurado | Se publican fichas desde el panel con pruebas | Implementada; migraciones aplicadas en la base local |
| 3 · Layout e inicio | Diseño público, menú, inicio, ocultar módulos corporativos | Sitio navegable | Pendiente |
| 4 · Flujos y plantillas | Módulos, vistas públicas y relaciones | Se publican flujos y plantillas enlazados | Pendiente |
| 5 · Contenido de lanzamiento | 25 fichas, 10 flujos, 10 plantillas, "Qué modelo usar", "Empieza aquí" | Contenido cargado y revisado | Pendiente |
| 6 · Lanzamiento | 301/410, Search Console, cambio de DNS | Sitio en línea e indexándose | Pendiente |
| 7 · Crecimiento | Ritmo semanal de fichas y flujos; comparativas y alternativas | Revisión trimestral en Search Console | Pendiente |

Las fases 3 y 4 del [plan del blog](10-PLAN-MEJORA-BLOG.md) se revisarán con este enfoque cuando se retomen.

## Decisiones del propietario

Confirmadas el 10 de noviembre de 2026:

- **Público principal:** freelancers, creadores y pequeños negocios.
- **Identidad visual:** nueva, desde cero; no se conserva el logo ni los colores anteriores.
- **Idioma:** español neutro.
- **Moneda:** precios solo en USD.
