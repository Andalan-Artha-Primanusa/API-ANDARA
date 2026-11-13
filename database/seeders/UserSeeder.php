<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\User\Models\User;
use App\Modules\Administration\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $hrRole = Role::where('name', 'hr')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        if (!$hrRole || !$managerRole || !$employeeRole) {
            $this->command?->error('Roles not found! Run RbacSeeder first.');
            return;
        }

        // HR users
        $hrs = User::factory()->count(2)->create();
        foreach ($hrs as $user) {
            $user->roles()->syncWithoutDetaching([$hrRole->id]);
        }

        // Manager users
        $managers = User::factory()->count(3)->create();
        foreach ($managers as $user) {
            $user->roles()->syncWithoutDetaching([$managerRole->id]);
        }

        // Employee users
        $employees = User::factory()->count(10)->create();
        foreach ($employees as $user) {
            $user->roles()->syncWithoutDetaching([$employeeRole->id]);
        }

        $this->command?->info('Users seeded with pivot-based roles.');
    }
}


