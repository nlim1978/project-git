<?php

namespace App\Services;

use App\Models\UserModel;

class AuthenticationService extends BaseService
{
    public function isActiveUser(string $userId): bool
    {
        return (new UserModel())
            ->where('user_id', $userId)
            ->where('account_status', 'Active')
            ->countAllResults() === 1;
    }

    public function attempt(string $username, string $password): bool
    {
        $username = trim($username);
        $user = (new UserModel())->where('username', $username)->first();

        if ($user === null || $user['account_status'] !== 'Active' || ! password_verify($password, $user['password_hash'])) {
            return false;
        }

        session()->regenerate(true);
        session()->set([
            'auth_user_id' => (string) $user['user_id'],
            'auth_username' => (string) $user['username'],
            'auth_name' => trim($user['first_name'] . ' ' . $user['last_name']),
        ]);

        (new UserModel())->update($user['user_id'], [
            'last_login' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function logout(): void
    {
        session()->remove(['auth_user_id', 'auth_username', 'auth_name']);
        session()->regenerate(true);
    }
}
