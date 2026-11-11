<?php

namespace App\Services;

class MediaImageGuidelines
{
    /** @return list<string> */
    public static function contexts(): array
    {
        return ['content', 'portfolio', 'gallery', 'service', 'blog', 'seo', 'logo', 'favicon', 'testimonial', 'assistant_avatar'];
    }

    /** @return array{label: string, width: int, height: int, ratio: string, crop: bool, description: string} */
    public static function forContext(string $context): array
    {
        [$label, $width, $height, $ratio, $crop, $description] = match ($context) {
            'portfolio' => ['Portada del proyecto', 1200, 675, '16:9', true, 'Las tarjetas muestran un recorte panorámico centrado. Mantén el contenido importante lejos de los bordes.'],
            'gallery' => ['Galería del proyecto', 1200, 675, '16:9', true, 'Las miniaturas usan un recorte panorámico centrado. El original conserva su proporción.'],
            'service' => ['Imagen del servicio', 1200, 800, '3:2', false, 'Preferentemente horizontal. La imagen conserva su proporción en la página de detalle.'],
            'blog' => ['Portada del artículo', 1200, 800, '3:2', false, 'Preferentemente horizontal. La imagen conserva su proporción en la página del artículo.'],
            'seo' => ['Imagen para compartir enlaces', 1200, 630, '1.91:1', true, 'Deja margen alrededor de textos y logos: la vista previa puede recortarse al compartir el enlace.'],
            'logo' => ['Logo del sitio', 600, 200, '3:1', false, 'Preferentemente PNG o WebP con fondo transparente. Conserva la proporción de tu logo.'],
            'favicon' => ['Icono de la pestaña', 512, 512, '1:1', false, 'Usa un PNG cuadrado, con un símbolo simple y reconocible en tamaños pequeños.'],
            'testimonial' => ['Foto del testimonio', 400, 400, '1:1', false, 'Preferentemente cuadrada, con el rostro centrado y espacio alrededor.'],
            'assistant_avatar' => ['Foto del asistente', 200, 200, '1:1', true, 'Se recorta al centro a 200 × 200 px y se muestra circular. Mantén el rostro centrado.'],
            default => ['Imagen de contenido', 1200, 800, '3:2', false, 'La medida es una recomendación. Puedes reutilizar imágenes de otras proporciones.'],
        };

        return compact('label', 'width', 'height', 'ratio', 'crop', 'description');
    }

    public static function recommendation(string $context): string
    {
        $guide = self::forContext($context);

        return 'Recomendado: '.$guide['width'].' × '.$guide['height'].' px ('.$guide['ratio'].'). '.$guide['description'];
    }
}
