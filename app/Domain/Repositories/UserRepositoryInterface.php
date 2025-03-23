<?php
namespace App\Domain\Repositories;

use App\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByPhone(string $phone): ?User;
    public function create(array $data): ?User;
    public function save(User $user): ?User;
}

