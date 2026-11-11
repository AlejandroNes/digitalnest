<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Template;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\Workflow;
use App\PostStatus;
use App\PublicationStatus;
use App\TemplateFormat;
use App\TemplateType;
use App\ToolPlatform;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use App\ToolStatus;
use App\WorkflowDifficulty;
use App\WorkflowProfession;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Example content for previewing the design locally. The data is illustrative and must be replaced
 * by real, tested reviews before launch. Run with: php artisan db:seed --class=DigitalNestDemoSeeder
 */
class DigitalNestDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de ejemplo no se cargan en producción.');
        }

        $categories = collect([
            ['escribir', 'Escribir', 'Redacta, corrige y resume textos en menos tiempo.'],
            ['imagen', 'Imagen', 'Crea y edita imágenes para redes, webs y presentaciones.'],
            ['video', 'Video', 'Graba, edita y subtitula videos sin ser editor profesional.'],
            ['automatizacion', 'Automatización', 'Conecta tus aplicaciones y elimina tareas repetitivas.'],
            ['productividad', 'Productividad', 'Organiza proyectos, notas y reuniones.'],
            ['programar', 'Programar', 'Asistentes y editores para escribir código más rápido.'],
        ])->mapWithKeys(fn (array $row, int $index): array => [$row[0] => ToolCategory::query()->updateOrCreate(
            ['slug' => $row[0]],
            ['name' => $row[1], 'description' => $row[2], 'sort_order' => $index, 'is_active' => true],
        )]);

        $tools = [
            ['chatgpt', 'ChatGPT', 'escribir', 'El asistente más versátil para escribir, resumir y pensar ideas.', ToolPricingModel::Freemium, 20, ToolSpanishSupport::Full, 9, true, ['productividad']],
            ['claude', 'Claude', 'escribir', 'Redacta textos largos con un tono natural y analiza documentos extensos.', ToolPricingModel::Freemium, 20, ToolSpanishSupport::Full, 9, true, ['programar']],
            ['grammarly', 'Grammarly', 'escribir', 'Corrector de estilo excelente en inglés, limitado en español.', ToolPricingModel::Freemium, 12, ToolSpanishSupport::None, 6, false, []],
            ['ideogram', 'Ideogram', 'imagen', 'Genera imágenes con texto legible: ideal para miniaturas y carteles.', ToolPricingModel::Freemium, 8, ToolSpanishSupport::Partial, 8, true, []],
            ['canva', 'Canva', 'imagen', 'Diseña piezas para redes en minutos con plantillas y funciones de IA.', ToolPricingModel::Freemium, 15, ToolSpanishSupport::Full, 8, false, ['productividad']],
            ['midjourney', 'Midjourney', 'imagen', 'La mayor calidad estética en imágenes generadas, sin plan gratis.', ToolPricingModel::Paid, 10, ToolSpanishSupport::Partial, 8, false, []],
            ['capcut', 'CapCut', 'video', 'Edita videos verticales con subtítulos automáticos en español.', ToolPricingModel::Freemium, 8, ToolSpanishSupport::Full, 8, true, []],
            ['descript', 'Descript', 'video', 'Edita video y podcast como si editaras un documento de texto.', ToolPricingModel::Freemium, 16, ToolSpanishSupport::Partial, 7, false, []],
            ['n8n', 'n8n', 'automatizacion', 'Automatizaciones potentes con IA que puedes alojar tú mismo.', ToolPricingModel::OpenSource, 24, ToolSpanishSupport::None, 9, true, ['programar']],
            ['make', 'Make', 'automatizacion', 'Automatiza flujos visualmente con un plan gratis generoso.', ToolPricingModel::Freemium, 9, ToolSpanishSupport::Partial, 8, false, []],
            ['zapier', 'Zapier', 'automatizacion', 'La forma más sencilla de conectar miles de aplicaciones.', ToolPricingModel::Freemium, 20, ToolSpanishSupport::None, 7, false, []],
            ['notion', 'Notion', 'productividad', 'Notas, proyectos y bases de datos en un solo lugar, con IA integrada.', ToolPricingModel::Freemium, 10, ToolSpanishSupport::Full, 8, false, []],
            ['cursor', 'Cursor', 'programar', 'Editor de código con IA que entiende todo tu proyecto.', ToolPricingModel::Freemium, 20, ToolSpanishSupport::Partial, 9, false, []],
        ];

        $models = [];
        foreach ($tools as $index => [$slug, $name, $category, $tagline, $pricing, $price, $spanish, $rating, $featured, $secondary]) {
            $tool = Tool::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'tagline' => $tagline,
                'tool_category_id' => $categories[$category]->id,
                'website_url' => 'https://example.com/'.$slug,
                'best_for' => 'Freelancers y pequeños equipos que quieren resultados rápidos sin curva de aprendizaje larga.',
                'not_for' => 'Quien necesita control total o trabaja con datos muy sensibles.',
                'review' => '<p><strong>Contenido de ejemplo.</strong> Aquí irá la prueba real: qué tarea hicimos, cuánto tardamos, qué salió bien y qué no.</p><h2>Cómo la probamos</h2><p>Usamos '.e($name).' durante una semana en tareas reales de un freelance.</p>',
                'pros' => ['Rápida de aprender', 'Buenos resultados desde el primer día', 'Plan gratis útil para empezar'],
                'cons' => ['Las funciones avanzadas son de pago', 'Algunas opciones solo están en inglés'],
                'pricing_model' => $pricing,
                'has_free_plan' => $pricing !== ToolPricingModel::Paid,
                'free_plan_notes' => $pricing === ToolPricingModel::Freemium ? 'Uso limitado al mes' : null,
                'starting_price_usd' => $price,
                'requires_card' => $pricing === ToolPricingModel::Paid,
                'spanish_support' => $spanish,
                'latam_payments' => true,
                'platforms' => [ToolPlatform::Web->value, ToolPlatform::Mac->value, ToolPlatform::Windows->value],
                'privacy_notes' => 'Revisa su política: algunos planes usan tus datos para mejorar el modelo.',
                'deal_notes' => $index === 0 ? 'Prueba gratis del plan de pago durante 7 días' : null,
                'deal_expires_at' => $index === 0 ? now()->addMonth()->toDateString() : null,
                'tested_at' => now()->subDays(10 + $index)->toDateString(),
                'reviewed_at' => now()->subDays(2)->toDateString(),
                'rating' => $rating,
                'status' => ToolStatus::Published,
                'published_at' => now()->subDays(5 + $index),
                'is_featured' => $featured,
                'sort_order' => $index,
            ]);
            $tool->secondaryCategories()->sync(collect($secondary)->map(fn (string $key): int => $categories[$key]->id)->all());
            $models[$slug] = $tool;
        }

        $alternatives = [
            'chatgpt' => ['claude'],
            'claude' => ['chatgpt'],
            'ideogram' => ['midjourney', 'canva'],
            'midjourney' => ['ideogram'],
            'capcut' => ['descript'],
            'n8n' => ['make', 'zapier'],
            'make' => ['n8n', 'zapier'],
            'zapier' => ['make', 'n8n'],
        ];
        foreach ($alternatives as $slug => $others) {
            $models[$slug]->alternatives()->sync(collect($others)->mapWithKeys(fn (string $other, int $order): array => [$models[$other]->id => ['sort_order' => $order]])->all());
        }

        $this->seedTemplatesAndWorkflows($categories->all(), $models);
        $this->seedGuides();
    }

    private function seedGuides(): void
    {
        $topics = collect([['ia', 'IA'], ['tutoriales', 'Tutoriales'], ['comparativas', 'Comparativas']])
            ->mapWithKeys(fn (array $row): array => [$row[0] => PostCategory::query()->updateOrCreate(['slug' => $row[0]], ['name' => $row[1]])]);

        $guides = [
            ['que-modelo-de-ia-usar', 'Qué modelo de IA usar para cada tarea', 'Una guía viva: qué modelo recomendamos para escribir, programar, analizar documentos o crear imágenes.', ['ia']],
            ['empieza-con-la-ia', 'Empieza con la IA en tu trabajo: guía para principiantes', 'Lo mínimo que necesitas saber para usar la IA con criterio desde hoy, sin tecnicismos.', ['ia', 'tutoriales']],
            ['chatgpt-vs-claude', 'ChatGPT vs Claude: cuál elegir para tu trabajo', 'Comparamos los dos asistentes en tareas reales de freelance: escribir, resumir y responder clientes.', ['comparativas', 'ia']],
            ['ia-local-ollama', 'IA local con Ollama: privacidad sin pagar suscripciones', 'Cómo usar modelos de IA en tu propio ordenador y cuándo merece la pena.', ['ia', 'tutoriales']],
        ];
        foreach ($guides as $index => [$slug, $title, $excerpt, $topicKeys]) {
            $post = Post::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => '<p><strong>Contenido de ejemplo.</strong> Aquí irá la guía real.</p><h2>Lo esencial</h2><p>'.str_repeat('Texto de ejemplo para previsualizar el diseño de lectura. ', 20).'</p><h2>Paso a paso</h2><ul><li>Primer punto de ejemplo.</li><li>Segundo punto de ejemplo.</li></ul><blockquote>Una cita destacada de ejemplo.</blockquote><p>'.str_repeat('Más texto de ejemplo para el cuerpo del artículo. ', 25).'</p>',
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(2 + $index * 3),
            ]);
            $post->categories()->sync(collect($topicKeys)->map(fn (string $key): int => $topics[$key]->id)->all());
        }
    }

    /**
     * @param  array<string, ToolCategory>  $categories
     * @param  array<string, Tool>  $tools
     */
    private function seedTemplatesAndWorkflows(array $categories, array $tools): void
    {
        $templates = [];
        $templateRows = [
            ['guion-video-corto', 'Guion para video corto de 60 segundos', 'Convierte una idea en un guion con gancho, desarrollo y llamada a la acción.', TemplateType::Prompt, TemplateFormat::Text,
                "Actúa como guionista de videos cortos para redes.\n\nEscribe un guion de 60 segundos sobre [tema] para [público].\n\nEstructura:\n1. Gancho en los primeros 3 segundos.\n2. Tres ideas concretas, una frase cada una.\n3. Cierre con una llamada a la acción: [acción].\n\nTono: cercano y directo. Indica entre corchetes qué se ve en pantalla.", ['[tema]', '[público]', '[acción]'], ['chatgpt', 'claude'], true],
            ['respuesta-correo-cliente', 'Respuesta profesional a un cliente', 'Redacta respuestas claras y amables a correos de clientes en segundos.', TemplateType::Prompt, TemplateFormat::Text,
                "Eres mi asistente. Responde a este correo de un cliente de forma profesional, amable y breve.\n\nContexto de mi negocio: [contexto]\nLo que quiero comunicar: [mensaje]\n\nCorreo del cliente:\n\"\"\"\n[correo]\n\"\"\"", ['[contexto]', '[mensaje]', '[correo]'], ['chatgpt', 'claude'], false],
            ['instrucciones-asistente-freelance', 'Instrucciones para tu asistente de freelance', 'Instrucciones de sistema para que la IA conozca tu negocio y responda siempre con tu estilo.', TemplateType::SystemInstructions, TemplateFormat::Markdown,
                "# Rol\nEres el asistente de [nombre], freelance de [servicio].\n\n# Estilo\n- Tutea al cliente.\n- Frases cortas, sin tecnicismos.\n- Nunca prometas plazos sin confirmarlo.\n\n# Precios\n[tarifas]", ['[nombre]', '[servicio]', '[tarifas]'], ['chatgpt', 'claude'], true],
            ['n8n-formulario-a-hoja', 'Automatización: formulario a hoja de cálculo con resumen IA', 'Flujo de n8n que guarda cada formulario en una hoja y resume la petición con IA.', TemplateType::Automation, TemplateFormat::Json,
                json_encode(['name' => 'Formulario a hoja con resumen', 'nodes' => [['name' => 'Webhook', 'type' => 'n8n-nodes-base.webhook'], ['name' => 'Resumen IA', 'type' => '@n8n/n8n-nodes-langchain.openAi'], ['name' => 'Google Sheets', 'type' => 'n8n-nodes-base.googleSheets']], 'connections' => new \stdClass], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), [], ['n8n'], false],
        ];
        foreach ($templateRows as $index => [$slug, $title, $summary, $type, $format, $content, $variables, $toolSlugs, $featured]) {
            $template = Template::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title, 'summary' => $summary, 'type' => $type, 'format' => $format, 'content' => $content,
                'usage' => '<p><strong>Contenido de ejemplo.</strong> Copia la plantilla, sustituye los huecos entre corchetes y pégala en la herramienta.</p>',
                'example_output' => 'Aquí irá un ejemplo real del resultado que obtuvimos.',
                'variables' => $variables, 'tested_at' => now()->subDays(6 + $index)->toDateString(),
                'status' => PublicationStatus::Published, 'published_at' => now()->subDays(4 + $index), 'is_featured' => $featured, 'sort_order' => $index,
            ]);
            $template->tools()->sync(collect($toolSlugs)->map(fn (string $toolSlug): int => $tools[$toolSlug]->id)->all());
            $templates[$slug] = $template;
        }

        $workflowRows = [
            ['video-redes-20-minutos', 'Cómo creo un video para redes en 20 minutos', 'De la idea al video subtitulado listo para publicar, con IA y herramientas gratis.', 'Un video vertical de 60 segundos, con guion, subtítulos y miniatura.', 'video', [WorkflowProfession::Creator, WorkflowProfession::Marketing, WorkflowProfession::Freelancer], WorkflowDifficulty::Basic, 20, 0, true, [
                ['Escribe el guion con IA', 'chatgpt', 'guion-video-corto'],
                ['Graba y edita en CapCut', 'capcut', null],
                ['Crea la miniatura con texto', 'ideogram', null],
            ]],
            ['responder-clientes-rapido', 'Responde correos de clientes en la mitad de tiempo', 'Un asistente con tus instrucciones y una plantilla de respuesta para no empezar de cero.', 'Respuestas consistentes y con tu estilo en menos de un minuto por correo.', 'escribir', [WorkflowProfession::Freelancer, WorkflowProfession::SmallBusiness, WorkflowProfession::Sales], WorkflowDifficulty::Basic, 15, 20, false, [
                ['Configura las instrucciones del asistente', 'claude', 'instrucciones-asistente-freelance'],
                ['Pega el correo y usa la plantilla de respuesta', 'claude', 'respuesta-correo-cliente'],
                ['Revisa y envía', null, null],
            ]],
            ['formularios-a-hoja-con-ia', 'Organiza las solicitudes de tu web automáticamente', 'Cada formulario llega a una hoja de cálculo con un resumen hecho por IA.', 'Todas las solicitudes en una hoja, resumidas y listas para priorizar.', 'automatizacion', [WorkflowProfession::SmallBusiness, WorkflowProfession::Freelancer], WorkflowDifficulty::Intermediate, 45, 0, false, [
                ['Importa la automatización en n8n', 'n8n', 'n8n-formulario-a-hoja'],
                ['Conecta tu formulario y tu hoja', 'n8n', null],
                ['Prueba con una solicitud real', null, null],
            ]],
        ];
        foreach ($workflowRows as $index => [$slug, $title, $summary, $goal, $category, $professions, $difficulty, $minutes, $cost, $featured, $steps]) {
            $workflow = Workflow::query()->updateOrCreate(['slug' => $slug], [
                'title' => $title, 'summary' => $summary, 'goal' => $goal,
                'intro' => '<p><strong>Contenido de ejemplo.</strong> Aquí irá cuándo usar este flujo y qué necesitas tener listo.</p>',
                'result' => '<p>Aquí irá lo que obtuvimos en nuestra prueba real, con capturas.</p>',
                'tool_category_id' => $categories[$category]->id,
                'professions' => array_map(fn (WorkflowProfession $profession): string => $profession->value, $professions),
                'difficulty' => $difficulty, 'time_minutes' => $minutes, 'cost_usd' => $cost,
                'tested_at' => now()->subDays(5 + $index)->toDateString(),
                'status' => PublicationStatus::Published, 'published_at' => now()->subDays(3 + $index), 'is_featured' => $featured, 'sort_order' => $index,
            ]);
            $workflow->steps()->delete();
            foreach ($steps as $position => [$stepTitle, $toolSlug, $templateSlug]) {
                $workflow->steps()->create([
                    'position' => $position, 'title' => $stepTitle,
                    'body' => '<p>Instrucciones de ejemplo para este paso.</p>',
                    'tool_id' => $toolSlug ? $tools[$toolSlug]->id : null,
                    'template_id' => $templateSlug ? $templates[$templateSlug]->id : null,
                ]);
            }
        }
    }
}
