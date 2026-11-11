<?php

namespace App\Models;

use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RedirectsChangedSlug;
use App\PublicationStatus;
use App\Services\ContentHtml;
use App\TemplateFormat;
use App\TemplateType;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'slug', 'summary', 'type', 'format', 'content', 'usage', 'example_output', 'variables',
    'tested_at', 'status', 'published_at', 'is_featured', 'sort_order',
    'seo_title', 'seo_description', 'seo_image_id', 'seo_canonical', 'seo_noindex',
])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, HasPublication, HasSeo, HasSlug, RedirectsChangedSlug;

    protected $attributes = [
        'status' => 'draft',
        'format' => 'text',
        'is_featured' => false,
        'sort_order' => 0,
        'seo_noindex' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'type' => TemplateType::class,
            'format' => TemplateFormat::class,
            'variables' => 'array',
            'tested_at' => 'date',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'seo_noindex' => 'boolean',
        ];
    }

    public static function publicPathsForSlug(string $slug): array
    {
        return ['/plantillas/'.$slug];
    }

    protected function usage(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value !== null ? ContentHtml::sanitize($value) : null);
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'template_tool');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    /**
     * @return list<string>
     */
    public function variableList(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim(is_array($item) ? (string) ($item['text'] ?? '') : (string) $item),
            is_array($this->variables) ? $this->variables : []
        ), fn (string $text): bool => $text !== ''));
    }

    public function downloadFilename(): string
    {
        return $this->slug.'.'.($this->format ?? TemplateFormat::Text)->extension();
    }
}
