<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plat extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'category',
        'is_available',
        'is_featured',
        'preparation_time',
        'ingredients',
        'allergens',
        'is_vegetarian',
        'is_vegan',
        'is_halal',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_vegetarian' => 'boolean',
        'is_vegan' => 'boolean',
        'is_halal' => 'boolean',
        'ingredients' => 'array',
        'allergens' => 'array',
    ];

    protected $appends = ['primary_image_url'];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PlatImage::class)->orderBy('sort_order');
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(TableReservation::class, 'reservation_plats')
            ->withPivot(['quantity', 'unit_price', 'total_price', 'notes'])
            ->withTimestamps();
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $primaryImage = $this->images()->where('is_primary', true)->first();
        if ($primaryImage) {
            return asset('storage/' . $primaryImage->image_path);
        }
        $firstImage = $this->images()->first();
        return $firstImage ? asset('storage/' . $firstImage->image_path) : null;
    }
}
