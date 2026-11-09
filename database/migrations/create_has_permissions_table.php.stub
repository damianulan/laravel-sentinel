<?php

declare(strict_types=1);

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
        Schema::create('has_permissions', function (Blueprint $table): void {
            $table->morphs('model', 'model_morph');
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->primary(['model_type', 'model_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('has_permissions');
    }
};
