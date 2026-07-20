<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The composite primary key (role_id, permission_id) already indexes role_id
     * as its leftmost column, but reverse lookups (all roles for a given permission,
     * as used by Permission::roles()) need permission_id indexed on its own.
     *
     * role_permissions is a pure pivot table, so it intentionally does not get
     * soft deletes here — sync()/detach() should hard-delete pivot rows; the
     * audit trail lives on the Role and Permission records themselves.
     */
    public function up(): void
    {
        if (in_array('role_permissions_permission_id_index', Schema::getIndexListing('role_permissions'), true)) {
            return;
        }

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->index('permission_id');
        });
    }

    /**
     * The permission_id index is intentionally left in place on rollback:
     * InnoDB requires an index to support the permission_id foreign key, so
     * dropping it here would require dropping and recreating the foreign key
     * itself — unwarranted churn for a rollback path. Leaving a harmless
     * index behind is the safer trade-off.
     */
    public function down(): void
    {
        //
    }
};
