<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('category', 50)->default('geral');
            $table->string('original_name');
            $table->string('normalized_name');
            $table->string('disk_path');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_documents');
    }
};
