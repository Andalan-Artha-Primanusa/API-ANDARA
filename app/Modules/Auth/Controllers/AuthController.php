<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Services\UserService;
use App\Modules\Employee\Models\Employee;
use App\Modules\Administration\Models\Permission;

class AuthController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Register new user with auto-created employee record.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name'                  => 'required|string|max:255',
                'email'                 => 'required|email|unique:users,email',
                'password'              => 'required|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                'password_confirmation' => 'required',
            ]);

            // Create user via service (handles hashing, etc)
            $user = $this->userService->register($validated);

            // Auto-create employee record in transaction
            Employee::create([
                'user_id'        => $user->id,
                'employee_code'  => 'EMP-' . str_pad((string)$user->id, 4, '0', STR_PAD_LEFT),
                'position'       => 'Staff',
                'department'     => 'General',
                'hire_date'      => now(),
                'salary'         => 0,
            ]);

            // Delete old tokens and create new one
            $user->tokens()->delete();
            $token = $user->createToken('api-token', ['*'])->plainTextToken;

            // Load relations without schema-fragile column lists; production databases may lag optional profile/employee columns.
            $user->load([
                'roles:id,name',
                'roles.permissions:id,name',
                'profile',
                'employee.department:id,name',
                'employee.position:id,name',
                'employee.location:id,name',
                'employee.workSchedule:id,name,check_in_time,check_out_time',
                'employee.manager:id,name',
                'employee.manager.profile',
                'companyAccesses.company',
                'companies',
            ]);

            $effectivePermissions = $this->resolveEffectivePermissions($user);

            return ApiResponse::success('Registration successful', [
                'user' => $user,
                'effective_permissions' => $effectivePermissions,
                'token' => $token,
            ], 201);

        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Registration failed', null, 500);
        }
    }

    /**
     * Login user and return authentication token.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                // The legacy SQL Server table accepts either EmailAddress or UserID.
                'email'    => 'required|string|max:255',
                'password' => 'required|min:6',
            ]);

            // Authenticate against dbo.ms_sa_permission on SQL Server.
            $legacyUser = $this->userService->loginLegacy($validated);

            return ApiResponse::success('Login successful', [
                'user' => [
                    'id' => $legacyUser->UserID,
                    'user_id' => $legacyUser->UserID,
                    'name' => $legacyUser->CompleteUserName ?? null,
                    'email' => $legacyUser->EmailAddress,
                    'role_id' => $legacyUser->UserRoleID,
                    'is_active' => (bool) $legacyUser->isActiveUser,
                    'is_first_login' => (bool) $legacyUser->isFirstLogin,
                ],
                'effective_permissions' => [],
                'token' => null,
            ]);

        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            // Never expose filesystem paths, SQL details, or stack traces from
            // an authentication endpoint. Logging remains best-effort so a
            // broken storage/logs permission does not create a second error.
            try {
                Log::error('Login failed', ['message' => $e->getMessage()]);
            } catch (\Throwable) {
                // The production error response must remain safe even when
                // the configured log channel is temporarily unavailable.
            }

            return ApiResponse::error('Login gagal. Silakan coba lagi.', null, 500);
        }
    }

    /**
     * Logout user by deleting current access token.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->user()->currentAccessToken();
            
            if ($token) {
                $token->delete();
            }

            return ApiResponse::success('Logout successful');

        } catch (\Exception $e) {
            return ApiResponse::error('Logout failed', null, 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'current_password.required' => 'Password sementara/password saat ini wajib diisi.',
                'password.required' => 'Password baru wajib diisi.',
                'password.min' => 'Password minimal 8 karakter.',
                'password.confirmed' => 'Konfirmasi password tidak sama.',
            ]);

            $user = $request->user();

            if (!$user || !Hash::check($validated['current_password'], $user->password)) {
                return ApiResponse::error('Password saat ini tidak sesuai', null, 422);
            }

            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();

            return ApiResponse::success('Password berhasil diganti. Silakan login kembali.');
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Throwable $e) {
            Log::error('Change password failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ApiResponse::error('Gagal mengganti password', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * Get authenticated user profile for current token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return ApiResponse::error('Unauthenticated', null, 401);
            }

            $user->load([
                'roles',
                'roles.permissions',
                'profile',
                'employee.department',
                'employee.position',
                'employee.location',
                'employee.workSchedule',
                'employee.manager',
                'employee.manager.profile',
                'companyAccesses.company',
                'companies',
            ]);

            $effectivePermissions = $this->resolveEffectivePermissions($user);

            return ApiResponse::success('Authenticated user', [
                'user' => $user,
                'effective_permissions' => $effectivePermissions,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fetch authenticated user failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ApiResponse::error('Failed to fetch authenticated user', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /**
     * Send a reset password link to the provided email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
            ]);

            $status = Password::sendResetLink([
                'email' => $validated['email'],
            ]);

            if ($status !== Password::RESET_LINK_SENT) {
                return ApiResponse::error(__($status), null, 400);
            }

            return ApiResponse::success('Link reset password sudah dikirim ke email Anda.');
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal mengirim link reset password', null, 500);
        }
    }

    /**
     * Reset password using token sent by email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed',
            ], [
                'token.required' => 'Token reset password wajib diisi.',
                'email.required' => 'Email wajib diisi.',
                'email.email' => 'Format email tidak valid.',
                'password.required' => 'Password wajib diisi.',
                'password.min' => 'Password minimal 8 karakter.',
                'password.confirmed' => 'Konfirmasi password tidak sama.',
            ]);

            $status = Password::reset(
                [
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'password_confirmation' => $request->input('password_confirmation'),
                    'token' => $validated['token'],
                ],
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'must_change_password' => false,
                        'password_changed_at' => now(),
                        'remember_token' => Str::random(60),
                    ])->save();

                    $user->tokens()->delete();
                    event(new PasswordReset($user));
                }
            );

            if ($status !== Password::PASSWORD_RESET) {
                return ApiResponse::error(__($status), null, 400);
            }

            return ApiResponse::success('Password berhasil direset. Silakan login kembali.');
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal reset password', null, 500);
        }
    }

    /**
     * Resolve permission names used by frontend menu rendering.
     * Super admin always receives all permissions.
     */
    private function resolveEffectivePermissions($user): array
    {
        if ($user->isSuperAdmin()) {
            return Permission::query()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();
        }

        return $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}

