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
use Barryvdh\DomPDF\Facade\Pdf;

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
        $query = User::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('fullname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($export) {
            $users = $query->get();

            $exportData = $users->map(function ($user) {
                return [
                    'ID' => $user->id,
                    'Fullname' => $user->fullname,
                    'Email' => $user->email,
                    'Date_of_birth' => $user->date_of_birth,
                    'PhoneNumber' => $user->phone_number,
                    'Role' => $user->role,
                    'Status' => $user->status,
                    'Created At' => $user->created_at,
                ];
            })->toArray();

            if (strtolower($export) === 'csv') {
                return ExportHelper::streamCsv($exportData, null, 'users.csv');
            }
            if (strtolower($export) === 'pdf') {
                return ExportHelper::downloadPdf($exportData, 'users.pdf');
            }
            throw new \Exception('Invalid export format.');
        }

        return $paginate ? $query->paginate($perPage) : $query->get();
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
        $user = User::on('tenant')->select($columns)->find(intval($id));
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
        $perPage = 15; // Set your desired per-page value

        // Ensure the collection is a Laravel Collection
        $reportCollection = collect($reportCollection);

        // Slice the collection for the current page
        // $currentPageItems = $reportCollection->forPage($currentPage, $perPage);

        $currentPageItems = $reportCollection->forPage($currentPage, $perPage)->values();


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
