<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE leaves DROP FOREIGN KEY leaves_approval_flow_id_foreign');
        DB::statement('ALTER TABLE leaves ADD CONSTRAINT leaves_approval_flow_id_foreign FOREIGN KEY (approval_flow_id) REFERENCES approval_flows(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE leaves DROP FOREIGN KEY leaves_approval_flow_id_foreign');
        DB::statement('ALTER TABLE leaves ADD CONSTRAINT leaves_approval_flow_id_foreign FOREIGN KEY (approval_flow_id) REFERENCES approval_flows(id)');
    }
};
