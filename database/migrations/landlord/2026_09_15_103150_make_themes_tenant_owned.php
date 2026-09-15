<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Themes were a shared catalogue: several tenants pointed at the same `themes` row via
 * `tenants.theme_id`, so one workspace editing its colours rewrote the palette for every
 * other workspace on that preset. This flips ownership — each theme now belongs to exactly
 * one tenant — and moves the seeded palettes to config/themes.php as copy-from presets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('themes', 'tenant_id')) {
            Schema::table('themes', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });
        }

        $themeIndexes = Schema::getIndexes('themes');
        $hasTenantUniqueIndex = collect($themeIndexes)->contains(
            fn(array $index): bool => $index['name'] === 'themes_tenant_id_unique'
        );

        if (! $hasTenantUniqueIndex) {
            Schema::table('themes', function (Blueprint $table) {
                $table->unique('tenant_id');
            });
        }

        $defaults = $this->defaultColors();

        DB::table('tenants')->orderBy('id')->each(function ($tenant) use ($defaults) {
            $source = $tenant->theme_id
                ? DB::table('themes')->where('id', $tenant->theme_id)->first()
                : null;

            DB::table('themes')->updateOrInsert(
                ['tenant_id' => $tenant->id],
                [
                    'name' => $tenant->name . ' theme',
                    'primary_color' => $source->primary_color ?? $defaults['primary_color'],
                    'secondary_color' => $source->secondary_color ?? $defaults['secondary_color'],
                    'tertiary_color' => $source->tertiary_color ?? $defaults['tertiary_color'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        // Drop the pointer before deleting the presets it still references.
        if (Schema::hasColumn('tenants', 'theme_id')) {
            $tenantForeignKeys = Schema::getForeignKeys('tenants');
            $themeForeignKey = collect($tenantForeignKeys)->first(
                fn(array $foreignKey): bool => in_array('theme_id', $foreignKey['columns'], true)
            );

            Schema::table('tenants', function (Blueprint $table) use ($themeForeignKey) {
                if ($themeForeignKey) {
                    $table->dropForeign($themeForeignKey['name']);
                }

                $table->dropColumn('theme_id');
            });
        }

        DB::table('themes')->whereNull('tenant_id')->delete();

        Schema::table('themes', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('theme_id')->nullable()->constrained('themes');
        });

        DB::table('themes')->orderBy('id')->each(function ($theme) {
            DB::table('tenants')->where('id', $theme->tenant_id)->update(['theme_id' => $theme->id]);
        });

        Schema::table('themes', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }

    /**
     * @return array{primary_color: string, secondary_color: string, tertiary_color: string}
     */
    private function defaultColors(): array
    {
        $presets = collect(config('themes.presets', []));
        $preset = $presets->firstWhere('name', config('themes.default')) ?? $presets->first() ?? [];

        return [
            'primary_color' => $preset['primary_color'] ?? '#F74634',
            'secondary_color' => $preset['secondary_color'] ?? '#1F1F1F',
            'tertiary_color' => $preset['tertiary_color'] ?? '#5D5D5D',
        ];
    }
};
