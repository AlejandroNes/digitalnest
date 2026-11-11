<?php

namespace App\Models;

use App\Models\Concerns\HasContentImage;
use App\Models\Concerns\HasOrderedActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'position', 'company', 'quote', 'image_id', 'sort_order', 'is_active'])]
class Testimonial extends Model
{
    use HasContentImage, HasFactory, HasOrderedActivity;

    protected $attributes = ['sort_order' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
