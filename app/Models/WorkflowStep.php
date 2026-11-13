<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Services\ContentHtml;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_id', 'position', 'title', 'body', 'tool_id', 'template_id', 'image_id'])]
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasContentImage, HasFactory;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    protected function body(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value !== null ? ContentHtml::sanitize($value) : null);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
