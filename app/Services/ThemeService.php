<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Theme;

class ThemeService
{
    /** Columns a caller is allowed to set on a theme. */
    private const WRITABLE = ['name', 'primary_color', 'secondary_color', 'tertiary_color'];

    /**
     * The palettes a workspace can start from.
     *
     * @return array<int, array<string, string>>
     */
    public function presets(): array
    {
        return config('themes.presets', []);
    }

    /**
     * Colours for a named preset, falling back to the configured default when the name is
     * unknown or absent.
     *
     * @return array{name: string, primary_color: string, secondary_color: string, tertiary_color: string}
     */
    public function presetColors(?string $name = null): array
    {
        $presets = collect($this->presets());

        $preset = ($name ? $presets->firstWhere('name', $name) : null)
            ?? $presets->firstWhere('name', config('themes.default'))
            ?? $presets->first()
            ?? [];

        return [
            'name' => $preset['name'] ?? 'Default',
            'primary_color' => $preset['primary_color'] ?? '#F74634',
            'secondary_color' => $preset['secondary_color'] ?? '#1F1F1F',
            'tertiary_color' => $preset['tertiary_color'] ?? '#5D5D5D',
        ];
    }

    /**
     * Create or update the tenant's own theme.
     *
     * On create the palette is completed from `preset` (or the default) because the colour
     * columns are NOT NULL. On update only the keys actually supplied are written, so a
     * partial edit cannot blank out the colours it left out.
     *
     * @param  array{preset?:string|null, name?:string, primary_color?:string, secondary_color?:string, tertiary_color?:string}  $data
     */
    public function modifyTenantTheme(Tenant $tenant, array $data): Theme
    {
        $attributes = array_intersect_key($data, array_flip(self::WRITABLE));

        // `tertiary_color` is the only nullable colour; a null for any of the others means
        // "not supplied" and must not reach the NOT NULL column.
        foreach (['name', 'primary_color', 'secondary_color'] as $required) {
            if (array_key_exists($required, $attributes) && $attributes[$required] === null) {
                unset($attributes[$required]);
            }
        }

        $theme = $tenant->theme()->first();

        if (! $theme) {
            $preset = $this->presetColors($data['preset'] ?? null);

            return $tenant->theme()->create([
                'name' => $attributes['name'] ?? $tenant->name.' theme',
                'primary_color' => $attributes['primary_color'] ?? $preset['primary_color'],
                'secondary_color' => $attributes['secondary_color'] ?? $preset['secondary_color'],
                'tertiary_color' => $attributes['tertiary_color'] ?? $preset['tertiary_color'],
            ]);
        }

        if (isset($data['preset'])) {
            $preset = $this->presetColors($data['preset']);
            unset($preset['name']);

            // An explicit preset sets the palette; any colours sent alongside it still win.
            $attributes += $preset;
        }

        if ($attributes !== []) {
            $theme->fill($attributes)->save();
        }

        return $theme;
    }
}
