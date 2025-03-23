<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;
use App\Application\Services\UserService;
use App\Models\FirebaseToken;
use App\Models\User; 

class AuthController extends Controller
{
    private UserService $userService;

    public function __construct(UserRepository $userRepository, UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Register a new user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        Log::info("User Email - {$request->email} ");
         
        try {
            // Validate the request data
            $fields = $request->validate([
                'fname' => 'required|string',
                'lname' => 'required|string',
                'phone' => 'required|string|unique:users',
                'password' => 'required|string|min:6',
                'email' => 'email|unique:users', // Ensure email is unique if provided
            ]);
            Log::info("User info - {$request->fname} ");
            // Create user using the repository
            $user = $this->userService->createUser($fields);
            Log::info("User created successfully with ID: {$user->id}, Email: {$user->email}");

            // Generate token
            $token = $this->userService->generateToken($user);
            Log::info(message: "token :". $token);
            Log::info(message: "token firebase :". $request->firebase_token);

            // Return response
            return response()->json([
                'message' => 'User registered successfully. Check your email for the verification code.',
                'user' => $user,
                'token' => $token,
                'result' => true,
                'firebase_token' => $request->firebase_token
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to process the registration. Please try again later.',
            'result' => false,], 500);
        }
    }

    /**
     * Login an existing user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate the request data
        $request->validate([
            'email' => 'email',
            'password' => 'required|string',
            //'firebase_token' => 'required|string', // Ensure Firebase token is provided ,
        ]);

        try {
            // Authenticate user using the repository
            $user = $this->userService->authenticateUser($request->email, $request->password);
            info(print_r($user,true));

            if (!$user) {
                return response()->json([
                    'message' => 'The provided credentials are not correct ',
                ], 401);
            }
            // Check activated account
            if (!$user->email_verified_at) {
                return response()->json(['message' => 'Please verify your email to activate your account.'], 403);
            }
            
            $activated = $this->userService->authenticateUserActivated($request->email, $request->password);
          // save firebaase token
          Log::info(message: "token firebase :". $request->firebase_token);
          if($request->firebase_token!='')
            $firebaseToken = FirebaseToken::updateOrCreate(
                ['user_id' => $user->id, 'token' => $request->firebase_token],
                ['active' => true]
            )->only(['token']);
            // Generate token
            $token = $this->userService->generateToken($user);

            // Return response
            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'token' => $token,
                'activated' => $activated,
                'result' => true,
                'firebase_token' => $firebaseToken 
            ], status: 200);

        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while logging in. Please try again later.',
                'result' => false,
        ], 500);
        }
    }

    public function verifyCode(Request $request)
        {
            $request->validate([
                'email' => 'required|email',
                'verification_code' => 'required|string',
            ]);

            $user = User::where('email', $request->email)
                        ->where('verification_code', $request->verification_code)
                        ->first();

            if (!$user) {
                return response()->json(['message' => 'Invalid verification code.'], 400);
            }

            // Activate the account
            $user->email_verified_at = now();
            $user->verification_code = null; // Remove code after activation
            $user->save();

            return response()->json(['message' => 'Account successfully activated.'], 200);
        }

    /**
     * Activate the user's account.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateAccount(Request $request)
    {
        try {
            Log::info("sessionVerificationCode :". $request->user_id);
            // Validate the request data
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'code' => 'required|string',
            ]);

            // Retrieve user
            $user = $this->userService->findUserById($request->user_id);

            // Activate the account
            $this->userService->activateAccount($user, $request->code);

            return response()->json([
                'message' => 'Account activated successfully',
                'user' => $user,
                'result' => true
            ], 200);

        } catch (\Exception $e) {
            Log::error('Activation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'An error occurred while activating the account. Please try again later.',
                'result' => false
            ], 500);
        }
    }

    /**
     * Logout the user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $user->tokens()->delete();  Log::info("Firebase token received: " . json_encode($request->firebase_token));

            // Ensure firebase_token is present in request
            if ($request->has('firebase_token') && !empty($request->firebase_token)) {
                $updated = FirebaseToken::where('user_id', $user->id)
                    ->where('token', $request->firebase_token)
                    ->update(['active' => false]);
    
                // Log if the update was successful
                if ($updated) {
                    Log::info("Firebase token deactivated successfully for user ID: " . $user->id);
                } else {
                    Log::warning("Firebase token not found or already inactive for user ID: " . $user->id);
                }
            }
            

            return response()->json([
                'message' => 'Logged out successfully',
                'result' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred while logging out. Please try again later.,',
            'result' => false
        ], 500);
        }
    }

    /**
     * Get the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }


      // Forget password and send a new password via email
      public function forgetPassword(Request $request) {
        //$2y$12$W9sP8tWP5OpQ0lD.rpZuseaT7DG3uqiR5pxVhXSq4Zib2Cb47RxbG
        // Validate the email address
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            return $this->userService->forgetPassword($request->email);
        } catch (\Exception $e) {
            Log::error('Error in forgetPassword: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred, please try again later.'], 500);
        }
    }

    // Reset password with current password and new password
    public function resetPassword(Request $request) {
        // Validate the request data
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        try {
            return $this->userService->resetPassword($request->user()->id, $request->current_password, $request->new_password);
        } catch (\Exception $e) {
            Log::error('Error in resetPassword: ' . $e->getMessage());
            return response()->json(['message' => 'An error occurred, please try again later.'], 500);
        }
    }
}
