<?php
namespace App\Domain\Entities;

class Transaction
{
    public int $id;
    public int $sender_id;
    public int $receiver_id;
    public float $amount;
 

    public function __construct(int $id, int $sender_id, int $receiver_id, float $amount) {
        $this->id = $id;
        $this->sender_id = $sender_id;
        $this->receiver_id = $receiver_id;
        $this->amount = $amount;
    
    }
}