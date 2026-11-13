<?php

namespace App\Modules\Administration\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $settings = NotificationSetting::all();

        // If empty, seed with defaults
        if ($settings->isEmpty()) {
            $this->seedDefaults();
            $settings = NotificationSetting::all();
        }

        return ApiResponse::success('Notification settings retrieved', $settings);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $category): JsonResponse
    {
        $validated = $request->validate([
            'channel' => 'required|string|in:in_app,email,push',
            'enabled' => 'required|boolean',
        ]);

        $setting = NotificationSetting::where('category', $category)->first();

        if (!$setting) {
            return ApiResponse::error('Setting not found', null, 404);
        }

        $channel = $validated['channel'];
        $setting->$channel = $validated['enabled'];
        $setting->save();

        return ApiResponse::success('Notification setting updated', $setting);
    }

    /**
     * Seed default settings if none exist.
     */
    private function seedDefaults(): void
    {
        $defaults = [
            [
                'category' => 'Absensi',
                'label' => 'Kehadiran & Absensi',
                'description' => 'Notifikasi saat karyawan melakukan check-in, check-out, atau terlambat.',
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
            [
                'category' => 'Payroll',
                'label' => 'Penggajian & Slip Gaji',
                'description' => 'Notifikasi saat payroll diproses, slip gaji tersedia, atau ada revisi gaji.',
                'in_app' => true,
                'email' => true,
                'push' => false,
            ],
            [
                'category' => 'Cuti',
                'label' => 'Manajemen Cuti',
                'description' => 'Notifikasi pengajuan cuti baru, persetujuan, atau penolakan cuti.',
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
            [
                'category' => 'Reimbursement',
                'label' => 'Klaim Biaya (Reimburse)',
                'description' => 'Notifikasi status klaim biaya dan pembayaran reimburse.',
                'in_app' => true,
                'email' => false,
                'push' => true,
            ],
            [
                'category' => 'Aset',
                'label' => 'Manajemen Aset',
                'description' => 'Notifikasi penyerahan aset, pengembalian, atau jadwal maintenance.',
                'in_app' => true,
                'email' => true,
                'push' => false,
            ],
            [
                'category' => 'Sistem',
                'label' => 'Update Sistem & Keamanan',
                'description' => 'Notifikasi mengenai pemeliharaan sistem atau peringatan keamanan akun.',
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
        ];

        foreach ($defaults as $default) {
            NotificationSetting::updateOrCreate(
                ['category' => $default['category']],
                $default
            );
        }
    }
}
