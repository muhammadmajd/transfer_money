<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Mail\SendMail;
use App\Repositories\FireBaseTokenRepository;
use App\Application\Services\TransactionService;
use App\Application\Services\UserService;
use Cache;
use Illuminate\Support\Facades\Mail;

class TransactionController extends Controller
{
    protected $transactionService;
    protected $userService;
    protected $firebaseTokenRepository;

    public function __construct(TransactionService $transactionService, UserService $userService, FireBaseTokenRepository $firebaseTokenRepository)
    {
        $this->transactionService = $transactionService;
        $this->userService = $userService;
        $this->firebaseTokenRepository = $firebaseTokenRepository;
    }

    /**
     * Transfer money from a sender to a receiver using phone number.
     */
    public function transferMoney(Request $request)
    {
        try {
            $fields = $request->validate([
                'sender_id' => 'required|exists:users,id',
                'phone' => 'required|string|exists:users,phone',
                'amount' => 'required|numeric|min:1',
            ]);

       
            // Get receiver by phone number
            $receiver = $this->userService->findUserByPhone($fields['phone']);
            //$receiver = User::where('phone', $fields['phone'])->first();

            if (!$receiver) {
                return response()->json([
                    'message' => 'Receiver not found.',
                    'sentStatus' => false,
                ], 404);
            }

            
            // Generate a random verification code
            $verificationCode = rand(100000, 999999);
            Log::info("Verification Code created:". $verificationCode);
            
            // Send the verification code via email to the sender
            $sender = $this->userService->findUserById($fields['sender_id']);
            //$sender = User::find($fields['sender_id']);
             
             // store code in a temporary session or a table for persistence.
             //session(['transaction_verification_code' => $verificationCode]);
             Cache::put('transaction_verification_code_' . $fields['sender_id'], $verificationCode,now()->addMinutes(10)); // Store with an expiration time of 10 minutes
 
            Log::info("User   :". $sender->email);
            $this->sendVerificationEmail($sender->email, $verificationCode);
            
            Log::info("verificationCode sent :". $verificationCode);
 
            return response()->json([
                'message' => 'Code Vervication Transaction sent please confirm the operation.',
                'sentStatus' => true,
               // 'transaction' => $transaction,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Transaction error: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while processing the transaction. Please try again later.',
                'sentStatus' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyTransaction(Request $request)
        {
            $fields =$request->validate([
                'verification_code' => 'required|numeric',
                 'sender_id' => 'required|numeric', 
                 'phone' => 'required|string|exists:users',
                'amount' => 'required|numeric|min:1',
            ]);
           
            
            // Retrieve the verification code from the session 
            $sessionVerificationCode = Cache::get('transaction_verification_code_'.$fields['sender_id']);
          //  $sessionVerificationCode = (string) session('transaction_verification_code');
            Log::info("sessionVerificationCode :". $sessionVerificationCode);
            if ($request->verification_code == $sessionVerificationCode) {
    
                $user = $this->userService->findUserByPhone($fields['phone']);
                $transaction = $this->transactionService->createTransaction(
                    $fields['sender_id'],
                    $user->id,
                    $fields['amount']
                );
                if ($transaction === null) {
                    return response()->json([
                        'result' => false,
                        'message' => 'Transaction failed. Please check the sender balance or ensure the receiver exists and is not the same as the sender.',
                    ], 400);
                }
                // Get active Firebase tokens for sender and receiver
                // $senderTokens = FirebaseToken::where('user_id', $sender->id)->where('active', true)->pluck('token'); 
                 $firebaseToken =$this->firebaseTokenRepository->getFirebaseTokenByPhone($fields['phone']);
                 


    
                return response()->json([
                    'message' => 'Transaction successful.',
                    'result' => true,
                    'transaction' => $transaction,
                    'firebaseToken' => $firebaseToken
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Invalid verification code.',
                    'result' => false,
                ], 400);
            }
        }

        
    /**
     * helper function to send email 
     */

    public function sendVerificationEmail($userEmail, $verificationCode)
        {
            try {
                Log::info("email :". $userEmail);
                // Send verification code via email to the sender
                
                Mail::to($userEmail)->send(new SendMail($verificationCode,"transaction"));
                //Mail::to($$userEmail)->send(new AccountActivationMail($verificationCode));

            } catch (\Exception $e) {
                Log::error('Error sending verification email: ' . $e->getMessage());
                throw new \Exception('Failed to send the verification code.');
            }
        }

    /**
     * Get received transaction history for a specific user.
     */

     

 
    
    public function getUserTransactions(Request $request)
    {
        Log::info("User created successfully with ID , Email - {$request->user_id} ");
        
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'transaction_type' => 'required',
            ]);

            $userId = $request->user_id;
            $transactionType = $request->transaction_type;

            $transactions = $this->transactionService->getUserTransactions($userId, $transactionType);

            if ($transactions->isEmpty()) {
                return response()->json([
                    'message' => 'No transactions found for this user.',
                    'result' => false,
                ], 404);
            }

            // Format the transactions
            // Process transactions to include transaction type and correct user info
            $transactions = $transactions->map(function ($transaction) use ($userId) {
                $isSent = (string)$transaction->sender_id === (string)$userId; // Check if the user sent the transaction
                Log::info(message: "User Transaction Type: " . ($isSent ? 'sent' : 'received') . " | Transaction ID: {$transaction->id}");
                Log::info(message: "User Transaction Type: " .  " | Transaction ID: {$transaction->sender_id}");
                Log::info(message: "User Transaction Type: " .  " | Transaction ID: {$userId}");
                return [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'sender_id' => $transaction->sender_id,
                    'receiver_id' => $transaction->receiver_id,
                    'created_at' => $transaction->created_at,
                    'transaction_type' => $isSent ? 'sent' : 'received', // Correctly classify transaction
                    'user' => [
                        'id' => $isSent ? $transaction->receiver->id : $transaction->sender->id,
                        'fname' => $isSent ? $transaction->receiver->fname : $transaction->sender->fname,
                        'lname' => $isSent ? $transaction->receiver->lname : $transaction->sender->lname,
                        'phone' => $isSent ? $transaction->receiver->phone : $transaction->sender->phone,
                        'email' => $isSent ? $transaction->receiver->email : $transaction->sender->email,
                    ]
                ];
            });
        
            return response()->json([
                'success' => true,
                'transactions' => $transactions
            ], 200);
        
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    
}
