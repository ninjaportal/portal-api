<?php

namespace NinjaPortal\Api\Http\Requests\V1\Admin\Concerns;

trait BuildsTranslatableRules
{
    /**
     * Build validation rules for locale-keyed translation payloads.
     *
     * @param  array  $attributes
     * @param  array|null  $locales
     * @param  array  $overrides
     * @return array
     */
    protected function translatableRules(array $attributes, ?array $locales = null, array $overrides = []): array
    {
        $locales = $locales ?? config('ninjaportal.translatable.locales', ['en', 'ar']);

        $rules = [];
        foreach ($attributes as $attribute) {
            $rules[$attribute] = $overrides[$attribute] ?? ['sometimes', 'nullable', 'string'];
        }

        foreach ($locales as $locale) {
            $rules[$locale] = ['sometimes', 'array'];
            foreach ($attributes as $attribute) {
                $rules["{$locale}.{$attribute}"] = $overrides[$attribute] ?? ['sometimes', 'nullable', 'string'];
            }
        }

        return $rules;
    }
}
