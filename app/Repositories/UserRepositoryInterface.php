<?php

namespace App\Repositories;

use App\Modules\User\Models\User;

interface UserRepositoryInterface
{
    public function create(array $data): User;
    public function findByEmail(string $email): ?User;
}

