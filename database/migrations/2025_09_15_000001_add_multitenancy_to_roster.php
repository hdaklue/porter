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
        if (!config('porter.multitenancy.enabled', false)) {
            return;
        }

        $connection = config('porter.database_connection') ?: config('database.default');
        $tableName = config('porter.table_names.roster', 'roster');
        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
        $idStrategy = config('porter.id_strategy', 'ulid');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($tenantColumn) {
            $tenantKeyType = config('porter.multitenancy.tenant_key_type', 'ulid');
            
            // Add nullable tenant column based on configured key type
            match ($tenantKeyType) {
                'integer' => $table->unsignedBigInteger($tenantColumn)->nullable(),
                'uuid' => $table->uuid($tenantColumn)->nullable(),
                'ulid' => $table->ulid($tenantColumn)->nullable(),
                'string' => $table->string($tenantColumn, 255)->nullable(),
                default => $table->ulid($tenantColumn)->nullable(), // Default to ULID
            };

            // Index for tenant-scoped queries
            $table->index([$tenantColumn], 'porter_tenant_idx');

            // Composite index for common tenant-scoped queries
            $table->index([$tenantColumn, 'assignable_type', 'assignable_id'], 'porter_tenant_assignable_idx');
            $table->index([$tenantColumn, 'roleable_type', 'roleable_id'], 'porter_tenant_roleable_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('porter.database_connection') ?: config('database.default');
        $tableName = config('porter.table_names.roster', 'roster');
        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($tenantColumn) {
            // Drop indexes
            $table->dropIndex('porter_tenant_idx');
            $table->dropIndex('porter_tenant_assignable_idx');
            $table->dropIndex('porter_tenant_roleable_idx');

            // Drop the tenant column
            $table->dropColumn($tenantColumn);
        });
    }
};