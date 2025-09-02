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
        $connection = config('porter.database_connection') ?: config('database.default');
        $idStrategy = config('porter.id_strategy', 'ulid');

        Schema::connection($connection)
            ->create(config('porter.table_names.roaster'), static function (Blueprint $table) use ($idStrategy) {
                $table->id();

                // Model type columns (always strings for class names)
                $table->string('assignable_type'); // assignable model type
                $table->string('roleable_type');   // Entity type (Project, Organization, etc.)

                // ID columns - type depends on configured strategy
                match ($idStrategy) {
                    'integer' => [
                        $table->unsignedBigInteger('assignable_id'), // For auto-increment IDs
                        $table->unsignedBigInteger('roleable_id'),   // For auto-increment IDs
                    ],
                    'uuid' => [
                        $table->uuid('assignable_id'),               // For UUID IDs
                        $table->uuid('roleable_id'),                 // For UUID IDs
                    ],
                    default => [  // 'ulid' or any other strategy
                        $table->string('assignable_id'),             // For ULID/string IDs
                        $table->string('roleable_id'),               // For ULID/string IDs
                    ]
                };

                $table->string('role_key');     // Role key ('admin', 'manager', etc.)
                $table->timestamps();

                $table->unique(
                    ['assignable_type', 'assignable_id', 'roleable_type', 'roleable_id', 'role_key'],
                    'porter_unique',
                );

                $table->index(['assignable_id', 'assignable_type'], 'porter_assignable_index');
                $table->index(['roleable_id', 'roleable_type'], 'porter_roleable_index');
                $table->index(['role_key'], 'porter_role_key_index');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('porter.database_connection') ?: config('database.default');

        Schema::connection($connection)->dropIfExists(config('porter.table_names.roaster', 'roaster'));
    }
};
