# Admin Permissions System

This document explains how to use the admin permissions system in the Filament dashboard.

## Overview

The admin permissions system allows you to:

1. Create and manage admin users with different roles
2. Assign specific resource permissions to roles
3. Control access to different resources (Labs, Lab Categories, Events, etc.)

## Getting Started

To set up the permissions system, run:

```bash
php artisan permissions:reset
```

This will create:
- A Super Admin role with all permissions
- A default admin user (email: admin@example.com, password: password)
- Default permissions for all resources

## Managing Roles and Permissions

1. Log in to the admin dashboard
2. Navigate to the "Roles" section to create/edit roles
3. Navigate to the "Admins" section to manage admin users

## Permission Structure

Permissions follow a simple resource-based structure. Each permission is named as:
- `manage_[resource]`

For example:
- `manage_lab` - Allows managing all lab resources
- `manage_lab_category` - Allows managing all lab category resources
- `manage_event` - Allows managing all event resources

## Creating a Role with Specific Resource Permissions

1. Go to the Roles section
2. Click "Create Role"
3. Enter a name for the role
4. Select the specific resources this role can manage
5. Save

For example, to create a "Lab Manager" role that can only manage labs and lab categories:
1. Create a new role called "Lab Manager"
2. Check the permissions for "manage_lab" and "manage_lab_category"
3. Save the role

## Creating a New Admin User

1. Go to the Admins section
2. Click "Create Admin"
3. Fill in the details (name, email, password)
4. Assign roles
5. Save

## Resetting Permissions

If you need to reset all permissions and start fresh:

```bash
php artisan permissions:reset
```

## Adding New Resources

When you add a new resource to your system, you need to:

1. Add it to the PermissionSeeder.php file in the $resources array
2. Run `php artisan permissions:reset` to recreate the permissions
3. Assign the new permission to the appropriate roles 