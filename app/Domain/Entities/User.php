<?php
namespace App\Domain\Entities;

class User
{
    public int $id;
    public string $fname;
    public string $lname;
    public string $phone;
    public string $email;
    public string $password;
    public ?string $balance;
    public ?string $token;
    public ?\DateTime $email_verified_at;
    
    public function __construct(int $id, string $fname, string $lname, string $phone, string $email, string $password, 
        ?string $balance=null, ?string $token = null, ?\DateTime $email_verified_at=null) {
        
        $this->id = $id;
        $this->fname = $fname;
        $this->lname = $lname;
        $this->phone = $phone;
        $this->email = $email;
        $this->password = $password;
        $this->balance = $balance;
        $this->token = $token;
        $this->email_verified_at = $email_verified_at;
    }
}

