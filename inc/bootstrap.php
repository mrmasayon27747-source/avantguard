<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/repository.php';

function bootstrap_defaults(): void
{
    if (STORAGE_MODE === 'mysql') {
        // Check if admin user exists in MySQL
        $admin = repo_find_user_by_username('admin');
        if (!$admin) {
            repo_create_user([
                'username' => 'admin',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'must_change_password' => 1
            ]);
        }
    } else {
        // JSON mode: check if file exists
        if (!file_exists(USERS_FILE)) {
            $users = [
                [
                    'username' => 'admin',
                    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                    'role' => 'admin',
                    'must_change_password' => true
                ]
            ];
            write_json(USERS_FILE, $users);
        }
    }
}
