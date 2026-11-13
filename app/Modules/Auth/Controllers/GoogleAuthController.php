<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use App\Services\UserService;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Return the Google OAuth redirect URL for the SPA frontend.
     *
     * @return JsonResponse
     */
    public function redirect(): JsonResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $url = $driver->stateless()
                ->redirect()
                ->getTargetUrl();

            return ApiResponse::success('Google OAuth redirect URL', ['url' => $url]);

        } catch (\Exception $e) {
            return ApiResponse::error('Failed to get redirect URL', null, 500);
        }
    }

    /**
     * Handle the Google OAuth callback.
     *
     * Validates email, creates/updates user, and returns API token via Redirect.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function callback()
    {
        // 1. Definisikan URL Frontend Anda (Tarik dari .env atau fallback default)
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        try {
            // Fetch Google user
            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');
            $googleUser = $driver->stateless()->user();

            // Validate email exists and is verified
            $email = $googleUser->getEmail();
            if (!$email) {
                return redirect()->to($frontendUrl . '/login?error=' . urlencode('Email not provided by Google'));
            }

            // Find or create user via service
            $user = $this->userService->findOrCreateFromGoogle($googleUser);

            if (!$user) {
                return redirect()->to($frontendUrl . '/login?error=' . urlencode('Failed to create user account'));
            }

            // Revoke old tokens and create new one
            $user->tokens()->delete();
            $token = $user->createToken('api-token', ['*'])->plainTextToken;

            // Load relations efficiently with selective columns (MEGA-FULL pattern)
            $user->load([
                'roles:id,name',
                'roles.permissions:id,name',
                'profile:id,user_id,profile_photo_path,phone,address',
                'employee:id,user_id,employee_code,department_id,position_id,location_id,work_schedule_id',
                'employee.department:id,name',
                'employee.position:id,name',
                'employee.location:id,name',
                'employee.workSchedule:id,name',
                'employee.manager:id,name',
                'employee.manager.profile:id,user_id,profile_photo_path',
            ]);

            // 2. Bungkus profil user agar dikirim via URL ke Frontend React
            $userData = urlencode(json_encode($user));

            // 3. 🎯 Lakukan Eksekusi REDIRECT kembali ke UI Front-End (Tidak lagi me-return JSON)
            return redirect()->to($frontendUrl . '/auth/google/callback?token=' . $token . '&user=' . $userData);

        } catch (InvalidStateException $e) {
            return redirect()->to($frontendUrl . '/login?error=' . urlencode('Invalid OAuth state'));
        } catch (\Throwable $e) {
            error_log('Google Auth Error: ' . $e->getMessage()); // Catat log untuk internal
            return redirect()->to($frontendUrl . '/login?error=' . urlencode('Google authentication failed'));
        }
    }
}
