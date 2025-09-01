<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('lararbac.database_connection') ?: config('database.default');
        
        Schema::connection($connection)
            ->create('roles', static function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->json('constraints')->nullable();
                $table->timestamps();
                
                $table->index('name');
            });

        Schema::connection($connection)
            ->create('roleable_has_roles', static function (Blueprint $table) {
                $table->id();
                $table->string('model_type');
                $table->string('model_id');
                $table->string('roleable_type');
                $table->string('roleable_id');
                $table->foreignUlid('role_id')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');
                    
                $table->unique(
                    ['model_type', 'model_id', 'roleable_type', 'roleable_id', 'role_id'],
                    'roleable_has_roles_unique',
                );

                $table->index(['model_id', 'model_type'], 'roleable_has_roles_model_index');
                $table->index(['roleable_id', 'roleable_type'], 'roleable_has_roles_roleable_index');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('lararbac.database_connection') ?: config('database.default');
        
        Schema::connection($connection)->dropIfExists('roleable_has_roles');
        Schema::connection($connection)->dropIfExists('roles');
    }
};
