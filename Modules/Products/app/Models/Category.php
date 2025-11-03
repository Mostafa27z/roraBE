<?php

namespace Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Products\Database\Factories\CategoryFactory;
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    protected static function newFactory()
    {
        return CategoryFactory::new();
    }
}
