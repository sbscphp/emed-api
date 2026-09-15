<?php

/*
|--------------------------------------------------------------------------
| Workspace theme presets
|--------------------------------------------------------------------------
|
| Every row in the landlord `themes` table belongs to exactly one tenant
| (`themes.tenant_id` is unique and NOT NULL). The palettes below are the
| starting points offered at signup and in company settings — they are
| constants, not rows, so choosing one copies its colours onto the tenant's
| own theme rather than pointing several tenants at a shared record.
|
*/

return [

    /*
     | Preset used when a caller supplies neither a preset name nor colours.
     */
    'default' => 'Coral',

    'presets' => [
        [
            'name' => 'Coral',
            'primary_color' => '#F74634',
            'secondary_color' => '#1F1F1F',
            'tertiary_color' => '#5D5D5D',
        ],
        [
            'name' => 'Ocean',
            'primary_color' => '#2563EB',
            'secondary_color' => '#1E3A5F',
            'tertiary_color' => '#64748B',
        ],
        [
            'name' => 'Emerald',
            'primary_color' => '#10B981',
            'secondary_color' => '#064E3B',
            'tertiary_color' => '#6B7280',
        ],
        [
            'name' => 'Purple',
            'primary_color' => '#8B5CF6',
            'secondary_color' => '#2E1065',
            'tertiary_color' => '#7C7C8A',
        ],
        [
            'name' => 'Amber',
            'primary_color' => '#F59E0B',
            'secondary_color' => '#451A03',
            'tertiary_color' => '#78716C',
        ],
    ],

];
