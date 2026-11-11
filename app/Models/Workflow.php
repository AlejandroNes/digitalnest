<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\RedirectsChangedSlug;
use App\PublicationStatus;
use App\Services\ContentHtml;
use App\Services\InternalUrl;
use App\ToolStatus;
use App\WorkflowDifficulty;
use App\WorkflowProfession;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'slug', 'summary', 'goal', 'intro', 'result', 'image_id', 'tool_category_id', 'professions',
    'difficulty', 'time_minutes', 'cost_usd', 'tested_at', 'status', 'published_at', 'is_featured', 'sort_order',
    'seo_title', 'seo_description', 'seo_image_id', 'seo_canonical', 'seo_noindex',
])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasContentImage, HasFactory, HasPublication, HasSeo, HasSlug, RedirectsChangedSlug;

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
        'sort_order' => 0,
        'seo_noindex' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'difficulty' => WorkflowDifficulty::class,
            'professions' => 'array',
            'time_minutes' => 'integer',
            'cost_usd' => 'decimal:2',
            'tested_at' => 'date',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'seo_noindex' => 'boolean',
        ];
    }

    public static function publicPathsForSlug(string $slug): array
    {
        return ['/flujos/'.$slug];
    }

    protected function intro(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value !== null ? ContentHtml::sanitize($value) : null);
    }

    protected function result(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value !== null ? ContentHtml::sanitize($value) : null);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return list<WorkflowProfession>
     */
    public function professionList(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?WorkflowProfession => is_string($value) ? WorkflowProfession::tryFrom($value) : null,
            $this->professions ?? []
        )));
    }

    public function durationLabel(): string
    {
        $minutes = (int) $this->time_minutes;

        return $minutes >= 60
            ? intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '')
            : $minutes.' min';
    }

    public function costLabel(): string
    {
        if ($this->cost_usd === null) {
            return 'Sin calcular';
        }
        $amount = (float) $this->cost_usd;

        return $amount <= 0 ? 'Gratis' : '$'.(fmod($amount, 1.0) === 0.0 ? number_format($amount, 0) : number_format($amount, 2)).'/mes';
    }

    /**
     * Published tools used in the steps, in step order and without duplicates.
     *
     * @return Collection<int, Tool>
     */
    public function publishedTools(): Collection
    {
        return (new Collection($this->steps->pluck('tool')->filter()->unique('id')->values()->all()))
            ->filter(fn (Tool $tool): bool => $tool->status === ToolStatus::Published && $tool->published_at?->lte(now()))
            ->values();
    }

    /**
     * Published templates used in the steps, in step order and without duplicates.
     *
     * @return Collection<int, Template>
     */
    public function publishedTemplates(): Collection
    {
        return (new Collection($this->steps->pluck('template')->filter()->unique('id')->values()->all()))
            ->filter(fn (Template $template): bool => $template->isPublished())
            ->values();
    }

    #[Scope]
    protected function forProfession(Builder $query, WorkflowProfession $profession): Builder
    {
        return $query->whereJsonContains('professions', $profession->value);
    }

    #[Scope]
    protected function usingTool(Builder $query, Tool $tool): Builder
    {
        return $query->whereHas('steps', fn (Builder $query): Builder => $query->where('tool_id', $tool->id));
    }

    /**
     * Schema.org HowTo data.
     *
     * @return array<string, mixed>
     */
    public function structuredData(string $url): array
    {
        $image = $this->imageUrl();
        if ($image && ! preg_match('~^https?://~i', $image)) {
            $image = InternalUrl::absolute($image);
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $this->title,
            'description' => $this->summary,
            'url' => $url,
            'image' => $image,
            'totalTime' => 'PT'.max(1, (int) $this->time_minutes).'M',
            'estimatedCost' => $this->cost_usd !== null ? ['@type' => 'MonetaryAmount', 'currency' => 'USD', 'value' => (string) $this->cost_usd] : null,
            'tool' => $this->publishedTools()->map(fn (Tool $tool): array => ['@type' => 'HowToTool', 'name' => $tool->name])->all() ?: null,
            'step' => $this->steps->values()->map(fn (WorkflowStep $step, int $index): array => array_filter([
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => $step->title,
                'text' => trim(html_entity_decode(strip_tags((string) $step->body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: $step->title,
                'url' => $url.'#paso-'.($index + 1),
            ]))->all(),
        ], fn (mixed $value): bool => $value !== null);
    }
}
