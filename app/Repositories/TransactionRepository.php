<?php
namespace App\Repositories;

use App\Domain\Repositories\TransactionRepositoryInterface;
use App\Domain\Entities\Transaction;
use App\Models\Transaction as EloquentTransaction;
use Illuminate\Support\Facades\Log;

class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * Retrieve a transaction by ID.
     *
     * @param  int  $id
     * @return Transaction|null
     */
    public function find(int $id): ?Transaction {
        $eloquentTransaction = EloquentTransaction::find($id);
        return $eloquentTransaction ? $this->toDomain($eloquentTransaction) : null;
    }

    /**
     * Retrieve transactions by sender ID.
     *
     * @param  int  $id
     * @return Transaction|null
     */
    public function findBySenderId(int $id): ?Transaction {
        $eloquentTransaction = EloquentTransaction::where('sender_id', $id)->first();
        return $eloquentTransaction ? $this->toDomain($eloquentTransaction) : null;
    }

    /**
     * Retrieve transactions by receiver ID.
     *
     * @param  int  $id
     * @return Transaction|null
     */
    public function findByReceiverId(int $id): ?Transaction {
        $eloquentTransaction = EloquentTransaction::where('receiver_id', $id)->first();
        return $eloquentTransaction ? $this->toDomain($eloquentTransaction) : null;
    }

    /**
     * Create a new transaction.
     *
     * @param  array  $data
     * @return Transaction|null
     */
    public function create($data): ?Transaction {
        info(print_r($data,true));
        $eloquentTransaction = EloquentTransaction::create($data);

        // Log transaction creation
        Log::info("Transaction created: ID {$eloquentTransaction->id}, Sender: {$eloquentTransaction->sender_id}, Receiver: {$eloquentTransaction->receiver_id}, Amount: {$eloquentTransaction->amount}");

        return $eloquentTransaction ? $this->toDomain($eloquentTransaction) : null;
    }

    /**
     * Save or update a transaction.
     *
     * @param  Transaction  $transaction
     * @return Transaction|null
     */
    public function save(Transaction $transaction): ?Transaction {
        $eloquentTransaction = EloquentTransaction::updateOrCreate(
            ['id' => $transaction->id],
            [
                'sender_id' => $transaction->sender_id,
                'receiver_id' => $transaction->receiver_id,
                'amount' => $transaction->amount,
            ]
        );

        return $eloquentTransaction ? $this->toDomain($eloquentTransaction) : null;
    }

    /**
     * Get the transaction history for a user.
     *
     * @param  int  $userId
     * @param  string  $transactionType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    
    public function getUserTransactions(int $userId, string $transactionType)
    {
        try {
            EloquentTransaction::where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
            });
                
            $query  = EloquentTransaction::where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
            })->orderBy('created_at', 'desc')->get();
            
            if ($transactionType === 'sent') {
                $query->where('sender_id', $userId);
            } elseif ($transactionType === 'received') {
                $query->where('receiver_id', $userId);
            }
            
            return $query;
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Convert Eloquent Model to Domain Entity.
     *
     * @param  EloquentTransaction  $eloquentTransaction
     * @return Transaction
     */
    private function toDomain(EloquentTransaction $eloquentTransaction): Transaction {
        return new Transaction(
            $eloquentTransaction->id,
            $eloquentTransaction->sender_id,
            $eloquentTransaction->receiver_id,
            $eloquentTransaction->amount,
            $eloquentTransaction->created_at,
            $eloquentTransaction->updated_at
        );
    }
}
