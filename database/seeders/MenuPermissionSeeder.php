<?php

namespace Database\Seeders;

use App\Modules\Administration\Models\MenuPermission;
use App\Modules\Administration\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $menuKeys = DB::table('menus')->where('is_active', true)->pluck('key');
        $roles = Role::all();

        foreach ($roles as $role) {
            foreach ($menuKeys as $key) {
                MenuPermission::firstOrCreate([
                    'menu_key' => $key,
                    'role_id' => $role->id,
                ]);
            }
        }

        $this->command?->info('All menus assigned to all roles successfully.');
    }
}


