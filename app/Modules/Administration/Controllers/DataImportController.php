<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Administration\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataImportController extends Controller
{
    public function importUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.import.execute')) {
            return ApiResponse::error('Forbidden', 'Missing admin import capability', 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,json|max:5120', // Max 5MB
            'role' => 'required|string|exists:roles,name',
        ]);

        try {
            $file = $validated['file'];
            $role = $validated['role'];

            $data = $this->parseFile($file);

            if (empty($data)) {
                return ApiResponse::error('Invalid file', 'File is empty or invalid format', 422);
            }

            $result = $this->importUserData($data, $role);

            return ApiResponse::success('User import completed', $result, 200);

        } catch (\Exception $e) {
            return ApiResponse::error('Import failed', $e->getMessage(), 422);
        }
    }

    public function importEmployees(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.import.execute')) {
            return ApiResponse::error('Forbidden', 'Missing admin import capability', 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,json|max:5120',
            'update_existing' => 'sometimes|boolean',
        ]);

        try {
            $file = $validated['file'];
            $updateExisting = $validated['update_existing'] ?? false;

            $data = $this->parseFile($file);

            if (empty($data)) {
                return ApiResponse::error('Invalid file', 'File is empty or invalid format', 422);
            }

            $result = $this->importEmployeeData($data, $updateExisting);

            return ApiResponse::success('Employee import completed', $result, 200);

        } catch (\Exception $e) {
            return ApiResponse::error('Import failed', $e->getMessage(), 422);
        }
    }

    protected function parseFile($file): array
    {
        $extension = $file->getClientOriginalExtension();

        if ($extension === 'json') {
            $content = file_get_contents($file->getPathname());
            return json_decode($content, true) ?? [];
        }

        if (in_array($extension, ['csv', 'xlsx'])) {
            return $this->parseCsv($file);
        }

        throw new \Exception('Unsupported file format: ' . $extension);
    }

    protected function parseCsv($file): array
    {
        $filePath = $file->getPathname();
        $records = [];
        $header = null;

        if (($handle = fopen($filePath, 'r')) !== false) {
            $firstLine = true;

            while (($row = fgetcsv($handle)) !== false) {
                if ($firstLine) {
                    $header = $row;
                    $firstLine = false;
                    continue;
                }

                if (!$header) {
                    continue;
                }

                // Map CSV row to associative array using header
                $record = [];
                foreach ($header as $index => $columnName) {
                    $record[$columnName] = $row[$index] ?? null;
                }

                $records[] = $record;
            }

            fclose($handle);
        }

        return $records;
    }

    protected function importUserData($data, $role): array
    {
        $roleModel = Role::where('name', $role)->first();

        if (!$roleModel) {
            throw new \Exception('Role not found: ' . $role);
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                try {
                    $this->validateUserRow($row);

                    $user = User::create([
                        'name' => $row['name'] ?? $row['full_name'],
                        'email' => $row['email'],
                        'password' => bcrypt($row['password'] ?? Str::password(18)),
                        'phone' => $row['phone'] ?? null,
                    ]);

                    $user->roles()->attach($roleModel->id);

                    $success++;

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 2, // +2 because header is row 1 and 0-indexed
                        'email' => $row['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'total' => count($data),
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'role' => $role,
        ];
    }

    protected function importEmployeeData($data, $updateExisting = false): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                try {
                    $this->validateEmployeeRow($row);

                    // Find user by email
                    $user = User::where('email', $row['email'])->first();

                    if (!$user) {
                        throw new \Exception('User not found with email: ' . $row['email']);
                    }

                    // Check if employee already exists
                    $employee = Employee::where('user_id', $user->id)->first();

                    if ($employee && !$updateExisting) {
                        throw new \Exception('Employee already exists for this user. Set update_existing=true to update.');
                    }

                    $employeeData = [
                        'user_id' => $user->id,
                        'employee_code' => $row['employee_code'] ?? 'EMP-' . str_pad(Employee::max('id') + 1, 4, '0', STR_PAD_LEFT),
                        'position' => $row['position'] ?? $row['job_title'],
                        'department' => $row['department'],
                        'hire_date' => $row['hire_date'] ?? now(),
                        'salary' => $row['salary'] ?? $row['basic_salary'] ?? 0,
                        'status' => $row['status'] ?? 'active',
                    ];

                    // Add optional manager
                    if (isset($row['manager_email'])) {
                        $manager = User::where('email', $row['manager_email'])->first();
                        if ($manager) {
                            $employeeData['manager_id'] = $manager->id;
                        }
                    }

                    if ($employee && $updateExisting) {
                        $employee->update($employeeData);
                    } else {
                        Employee::create($employeeData);
                    }

                    $success++;

                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = [
                        'row' => $index + 2,
                        'email' => $row['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'total' => count($data),
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
            'update_mode' => $updateExisting,
        ];
    }

    protected function validateUserRow($row): void
    {
        if (empty($row['email'])) {
            throw new \Exception('Email is required');
        }

        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }

        if (User::where('email', $row['email'])->exists()) {
            throw new \Exception('User with this email already exists');
        }

        if (empty($row['name']) && empty($row['full_name'])) {
            throw new \Exception('Name or full_name is required');
        }
    }

    protected function validateEmployeeRow($row): void
    {
        if (empty($row['email'])) {
            throw new \Exception('Email is required');
        }

        if (empty($row['department'])) {
            throw new \Exception('Department is required');
        }

        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format');
        }
    }

    public function getImportTemplate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('admin.import.execute')) {
            return ApiResponse::error('Forbidden', 'Missing admin import capability', 403);
        }

        $type = $request->query('type', 'user'); // user or employee

        $templates = [
            'user' => [
                'columns' => ['name', 'email', 'phone', 'password'],
                'example' => [
                    [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'phone' => '08123456789',
                        'password' => '',
                    ],
                    [
                        'name' => 'Jane Smith',
                        'email' => 'jane@example.com',
                        'phone' => '08987654321',
                        'password' => '',
                    ],
                ],
            ],
            'employee' => [
                'columns' => ['email', 'employee_code', 'position', 'department', 'hire_date', 'salary', 'manager_email', 'status'],
                'example' => [
                    [
                        'email' => 'john@example.com',
                        'employee_code' => 'EMP-0001',
                        'position' => 'Software Engineer',
                        'department' => 'IT',
                        'hire_date' => '2023-01-15',
                        'salary' => '10000000',
                        'manager_email' => 'manager@example.com',
                        'status' => 'active',
                    ],
                    [
                        'email' => 'jane@example.com',
                        'employee_code' => 'EMP-0002',
                        'position' => 'HR Specialist',
                        'department' => 'HR',
                        'hire_date' => '2023-02-20',
                        'salary' => '8000000',
                        'manager_email' => 'manager@example.com',
                        'status' => 'active',
                    ],
                ],
            ],
        ];

        $template = $templates[$type] ?? $templates['user'];

        return ApiResponse::success('Import template for ' . $type, $template);
    }
}

