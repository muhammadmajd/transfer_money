<?php
namespace App\Application\Services;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\Exceptions\Exception;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMail;
use App\Models\User as EloquentUser;
use Cache;
class UserService
{
    private UserRepositoryInterface $userRepository;
    
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    /**
     * Generate an API token for the user.
     *
     * @param  User  $user
     * @return string
     */
    public function generateToken(User $user)
    {
        $eloquentUser = EloquentUser::find($user->id);
        $token = $eloquentUser->createToken($user->fname);
        return $token->plainTextToken;
    }
    
    public function findUserById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }
    
    public function createUser(array $data): ?User
    {
        Log::info("User   -  ");
        $user = $this->userRepository->create($data);
        Log::info(print_r(["inService"=>$user],true));
        if($user){
            // Send email with the verification code
            $verification_code = Str::random(6);

            Log::info("Verification Code created:". $verification_code);
              
             // store code in a temporary session or a table for persistence.
             //session(['transaction_verification_code' => $verificationCode]);
             Cache::put('account_verification_code_' . strval($user->id), $verification_code,now()->addMinutes(10)); // Store with an expiration time of 10 minutes
 
            Mail::to($user->email)->send(new SendMail($verification_code,"AccountActivationMail"));
            Log::info("mail sent successfully");
            return $user;
        }
        return null;
    }
    
    public function findUserByPhone(string $phone): ?User
    {
        return $this->userRepository->findByPhone($phone);
    }
    
    public function forgetPassword($email) {
        try {
            // Check if user exists
            $user = $this->userRepository->findByEmail($email);
            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            
            // Generate new password
            $newPassword = Str::random(10);
            $user->password = Hash::make($newPassword);
            $this->userRepository->save($user);
            
            // Send email with new password
            Mail::to($email)->send(new SendMail($newPassword,"forget"));
            
            return response()->json(['message' => 'A new password has been sent to your email.'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to process request.', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function resetPassword(int $id, string $currentPassword, string $newPassword)
    {
        try {
            // Find user by ID
            $user = $this->userRepository->find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }
            
            // Check if current password matches
            if (!Hash::check($currentPassword, $user->password)) {
                return response()->json(['message' => 'Current password is incorrect.'], 400);
            }
            
            // Update password
            $hashedPassword = Hash::make($newPassword);
            $user->password = $hashedPassword;
            $this->userRepository->save($user);
            
            // Send confirmation email
            
            Mail::to($user->email)->send(new SendMail($user->password,"ResetPassword"));
            
            
            return response()->json(['message' => 'Password updated successfully.'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to reset password.', 'error' => $e->getMessage()], 500);
        }
        
    }
    
    /**
     * Authenticate the user for login.
     *
     * @param  string  $email
     * @param  string  $password
     * @return User|null
     */
    public function authenticateUser(string $email, string $password): ?User
    {
        $user = $this->userRepository->findByEmail($email);
        
        if ($user && Hash::check($password, $user->password)) {
            return $user;
        }
        
        return null;
    }
    
    public function authenticateUserActivated(string $email, string $password): bool|null
    {
        $user = $this->userRepository->findByEmail($email);
        
        // Check if user exists and if password is correct
        if ($user && Hash::check($password, $user->password)) {
            // Check if account is activated
            if (!$user->email_verified_at) {
                return false; // Return null if account is not activated
            }
            return true;
        }
        
        return null;
    }
    
    /**
     * Activate the user's account.
     *
     * @param  User  $user
     * @return User|null
     */
    public function activateAccount(User $user, string $code)
    {

        // Retrieve the verification code from the session 
        $sessionVerificationCode = Cache::get('account_verification_code_'.strval($user->id));
        //  $sessionVerificationCode = (string) session('transaction_verification_code');
          Log::info("sessionVerificationCode :". $sessionVerificationCode);
          Log::info("code :". $code);
          if (strval($code) == strval($sessionVerificationCode)) {
            // Set the email_verified_at field to the current timestamp
            $user->email_verified_at = Carbon::now();
            $this->userRepository->save($user);
            return $user;

          }
          else null;
        
        
        
    }
}