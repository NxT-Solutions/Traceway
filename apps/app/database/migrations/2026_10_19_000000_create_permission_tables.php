<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables of spatie/laravel-permission, without teams. The names match config/permission.php.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->string('guard_name');
            $blueprint->timestamps();
            $blueprint->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('name');
            $blueprint->string('guard_name');
            $blueprint->timestamps();
            $blueprint->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $blueprint): void {
            $blueprint->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger('model_id');
            $blueprint->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $blueprint->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $blueprint): void {
            $blueprint->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $blueprint->string('model_type');
            $blueprint->unsignedBigInteger('model_id');
            $blueprint->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $blueprint->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $blueprint): void {
            $blueprint->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $blueprint->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $blueprint->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
