<?php

namespace Database\Seeders;

use App\Modules\User\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,     // 🔥 WAJIB PERTAMA
            AdminSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
            ApprovalFlowSeeder::class,
            LeaveSeeder::class,
            ReimbursementSeeder::class,
        ]);
    }
}

