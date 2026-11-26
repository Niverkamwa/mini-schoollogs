<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            if (Schema::hasColumn('passages', 'appareil_id')) {
                $table->unsignedBigInteger('appareil_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        //
    }
};