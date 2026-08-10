<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name_ar', 'slug', 'description_ar', 'sort_order'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
