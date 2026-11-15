<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('kode_mitra', 3)->unique()->after('phone');
            $table->unsignedBigInteger('package_id')->nullable()->index()->after('kode_mitra');
            $table->boolean('is_active')->default(true)->after('package_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'kode_mitra',
                'package_id',
                'is_active',
                'must_change_password',
            ]);
        });
    }
};
