<?php
namespace App\Repositories;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Models\User as EloquentUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Retrieve a user by PK.
     *
     * @param  int  $id
     * @return User|null
     */
    
    public function find(int $id): ?User {
        $eloquentUser = EloquentUser::find($id);
        return $eloquentUser ? $this->toDomain($eloquentUser): null;
    }
    
    /**
     * Retrieve a user by Email.
     *
     * @param  string  $email
     * @return User|null
     */
    
    public function findByEmail(string $email): ?User {
        $eloquentUser = EloquentUser::where('email',$email)->first();
        return $eloquentUser ? $this->toDomain($eloquentUser): null;
    }
    
    /**
     * Retrieve a user by Phone.
     *
     * @param  string  $phone
     * @return User|null
     */
    
    public function findByPhone(string $phone): ?User {
        $eloquentUser = EloquentUser::where('phone',$phone)->first();
        return $eloquentUser ? $this->toDomain($eloquentUser): null;
    }
    
    /**
     * Create a new user.
     *
     * @param  array  $data
     * @return User|null
     */
    public function create(array $data): ?User
    {
        $data['password'] = Hash::make($data['password']);
        $eloquentUser = EloquentUser::create($data);
        // Log message after user creation
        Log::info("User created successfully with ID: {$eloquentUser->id}, Email: {$eloquentUser->email}");
        Log::info(print_r(["toDomain"=>$this->toDomain($eloquentUser)],true));
        return $eloquentUser ? $this->toDomain($eloquentUser): null;
    }

    
    public function save(User $user): ?User
    {
        $eloquentUser = EloquentUser::find($user->id);
        
        if (!$eloquentUser) {
            throw new \Exception("User not found!");
        }
        
        $eloquentUser->email_verified_at = $user->email_verified_at;
        $eloquentUser->save();

        $eloquentUser = eloquentUser::updateOrCreate(
            ['id' =>$user->id],
            [
                'fname' => $user->fname,
                'lname' => $user->lname,
                'phone' => $user->phone,
                'password' => $user->password,
                'balance' => $user->balance,
                'email_verified_at' => $user->email_verified_at,
            ]
        );

        
        return $eloquentUser ? $this->toDomain($eloquentUser): null;
    }
    
    
    private function toDomain(EloquentUser $eloquentUser): User
    {
        return new User(
            $eloquentUser->id,
            $eloquentUser->fname,
            $eloquentUser->lname,
            $eloquentUser->phone,
            $eloquentUser->email,
            $eloquentUser->password,
            $eloquentUser->balance,
            $eloquentUser->token,
            $eloquentUser->email_verified_at,
            );
    }

}
