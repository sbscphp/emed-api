<?php

namespace App\Repositories\User;

use App\Models\AuditLog;
use App\Models\User;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SystemReportExport;
use App\Helpers\ExportHelper;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Class UserRepository
 * 
 * This class handles the database operations for the User model.
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Retrieve all users from the database.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all($filters = [], $search = null, $export = null, $paginate = true, $perPage = 20)
    {
        $tenantUuid = $filters['tenant_id'] ?? null;
        $tenant = Tenant::where('uuid', $tenantUuid)->first();

        if (!$tenant) {
            throw new \Exception('Invalid tenant.');
        }

        // Switch DB to tenant context
        $tenant->makeCurrent();
        $tenantId = $tenant->id;

        // Base query
        $query = User::query()
            ->select('users.*', 'tenant_users.status as tenant_status')
            ->join('tenant_users', 'tenant_users.user_id', '=', 'users.id')
            ->where('tenant_users.tenant_id', $tenantId)
            ->whereNull('tenant_users.deleted_at')
            ->with(['tenantUsers' => function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->whereNull('deleted_at');
            }]);

        // Search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $terms = explode(' ', $search);
                foreach ($terms as $term) {
                    $q->where(function ($q2) use ($term) {
                        $q2->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('fullname', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone_number', 'like', "%{$term}%");
                    });
                }
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('tenant_users.status', $filters['status']);
        }

        // Role filter (manual)
        if (!empty($filters['role'])) {
            $roleId = $filters['role'];
            $tenantDb = DB::connection('tenant')->getDatabaseName();

            $query->whereExists(function ($sub) use ($tenantDb, $tenant, $roleId) {
                $sub->select(DB::raw(1))
                    ->from("$tenantDb.role_user")
                    ->join("$tenantDb.roles", "roles.id", "=", "role_user.role_id")
                    ->whereRaw("role_user.user_id = users.id")
                    ->where("roles.tenant_id", $tenant->uuid)
                    ->where("role_user.role_id", $roleId);
            });
        }

        if (!empty($export)) {
            $users = $query->orderBy('users.id', 'DESC')->get();

            $exportData = $users->map(function ($user) use ($tenant) {

                // fetch tenant-specific role for export
                $role = $user->roles()
                    ->where('roles.tenant_id', $tenant->uuid)
                    ->first(['roles.name', 'roles.display_name']);

                return [
                    'Full Name'    => $user->fullname ?? ($user->first_name . ' ' . $user->last_name),
                    'Role'         => $role->display_name ?? $role->name ?? 'N/A',
                    'Email'        => $user->email,
                    'Status'       => ucfirst($user->tenant_status ?? 'Inactive'),
                ];
            })->toArray();

            return match (strtolower($export)) {
                'csv' => ExportHelper::streamCsv($exportData, null, 'users.csv'),
                'pdf' => ExportHelper::downloadPdf($exportData, 'users.pdf'),
                default => throw new \Exception('Invalid export format.'),
            };
        }

        // Fetch results
        $result = $paginate
            ? $query->orderBy('users.id', 'DESC')->paginate($perPage)
            : $query->orderBy('users.id', 'DESC')->get();

        // Attach tenant-specific `userRole` exactly like login does
        $result->getCollection()->transform(function ($user) use ($tenant) {

            // Fetch the user role for this tenant
            $userRole = $user->roles()
                ->where('roles.tenant_id', $tenant->uuid)
                ->first(['roles.id', 'roles.name', 'roles.display_name']);

            // Attach only userRole
            $user->userRole = $userRole;

            // Remove roles array completely
            unset($user->roles);

            return $user;
        });

        return $result;
    }

    /**
     * Create a new user in the database.
     * 
     * @param array $data
     * @return \App\Models\User
     */
    public function create(array $data)
    {
        return User::create($data);
    }

    /**
     * Update an existing user in the database.
     * 
     * @param array $data
     * @param int $id
     * @return \App\Models\User
     */
    public function update(array $data, $id)
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    /**
     * Delete a user from the database.
     * 
     * @param int $id
     * @return void
     */
    public function delete($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
    }

    /**
     * Find a user in the database by their ID.
     * 
     * @param int $id
     * @param array $selectAttr
     * @return \App\Models\User
     */
    public function find(int $id, array $selectAttrs = [])
    {

        $columns = !empty($selectAttrs) ? $selectAttrs : ['*'];
        // $user = User::on('tenant')->select($columns)->find(intval($id));
        $user = User::on('landlord')->select($columns)->find(intval($id));
        if ($user) {
            return $user;
        }
        // return User::select($selectAttrs ? $selectAttrs : '*')->find($id);
    }

    /**
     * Find a user in the database by their phone number.
     * 
     * @param string $phone_number
     * @return \App\Models\User
     */
    public function findByPhoneNumber($phone_number)
    {
        return User::where('phoneno', $phone_number)->first();
    }

    /**
     * Find a user by $attr.
     * 
     * @param string $attr
     * @param string $value
     * @return \App\Models\User
     */
    public function findByAttribute($attr, $value)
    {
        return User::where($attr, $value)->first();
    }

    public function getSystemReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $start = $request->start_date;
        $end = $request->end_date;
        $download = $request->boolean('download', 0);
        $perPage = $request->integer('per_page', 10);
        $currentPage = $request->integer('page', 1);
        $export = $request->export;
        $logs = AuditLog::with('causer.roles')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy('causer_id');

        if ($logs->isEmpty()) {
            return JsonResponser::send(false, 'No system logs found for the selected date range.', []);
        }

        $reportCollection = $logs->map(function ($group) {
            $admin = $group->first()->causer;

            return [
                'full_name' => $admin?->fullname ?? 'Unknown',
                'user_id' => $admin?->id,
                'roles' => $admin?->roles->pluck('name') ?? [],
                'status' => $admin?->status ?? 'inactive',
                'actions' => $group->map(function ($log) {
                    return [
                        'description' => $log->description,
                        'performed_at' => $log->created_at->toDateTimeString(),
                    ];
                })->values()
            ];
        })->values();

        if ($download) {
            if ($export == 'xlsx') {
                return Excel::download(new SystemReportExport($reportCollection), 'system_report_' . now()->format('Ymd_His') . '.xlsx');
            } else if ($export == 'pdf') {
                $pdf = Pdf::loadView('reports.system_report', [
                    'report' => $reportCollection,
                ]);

                return $pdf->download('system_report' . now()->format('Ymd_His') . '.pdf');
            }
        }


        $currentPage = LengthAwarePaginator::resolveCurrentPage(); // Automatically resolves the page number from the request

        // Ensure the collection is a Laravel Collection
        $reportCollection = collect($reportCollection);

        // Slice the collection for the current page
        // $currentPageItems = $reportCollection->forPage($currentPage, $perPage);

        $currentPageItems = $reportCollection->forPage($currentPage, intval($perPage))->values();


        // Create the paginator
        $paginated = new LengthAwarePaginator(
            $currentPageItems,
            $reportCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(), // Or use url()->current()
                'query' => request()->query(), // Keep query parameters like filters/search terms
            ]
        );


        return JsonResponser::send(false, 'System Report Generated Successfully.', $paginated);
    }
}
