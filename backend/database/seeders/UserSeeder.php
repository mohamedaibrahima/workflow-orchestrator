<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Admin',
                'email' => 'admin@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['admin'],
            ],
            [
                'name' => 'Employee One',
                'email' => 'employee1@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['employee'],
            ],
            [
                'name' => 'Employee Two',
                'email' => 'employee2@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['employee'],
            ],
            [
                'name' => 'Department Manager 1',
                'email' => 'manager1@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['department_manager'],
            ],
            [
                'name' => 'Department Manager 2',
                'email' => 'manager2@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['department_manager'],
            ],
            [
                'name' => 'Finance Approver 1',
                'email' => 'finance1@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['finance_approver'],
            ],
            [
                'name' => 'Finance Approver 2',
                'email' => 'finance2@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['finance_approver'],
            ],
            [
                'name' => 'Procurement Approver',
                'email' => 'procurement@example.com',
                'password' => 'password',
                'is_active' => true,
                'roles' => ['procurement_approver'],
            ],
        ];

        foreach ($users as $item) {
            $user = User::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'password' => $item['password'],
                    'is_active' => $item['is_active'],
                ]
            );

            $user->syncRoles($item['roles']);
        }
    }
}