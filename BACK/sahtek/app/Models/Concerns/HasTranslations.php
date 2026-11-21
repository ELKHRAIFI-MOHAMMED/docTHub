<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Translatable content stored in the polymorphic `translations` table.
 * Base columns hold the fallback language (English); other locales override per field.
 *
 * Models using this trait declare: protected array $translatable = ['name', ...];
 */
trait HasTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    /** Value of $field in $locale (current app locale by default), falling back to the base column. */
    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $base = $this->getAttribute($field);

        if ($locale === config('app.fallback_locale', 'en') || ! in_array($field, $this->translatableFields(), true)) {
            return $base;
        }

        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        $hit = $translations->first(fn (Translation $t) => $t->locale === $locale && $t->field === $field);

        return ($hit && $hit->value !== '') ? $hit->value : $base;
    }

    /**
     * @param  array<string, array<string, ?string>>  $translations  ['fr' => ['name' => '...'], 'ar' => [...]]
     */
    public function syncTranslations(array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            foreach ((array) $fields as $field => $value) {
                if (! in_array($field, $this->translatableFields(), true)) {
                    continue;
                }
                if ($value === null || trim((string) $value) === '') {
                    $this->translations()->where(['locale' => $locale, 'field' => $field])->delete();
                    continue;
                }
                $this->translations()->updateOrCreate(['locale' => $locale, 'field' => $field], ['value' => $value]);
            }
        }
        $this->unsetRelation('translations');
    }

    /** All translations grouped as ['fr' => ['name' => '...']] (admin editing). */
    public function translationsByLocale(): array
    {
        $out = [];
        foreach ($this->translations()->get() as $t) {
            $out[$t->locale][$t->field] = $t->value;
        }

        return $out;
    }

    /** Eager-load only the current locale's translations (avoids N+1). */
    public function scopeWithLocaleTranslations(Builder $query, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();

        return $query->with(['translations' => fn ($q) => $q->where('locale', $locale)]);
    }
}
