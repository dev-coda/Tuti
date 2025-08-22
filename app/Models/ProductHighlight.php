<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductHighlight extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'product_id',
        'position',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the category this highlight belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the product being highlighted
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope for active highlights
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for highlights in a specific category
     */
    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope for highlights ordered by position
     */
    public function scopeOrderedByPosition($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Get available positions for a category (1-4)
     */
    public static function getAvailablePositions($categoryId): array
    {
        $usedPositions = self::forCategory($categoryId)->active()->pluck('position')->toArray();
        $allPositions = [1, 2, 3, 4];
        return array_diff($allPositions, $usedPositions);
    }

    /**
     * Validation rules for highlighting
     */
    public static function getValidationRules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'product_id' => 'required|exists:products,id',
            'position' => 'required|integer|min:1|max:4',
            'active' => 'boolean',
        ];
    }
}
