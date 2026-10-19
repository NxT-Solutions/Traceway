<?php

declare(strict_types=1);

namespace App\Account;

/**
 * One thing the admin area lets a person do. The gate checks these names, so a Super Admin passes them all.
 */
enum Permission: string
{
    case ViewAnalytics = 'view-analytics';
    case ViewUsers = 'view-users';
    case ManageUsers = 'manage-users';
    case AssignRoles = 'assign-roles';
    case Impersonate = 'impersonate';
}
