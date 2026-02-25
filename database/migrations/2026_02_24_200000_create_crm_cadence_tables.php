<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_cadence_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->text('description')->nullable();
            $t->json('steps');
            $t->boolean('is_default')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('crm_cadence_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('opportunity_id')->constrained('crm_opportunities')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
            $t->foreignId('cadence_template_id')->nullable()->constrained('crm_cadence_templates')->nullOnDelete();
            $t->unsignedSmallInteger('step_number');
            $t->string('title', 255);
            $t->text('description')->nullable();
            $t->date('due_date');
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('notified')->default(false);
            $t->boolean('notified_email')->default(false);
            $t->timestamps();

            $t->index(['opportunity_id', 'due_date']);
            $t->index(['assigned_user_id', 'due_date', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_cadence_tasks');
        Schema::dropIfExists('crm_cadence_templates');
    }
};
