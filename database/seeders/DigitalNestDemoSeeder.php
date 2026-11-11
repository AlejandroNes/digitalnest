<?php

namespace Database\Seeders;

use App\Models\Tool;
use App\Models\ToolCategory;
use App\ToolPlatform;
use App\ToolPricingModel;
use App\ToolSpanishSupport;
use App\ToolStatus;
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
    }
}
