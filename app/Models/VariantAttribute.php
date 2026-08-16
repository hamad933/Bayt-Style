<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantAttribute extends Model
{
    protected $fillable = ['code', 'name_ar', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(VariantAttributeOption::class)->orderBy('sort_order');
    }
}
