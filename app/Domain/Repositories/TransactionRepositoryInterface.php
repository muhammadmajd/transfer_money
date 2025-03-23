<?php
namespace App\Domain\Repositories;

use App\Domain\Entities\Transaction;

interface TransactionRepositoryInterface
{
    public function find(int $id): ?Transaction;
    public function findBySenderId(int $id): ?Transaction;
    public function findByReceiverId(int $id): ?Transaction;
    public function create($data): ?Transaction;
    public function save(Transaction $transaction): ?Transaction;
}

