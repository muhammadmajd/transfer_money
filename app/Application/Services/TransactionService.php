<?php
namespace App\Application\Services;

use App\Domain\Repositories\TransactionRepositoryInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class TransactionService
{
    private TransactionRepositoryInterface $transactionRepository;
    private UserRepositoryInterface $userRepository;
    
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        UserRepositoryInterface $userRepository
        ) {
            $this->transactionRepository = $transactionRepository;
            $this->userRepository = $userRepository;
    }
    
    /**
     * Find transaction by ID.
     *
     * @param  int  $id
     * @return Transaction|null
     */
    public function findTransactionById(int $id): ?Transaction
    {
        return $this->transactionRepository->find($id);
    }
    
    /**
     * Find transactions by sender ID.
     *
     * @param  int  $senderId
     * @return array|null
     */
    public function findTransactionsBySender(int $senderId): ?array
    {
        return $this->transactionRepository->findBySenderId($senderId);
    }
    
    /**
     * Find transactions by receiver ID.
     *
     * @param  int  $receiverId
     * @return array|null
     */
    public function findTransactionsByReceiver(int $receiverId): ?array
    {
        return $this->transactionRepository->findByReceiverId($receiverId);
    }
    
    /**
     * Create a new transaction with balance validation.
     *
     * @param  int  $senderId
     * @param  int  $receiverId
     * @param  float  $amount
     * @return Transaction|null
     */
    public function createTransaction(int $senderId, int $receiverId, float $amount): ?Transaction
    {
        try {
            DB::beginTransaction();
            
            // Validate sender and receiver
            $sender = $this->userRepository->find($senderId);
            $receiver = $this->userRepository->find($receiverId);
            info(print_r(["sid"=>$senderId,"rid"=>$receiverId, $sender, $receiver],true));
            if (!$sender || !$receiver) {
                throw new Exception("Invalid sender or receiver.");
            }
            
            // Ensure sender has sufficient balance
            if ($sender->balance < $amount) {
                throw new Exception("Insufficient balance.");
            }
            
            // Deduct amount from sender & add to receiver
            $sender->balance -= $amount;
            $receiver->balance += $amount;
            
            // Save updated balances
            $this->userRepository->save($sender);
            $this->userRepository->save($receiver);
            
            // Create transaction record
            $transactionData = [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'amount' => $amount,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            info("here......");
            
            $transaction = $this->transactionRepository->create($transactionData);
            
            // Log the transaction
            Log::info("Transaction successful: Sender {$senderId} → Receiver {$receiverId}, Amount: {$amount}");
            
            DB::commit();
            return $transaction;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Transaction failed: " . $e->getMessage());
            //Log::error($e);
            return null;
        }
    }
    
    /**
     * Save or update a transaction.
     *
     * @param  Transaction  $transaction
     * @return Transaction|null
     */
    public function saveTransaction(Transaction $transaction): ?Transaction
    {
        return $this->transactionRepository->save($transaction);
    }
    
    public function getUserTransactions(int $userId, string $transactionType){
        return $this->transactionRepository->getUserTransactions($userId, $transactionType);
    }
}
