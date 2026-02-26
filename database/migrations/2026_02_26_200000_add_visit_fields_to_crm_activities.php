<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->time('visit_arrival_time')->nullable()->after('due_at');
            $table->time('visit_departure_time')->nullable()->after('visit_arrival_time');
            $table->string('visit_transport', 50)->nullable()->after('visit_departure_time');
            $table->string('visit_location', 500)->nullable()->after('visit_transport');
            $table->string('visit_attendees', 1000)->nullable()->after('visit_location');
            $table->string('visit_objective', 100)->nullable()->after('visit_attendees');
            $table->string('visit_receptivity', 20)->nullable()->after('visit_objective');
            $table->date('visit_next_contact')->nullable()->after('visit_receptivity');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn([
                'visit_arrival_time', 'visit_departure_time', 'visit_transport',
                'visit_location', 'visit_attendees', 'visit_objective',
                'visit_receptivity', 'visit_next_contact',
            ]);
        });
    }
};
