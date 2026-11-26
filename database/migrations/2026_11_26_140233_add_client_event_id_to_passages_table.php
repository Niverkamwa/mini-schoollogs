<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            if (! Schema::hasColumn('passages', 'client_event_id')) {
                $table->string('client_event_id', 120)->nullable()->unique();
            }
            if (! Schema::hasColumn('passages', 'sens')) {
                $table->string('sens', 20)->nullable();
            }
            if (! Schema::hasColumn('passages', 'horodatage_appareil')) {
                $table->timestamp('horodatage_appareil')->nullable();
            }
            if (! Schema::hasColumn('passages', 'horodatage_serveur')) {
                $table->timestamp('horodatage_serveur')->nullable();
            }
            if (! Schema::hasColumn('passages', 'source_sync')) {
                $table->string('source_sync', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('passages', function (Blueprint $table) {
            foreach (['source_sync', 'horodatage_serveur', 'horodatage_appareil', 'sens', 'client_event_id'] as $colonne) {
                if (Schema::hasColumn('passages', $colonne)) {
                    if ($colonne === 'client_event_id') {
                        $table->dropUnique(['client_event_id']);
                    }
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};