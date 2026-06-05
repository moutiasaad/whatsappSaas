<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalPage extends Model
{
    protected $fillable = ['slug', 'locale', 'title', 'content'];

    public static function forSlug(string $slug, string $locale): ?self
    {
        return self::where('slug', $slug)
            ->where('locale', $locale)
            ->first()
            ?? self::where('slug', $slug)
                ->where('locale', 'en')
                ->first();
    }
}
