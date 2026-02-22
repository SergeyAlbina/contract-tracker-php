<?php
declare(strict_types=1);

namespace App\Modules\Users;

use App\App;
use App\Http\{Request, Response};

final class UsersController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $items = $this->app->make(UsersService::class)->list();
        return $this->app->view('users/list', [
            'title' => 'Пользователи',
            'items' => $items,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->app->view('users/form', [
            'title' => 'Новый пользователь',
            'userForm' => ['role' => 'viewer', 'is_active' => '1'],
            'errors' => [],
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): Response
    {
        $service = $this->app->make(UsersService::class);
        $input = $request->all();
        $result = $service->create($input);

        if (!$result['success']) {
            return $this->app->view('users/form', [
                'title' => 'Новый пользователь',
                'userForm' => $input,
                'errors' => $result['errors'],
                'isEdit' => false,
            ]);
        }

        $this->app->flash('success', 'Пользователь создан.');
        return Response::redirect('/users');
    }

    public function edit(Request $request): Response
    {
        $id = $request->paramInt('id');
        $user = $this->app->make(UsersService::class)->getById($id);
        if (!$user) {
            $this->app->flash('error', 'Пользователь не найден.');
            return Response::redirect('/users');
        }

        return $this->app->view('users/form', [
            'title' => 'Редактирование пользователя',
            'userForm' => $user,
            'errors' => [],
            'isEdit' => true,
        ]);
    }

    public function update(Request $request): Response
    {
        $id = $request->paramInt('id');
        $service = $this->app->make(UsersService::class);
        $result = $service->update($id, $request->all());

        if (!$result['success']) {
            $existing = $service->getById($id) ?? [];
            return $this->app->view('users/form', [
                'title' => 'Редактирование пользователя',
                'userForm' => array_merge($existing, $request->all()),
                'errors' => $result['errors'],
                'isEdit' => true,
            ]);
        }

        $this->app->flash('success', 'Пользователь обновлён.');
        return Response::redirect('/users');
    }

    public function delete(Request $request): Response
    {
        $id = $request->paramInt('id');
        $actorId = $this->app->currentUserId() ?? 0;
        $result = $this->app->make(UsersService::class)->delete($id, $actorId);

        if ($result['success']) {
            $this->app->flash('success', 'Пользователь удалён.');
        } else {
            $this->app->flash('error', implode(' ', $result['errors'] ?? ['Ошибка удаления.']));
        }

        return Response::redirect('/users');
    }

    public function showPasswordForm(Request $request): Response
    {
        return $this->app->view('users/password', [
            'title' => 'Смена пароля',
            'errors' => [],
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $userId = $this->app->currentUserId();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $result = $this->app->make(UsersService::class)->changeOwnPassword(
            $userId,
            (string) $request->post('current_password', ''),
            (string) $request->post('new_password', ''),
            (string) $request->post('new_password_confirm', '')
        );

        if (!$result['success']) {
            return $this->app->view('users/password', [
                'title' => 'Смена пароля',
                'errors' => $result['errors'],
            ]);
        }

        $this->app->flash('success', 'Пароль успешно изменён.');
        return Response::redirect('/contracts');
    }
}
