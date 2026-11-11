<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class PostCategory extends Model
{
    use HasFactory, HasSlug;

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_category_post');
    }
}
