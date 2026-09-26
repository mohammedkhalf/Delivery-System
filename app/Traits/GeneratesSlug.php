<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Transliterator;

trait GeneratesSlug
{
    public function generateSlug(?string $name): string
    {
        $baseSlug = $this->slugify($name ?? '');

        if ($baseSlug === '') {
            $baseSlug = 'item';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            static::where('slug', $slug)
                ->when($this->getKey(), fn ($query) => $query->where($this->getKeyName(), '!=', $this->getKey()))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . (++$counter);
        }

        return $slug;
    }

    protected function slugify(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $slug = Str::slug($name);
        if ($slug !== '') {
            return $slug;
        }

        if (class_exists(Transliterator::class)) {
            $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($transliterator) {
                $romanized = $transliterator->transliterate($name);
                if (\is_string($romanized) && ($slug = Str::slug($romanized)) !== '') {
                    return $slug;
                }
            }
        }

        $fallback = preg_replace('/[^\p{L}\p{N}]+/u', '-', Str::lower($name));

        return trim($fallback ?? '', '-');
    }
}
