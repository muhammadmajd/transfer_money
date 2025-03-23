<?php

namespace App\Http\Controllers;

use App\Application\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //protected $userRepository;
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function checkUserByPhone(Request $request)
    {
        
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
        ]);

        $user = $this->userService->findUserByPhone($request->phone);
                

        if ($user) {
            return response()->json([
                'message' => 'User exists',
                'exists' => true,
                'user' => $user,
            ], 200);
        } else {
            return response()->json([
                'message' => 'User does not exist',
                'exists' => false,
            ], 404);
        }
    }
}
