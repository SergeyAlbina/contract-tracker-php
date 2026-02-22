<?php
declare(strict_types=1);

namespace App\Modules\Users;

use App\App;
use App\Shared\Security\Passwords;

final class UsersService
{
    private const ROLES = ['admin', 'manager', 'viewer'];

    private UsersRepository $repo;
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->repo = $app->make(UsersRepository::class);
    }

    public function list(): array
    {
        return $this->repo->all();
    }

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    /** @return array{success:bool, id?:int, errors?:string[]} */
    public function create(array $input): array
    {
        $login = trim((string) ($input['login'] ?? ''));
        $email = $this->normalizeEmail((string) ($input['email'] ?? ''));
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $role = (string) ($input['role'] ?? 'viewer');
        $isActive = (($input['is_active'] ?? '0') === '1') ? 1 : 0;
        $password = (string) ($input['password'] ?? '');

        $errors = $this->validateUserPayload($login, $email, $fullName, $role, $password, true);
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $id = $this->repo->insert([
            'login' => $login,
            'email' => $email,
            'password_hash' => Passwords::hash($password),
            'full_name' => $fullName,
            'role' => $role,
            'is_active' => $isActive,
        ]);

        $this->app->audit('user_created', 'user', $id, ['login' => $login, 'role' => $role]);
        return ['success' => true, 'id' => $id];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function update(int $id, array $input): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Пользователь не найден.']];
        }

        $login = trim((string) ($input['login'] ?? $existing['login']));
        $email = $this->normalizeEmail((string) ($input['email'] ?? ($existing['email'] ?? '')));
        $fullName = trim((string) ($input['full_name'] ?? $existing['full_name']));
        $role = (string) ($input['role'] ?? $existing['role']);
        $isActive = (($input['is_active'] ?? '0') === '1') ? 1 : 0;
        $password = trim((string) ($input['password'] ?? ''));

        $errors = $this->validateUserPayload($login, $email, $fullName, $role, $password, false, $id);

        $isLastAdmin = ((string) $existing['role'] === 'admin')
            && ((int) $existing['is_active'] === 1)
            && ($this->repo->countActiveAdmins() <= 1);
        if ($isLastAdmin && ($role !== 'admin' || $isActive !== 1)) {
            $errors[] = 'Нельзя лишить прав или деактивировать последнего активного администратора.';
        }

        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $updateData = [
            'login' => $login,
            'email' => $email,
            'full_name' => $fullName,
            'role' => $role,
            'is_active' => $isActive,
        ];
        if ($password !== '') {
            $updateData['password_hash'] = Passwords::hash($password);
        }

        $this->repo->update($id, $updateData);
        $this->app->audit('user_updated', 'user', $id, ['login' => $login, 'role' => $role]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function delete(int $id, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) {
            return ['success' => false, 'errors' => ['Пользователь не найден.']];
        }

        if ($id === $actorId) {
            return ['success' => false, 'errors' => ['Нельзя удалить свою учётную запись.']];
        }

        $isLastAdmin = ((string) $existing['role'] === 'admin')
            && ((int) $existing['is_active'] === 1)
            && ($this->repo->countActiveAdmins() <= 1);
        if ($isLastAdmin) {
            return ['success' => false, 'errors' => ['Нельзя удалить последнего активного администратора.']];
        }

        $this->repo->delete($id);
        $this->app->audit('user_deleted', 'user', $id, ['login' => $existing['login']]);
        return ['success' => true];
    }

    /** @return array{success:bool, errors?:string[]} */
    public function changeOwnPassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
    {
        $user = $this->repo->findAuthById($userId);
        if (!$user || !(int) $user['is_active']) {
            return ['success' => false, 'errors' => ['Пользователь не найден или неактивен.']];
        }

        if (trim($currentPassword) === '' || trim($newPassword) === '' || trim($confirmPassword) === '') {
            return ['success' => false, 'errors' => ['Заполните все поля пароля.']];
        }
        if (!Passwords::verify($currentPassword, (string) $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Текущий пароль указан неверно.']];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'errors' => ['Новый пароль и подтверждение не совпадают.']];
        }
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'errors' => ['Новый пароль должен быть не короче 8 символов.']];
        }
        if (Passwords::verify($newPassword, (string) $user['password_hash'])) {
            return ['success' => false, 'errors' => ['Новый пароль должен отличаться от текущего.']];
        }

        $this->repo->update($userId, ['password_hash' => Passwords::hash($newPassword)]);
        $this->app->audit('password_changed', 'user', $userId);
        return ['success' => true];
    }

    /**
     * @return string[]
     */
    private function validateUserPayload(
        string $login,
        ?string $email,
        string $fullName,
        string $role,
        string $password,
        bool $requirePassword,
        ?int $userId = null
    ): array {
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $login)) {
            $errors[] = 'Логин: 3-64 символа, только латиница, цифры, точка, дефис и подчёркивание.';
        }
        if ($fullName === '') {
            $errors[] = 'Укажите ФИО.';
        }
        if (!in_array($role, self::ROLES, true)) {
            $errors[] = 'Указана некорректная роль.';
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email.';
        }

        if ($requirePassword && strlen($password) < 8) {
            $errors[] = 'Пароль должен быть не короче 8 символов.';
        }
        if (!$requirePassword && $password !== '' && strlen($password) < 8) {
            $errors[] = 'Новый пароль должен быть не короче 8 символов.';
        }

        if ($this->repo->isLoginTaken($login, $userId)) {
            $errors[] = 'Логин уже занят.';
        }
        if ($email !== null && $this->repo->isEmailTaken($email, $userId)) {
            $errors[] = 'Email уже используется.';
        }

        return $errors;
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        return strtolower($email);
    }
}
