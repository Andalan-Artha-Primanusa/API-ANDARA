<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('birth_date');
            $table->string('marital_status', 20)->nullable()->after('gender');
            $table->string('religion', 50)->nullable()->after('marital_status');
            $table->string('nationality', 100)->nullable()->after('religion');
            $table->string('id_number', 100)->nullable()->unique()->after('nationality');

            $table->string('emergency_contact_name', 255)->nullable()->after('id_number');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relation', 100)->nullable()->after('emergency_contact_phone');

            $table->text('current_address')->nullable()->after('emergency_contact_relation');
            $table->text('permanent_address')->nullable()->after('current_address');

            $table->string('bank_name', 100)->nullable()->after('permanent_address');
            $table->string('bank_account_number', 100)->nullable()->after('bank_name');
            $table->string('bank_account_name', 255)->nullable()->after('bank_account_number');

            $table->string('tax_number', 100)->nullable()->after('bank_account_name');
            $table->string('last_education', 100)->nullable()->after('tax_number');
            $table->string('institution_name', 255)->nullable()->after('last_education');
            $table->year('graduation_year')->nullable()->after('institution_name');
            $table->string('profile_photo_path', 255)->nullable()->after('graduation_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropUnique('user_profiles_id_number_unique');

            $table->dropColumn([
                'gender',
                'marital_status',
                'religion',
                'nationality',
                'id_number',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relation',
                'current_address',
                'permanent_address',
                'bank_name',
                'bank_account_number',
                'bank_account_name',
                'tax_number',
                'last_education',
                'institution_name',
                'graduation_year',
                'profile_photo_path',
            ]);
        });
    }
};
