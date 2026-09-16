<?php

namespace App\Http\Controllers\V1\Theme;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Responser\JsonResponser;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function __construct(protected ThemeService $service) {}

    /**
     * The palettes a workspace can start from, plus the current workspace's own theme when
     * a tenant is resolvable from the request.
     */
    public function all()
    {
        try {
            $data = [
                'presets' => $this->service->presets(),
                'theme' => Tenant::current()?->themeData(),
            ];

            return JsonResponser::send(false, 'Theme record(s) found successfully.', $data, 200);
        } catch (\Throwable $th) {
            report($th);

            return JsonResponser::send(true, 'Something went wrong, please try again.', [], 500);
        }
    }

    /**
     * Update the current workspace's own theme. Never touches another tenant's palette.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'preset' => ['sometimes', 'string', Rule::in(array_column($this->service->presets(), 'name'))],
            'primary_color' => ['sometimes', 'string', 'max:7'],
            'secondary_color' => ['sometimes', 'string', 'max:7'],
            'tertiary_color' => ['sometimes', 'string', 'max:7'],
        ]);

        $tenant = Tenant::current();

        if (!$tenant) {
            return JsonResponser::send(true, 'No workspace resolved for this request.', [], 404);
        }

        try {
            $data = $this->service->modifyTenantTheme($tenant, $validated);

            return JsonResponser::send(false, 'Theme updated successfully.', $data, 200);
        } catch (\Throwable $th) {
            report($th);

            return JsonResponser::send(true, 'Something went wrong, please try again.', [], 500);
        }
    }
}
