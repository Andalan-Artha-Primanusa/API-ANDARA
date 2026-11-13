<?php

namespace App\Constants;

class MenuRegistry
{
    const MENUS = [
        ['path' => '/dashboard', 'label' => 'Dashboard', 'group' => 'Dashboard'],
        ['path' => '/employee-dashboard', 'label' => 'Dashboard Saya', 'group' => 'Dashboard'],
        ['path' => '/employees', 'label' => 'Manajemen Karyawan', 'group' => 'Karyawan'],
        ['path' => '/attendance/overtime', 'label' => 'Lembur', 'group' => 'Absensi'],
        ['path' => '/attendance/reports', 'label' => 'Laporan Absensi', 'group' => 'Absensi'],
        ['path' => '/attendance/monitoring', 'label' => 'Monitoring Clock In/Out', 'group' => 'Absensi'],
        ['path' => '/attendance/check-in', 'label' => 'Testing Clock In', 'group' => 'Absensi'],
        ['path' => '/attendance/check-out', 'label' => 'Testing Clock Out', 'group' => 'Absensi'],
        ['path' => '/leave/requests', 'label' => 'Permohonan Cuti', 'group' => 'Cuti'],
        ['path' => '/leave/calendar', 'label' => 'Kalender Cuti', 'group' => 'Cuti'],
        ['path' => '/leave/balance', 'label' => 'Saldo Cuti', 'group' => 'Cuti'],
        ['path' => '/payroll', 'label' => 'Payroll Ringkasan', 'group' => 'Payroll'],
        ['path' => '/payroll/list', 'label' => 'Daftar Payroll', 'group' => 'Payroll'],
        ['path' => '/payroll/process', 'label' => 'Proses Payroll', 'group' => 'Payroll'],
        ['path' => '/payroll/component', 'label' => 'Komponen Gaji', 'group' => 'Payroll'],
        ['path' => '/payroll/reports', 'label' => 'Laporan & Pajak', 'group' => 'Payroll'],
        ['path' => '/assets', 'label' => 'Aset & Inventaris', 'group' => 'Aset'],
        ['path' => '/reimbursements', 'label' => 'Manajemen Reimburse', 'group' => 'Reimburse'],
        ['path' => '/training/programs', 'label' => 'Pelatihan & Pendaftaran', 'group' => 'Pelatihan'],
        ['path' => '/competencies', 'label' => 'Kompetensi', 'group' => 'Pelatihan'],
        ['path' => '/promotions', 'label' => 'Karir & Promosi', 'group' => 'Karir'],
        ['path' => '/kpis', 'label' => 'KPI & Kinerja', 'group' => 'KPI'],
        ['path' => '/reports/dashboard-summary', 'label' => 'Laporan & Analitik', 'group' => 'Laporan'],
        ['path' => '/compliance/overview', 'label' => 'Dashboard Kepatuhan', 'group' => 'Kepatuhan'],
        ['path' => '/workforce/holidays', 'label' => 'Kalender Libur', 'group' => 'Kepatuhan'],
        ['path' => '/workforce/shift-swaps', 'label' => 'Tukar Shift', 'group' => 'Kepatuhan'],
        ['path' => '/workforce/overtime-rules', 'label' => 'Aturan Lembur', 'group' => 'Kepatuhan'],
        ['path' => '/organization/master-data', 'label' => 'Departemen & Posisi', 'group' => 'Master Data'],
        ['path' => '/leave/type', 'label' => 'Jenis Cuti', 'group' => 'Master Data'],
        ['path' => '/leave/policy', 'label' => 'Kebijakan Cuti', 'group' => 'Master Data'],
        ['path' => '/admin/import', 'label' => 'Pusat Impor', 'group' => 'Master Data'],
        ['path' => '/locations', 'label' => 'Lokasi', 'group' => 'Admin'],
        ['path' => '/work-schedules', 'label' => 'Jadwal Kerja', 'group' => 'Admin'],
        ['path' => '/admin/users', 'label' => 'Pengguna', 'group' => 'Admin'],
        ['path' => '/admin/roles', 'label' => 'Peran', 'group' => 'Admin'],
        ['path' => '/admin/permissions', 'label' => 'Izin', 'group' => 'Admin'],
        ['path' => '/admin/notifications', 'label' => 'Notifikasi Admin', 'group' => 'Admin'],
        ['path' => '/admin/notifications/email-send', 'label' => 'Kirim Notifikasi Email', 'group' => 'Admin'],
        ['path' => '/admin/notifications/email-logs', 'label' => 'Log & Template Email', 'group' => 'Admin'],
        ['path' => '/admin/audit-logs', 'label' => 'Log Audit', 'group' => 'Admin'],
        ['path' => '/admin/biometric-devices', 'label' => 'Perangkat Biometrik', 'group' => 'Admin'],
        ['path' => '/approval-flows', 'label' => 'Alur Persetujuan', 'group' => 'Admin'],
        ['path' => '/settings/company', 'label' => 'Pengaturan Perusahaan', 'group' => 'Admin'],
        ['path' => '/settings/notifications', 'label' => 'Pengaturan Notifikasi', 'group' => 'Admin'],
        ['path' => '/admin/assignment-letters', 'label' => 'Surat Tugas', 'group' => 'Legal'],
        ['path' => '/legal/letters', 'label' => 'Generator Surat', 'group' => 'Legal'],
        ['path' => '/legal/severance', 'label' => 'Kalkulator Pesangon', 'group' => 'Legal'],
        ['path' => '/legal/tax', 'label' => 'PPh21 Progresif', 'group' => 'Legal'],
    ];

    public static function all(): array
    {
        return self::MENUS;
    }

    public static function paths(): array
    {
        return array_column(self::MENUS, 'path');
    }
}
