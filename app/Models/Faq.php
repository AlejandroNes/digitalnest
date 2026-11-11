<?php

namespace App\Models;

use App\Models\Concerns\HasOrderedActivity;
use App\Services\ContentHtml;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question', 'answer', 'sort_order', 'is_active'])]
class Faq extends Model
{
    use HasFactory, HasOrderedActivity;

    protected $attributes = ['sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    protected function answer(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => ContentHtml::sanitize($value));
    }
}
