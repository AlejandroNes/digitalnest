<?php

namespace Database\Seeders;

use App\Models\ToolCategory;
use Illuminate\Database\Seeder;

/**
 * Initial sector taxonomy. Idempotent and safe in production: existing sectors keep
 * their edited texts and only receive the icon, colour or parent when those are empty.
 */
class SectorSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string, icon: string, color: string, children: array<string, string>}>
     */
    private const SECTORS = [
        'ia' => ['name' => 'Inteligencia artificial', 'icon' => 'sparkles', 'color' => '#8B5CF6',
            'description' => 'Asistentes, chatbots y modelos de IA para trabajar más rápido.',
            'children' => ['chatbots' => 'Chatbots y asistentes', 'escribir' => 'Escritura con IA', 'agentes' => 'Agentes y automatización con IA']],
        'video' => ['name' => 'Vídeo', 'icon' => 'film', 'color' => '#EF4444',
            'description' => 'Edita, genera y subtitula vídeos sin ser editor profesional.',
            'children' => ['editores-video' => 'Editores de vídeo', 'video-ia' => 'Generación de vídeo con IA', 'grabacion-pantalla' => 'Grabación de pantalla']],
        'audio' => ['name' => 'Audio', 'icon' => 'musical-note', 'color' => '#F59E0B',
            'description' => 'Voz, música, podcasts y transcripción.',
            'children' => ['voz-ia' => 'Voz y doblaje con IA', 'musica' => 'Música', 'transcripcion' => 'Transcripción', 'podcast' => 'Podcast']],
        'imagen' => ['name' => 'Imágenes', 'icon' => 'photo', 'color' => '#EC4899',
            'description' => 'Genera, edita y optimiza imágenes.',
            'children' => ['imagen-ia' => 'Generación de imágenes con IA', 'editores-foto' => 'Editores de fotos', 'stock' => 'Bancos de imágenes']],
        'diseno' => ['name' => 'Diseño', 'icon' => 'swatch', 'color' => '#14B8A6',
            'description' => 'Diseño gráfico, UI y recursos creativos.',
            'children' => ['diseno-grafico' => 'Diseño gráfico', 'diseno-ui' => 'Diseño UI/UX', 'fuentes-iconos' => 'Fuentes e iconos']],
        'hosting' => ['name' => 'Hosting y dominios', 'icon' => 'server-stack', 'color' => '#3B82F6',
            'description' => 'Alojamiento web, cloud, dominios y creadores de sitios.',
            'children' => ['hosting-web' => 'Hosting web', 'vps-cloud' => 'VPS y cloud', 'dominios' => 'Dominios', 'creadores-web' => 'Creadores de webs']],
        'vpn-seguridad' => ['name' => 'VPN y seguridad', 'icon' => 'shield-check', 'color' => '#22C55E',
            'description' => 'Privacidad, contraseñas y protección online.',
            'children' => ['vpn' => 'VPN', 'gestores-contrasenas' => 'Gestores de contraseñas', 'antivirus' => 'Antivirus']],
        'productividad' => ['name' => 'Productividad', 'icon' => 'bolt', 'color' => '#6366F1',
            'description' => 'Organiza proyectos, notas, tareas y reuniones.',
            'children' => ['notas' => 'Notas y documentos', 'gestion-proyectos' => 'Gestión de proyectos', 'automatizacion' => 'Automatización']],
        'desarrollo' => ['name' => 'Desarrollo', 'icon' => 'code-bracket', 'color' => '#0EA5E9',
            'description' => 'Editores, asistentes de código y herramientas para developers.',
            'children' => ['programar' => 'Asistentes de código', 'devops' => 'DevOps y despliegue', 'apis' => 'APIs y bases de datos']],
        'marketing' => ['name' => 'Marketing', 'icon' => 'megaphone', 'color' => '#F97316',
            'description' => 'SEO, email, redes sociales y analítica.',
            'children' => ['seo' => 'SEO', 'email-marketing' => 'Email marketing', 'redes-sociales' => 'Redes sociales']],
        'educacion' => ['name' => 'Educación', 'icon' => 'academic-cap', 'color' => '#A855F7',
            'description' => 'Cursos, idiomas y plataformas de aprendizaje.',
            'children' => ['cursos' => 'Cursos online', 'idiomas' => 'Idiomas']],
        'negocio' => ['name' => 'Negocio y finanzas', 'icon' => 'briefcase', 'color' => '#64748B',
            'description' => 'Facturación, CRM, e-commerce y herramientas para empresas.',
            'children' => ['ecommerce' => 'E-commerce', 'crm' => 'CRM y ventas', 'facturacion' => 'Facturación']],
    ];

    public function run(): void
    {
        $order = 0;

        foreach (self::SECTORS as $slug => $sector) {
            $parent = $this->upsert($slug, $sector['name'], $sector['description'], $sector['icon'], $sector['color'], null, $order++);

            $childOrder = 0;
            foreach ($sector['children'] as $childSlug => $childName) {
                $this->upsert($childSlug, $childName, null, null, null, $parent, $childOrder++);
            }
        }
    }

    private function upsert(string $slug, string $name, ?string $description, ?string $icon, ?string $color, ?ToolCategory $parent, int $order): ToolCategory
    {
        $category = ToolCategory::query()->firstOrNew(['slug' => $slug]);

        if (! $category->exists) {
            $category->fill(['name' => $name, 'description' => $description, 'sort_order' => $order, 'is_active' => true]);
        }

        $category->icon ??= $icon;
        $category->accent_color ??= $color;
        if ($category->parent_id === null && $parent !== null && $parent->isNot($category)) {
            $category->parent_id = $parent->id;
        }
        $category->save();

        return $category;
    }
}
