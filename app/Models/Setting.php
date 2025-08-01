<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'value',
        'slug',
    ];

    /**
     * Get setting value by slug
     */
    public static function getValue($slug, $default = null)
    {
        $setting = self::where('slug', $slug)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting value by slug
     */
    public static function setValue($slug, $value)
    {
        return self::updateOrCreate(
            ['slug' => $slug],
            ['value' => $value]
        );
    }

    /**
     * Get multiple settings by slugs
     */
    public static function getMultiple(array $slugs)
    {
        return self::whereIn('slug', $slugs)->pluck('value', 'slug')->toArray();
    }
}
