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
                        $table->ulid('assignable_id'),             // For ULID/string IDs
                        $table->ulid('roleable_id'),               // For ULID/string IDs
                    ]
                };

                $table->text('role_key');       // Encrypted/hashed role key (can be long)
                $table->timestamps();

                // Database constraints (only if Blueprint::check method exists)
                if (method_exists($table, 'check')) {
                    $table->check('LENGTH(assignable_type) > 0', 'assignable_type_not_empty');
                    $table->check('LENGTH(roleable_type) > 0', 'roleable_type_not_empty');
                    $table->check('LENGTH(role_key) > 0', 'role_key_not_empty');

                    // Additional validation based on ID strategy
                    if (config('porter.id_strategy') === 'integer') {
                        $table->check('assignable_id > 0', 'assignable_id_positive');
                        $table->check('roleable_id > 0', 'roleable_id_positive');
                    }
                }

                $table->unique(
                    ['assignable_type', 'assignable_id', 'roleable_type', 'roleable_id', 'role_key'],
                    'porter_unique',
                );

                // Performance indexes
                $table->index(['assignable_id', 'assignable_type'], 'porter_assignable_idx');
                $table->index(['roleable_id', 'roleable_type'], 'porter_roleable_idx');
                $table->index(['role_key'], 'porter_role_key_idx');

                // Composite indexes for common queries
                $table->index(['assignable_type', 'assignable_id', 'roleable_type'], 'porter_user_entity_idx');
                $table->index(['roleable_type', 'roleable_id'], 'porter_entity_idx');
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
