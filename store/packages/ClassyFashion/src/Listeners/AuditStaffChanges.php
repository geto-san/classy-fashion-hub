<?php

namespace ClassyFashion\Listeners;

use ClassyFashion\Support\Audit;

/**
 * Staff account and role changes go in the audit log (report 9.1, 10.2).
 * Never records passwords or tokens, only which fields changed.
 */
class AuditStaffChanges
{
    protected const IGNORED = ['password', 'updated_at', 'remember_token', 'api_token'];

    public function adminCreated($admin): void
    {
        Audit::log($admin, "Staff account created: {$admin->email}", [
            'email' => $admin->email, 'role_id' => $admin->role_id, 'status' => $admin->status,
        ], null, 'staff.created');
    }

    public function adminUpdated($admin): void
    {
        $changes = $admin->getChanges();

        Audit::log($admin, "Staff account updated: {$admin->email}", [
            'email'            => $admin->email,
            'changed_fields'   => array_values(array_diff(array_keys($changes), self::IGNORED)),
            'password_changed' => array_key_exists('password', $changes),
        ], null, 'staff.updated');
    }

    public function adminDeleted($id): void
    {
        $this->deleted('staff.deleted', "Staff account #{$id} deleted", ['admin_id' => $id]);
    }

    public function roleCreated($role): void
    {
        Audit::log($role, "Role created: {$role->name}", ['permission_type' => $role->permission_type], null, 'role.created');
    }

    public function roleUpdated($role): void
    {
        Audit::log($role, "Role updated: {$role->name}", [
            'permission_type' => $role->permission_type,
            'permissions'     => $role->permissions,
        ], null, 'role.updated');
    }

    public function roleDeleted($id): void
    {
        $this->deleted('role.deleted', "Role #{$id} deleted", ['role_id' => $id]);
    }

    /**
     * The record is gone, so there is no model to attach the entry to.
     */
    protected function deleted(string $event, string $description, array $properties): void
    {
        $logger = activity('classy-fashion')->event($event)->withProperties($properties);

        if ($admin = auth('admin')->user()) {
            $logger->causedBy($admin);
        }

        $logger->log($description);
    }
}
