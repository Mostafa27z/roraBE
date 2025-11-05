<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Modules\Auth\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:100',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:6|confirmed',
        'phone' => 'nullable|string|max:15', // Add phone validation
        'roles' => 'array', // ['client', 'admin']
    ]);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'phone' => $request->phone, // Store phone number
    ]);

    // Assign roles (default = client)
    $roleNames = $request->roles ?? ['client'];
    $roles = Role::whereIn('name', $roleNames)->get();
    $user->roles()->attach($roles);

    return response()->json([
        'message' => 'User registered successfully',
        'user' => $user->load('roles')
    ], 200);
}


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Invalid credentials.']]);
        }

        // Simplified token creation (no abilities)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('roles'),
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
    public function updateProfile(Request $request)
{
    $request->validate([
        'name' => 'nullable|string|max:100',
        'email' => 'nullable|email|unique:users,email,' . $request->user()->id,
        'phone' => 'nullable|string|max:15', // Validate phone
        'old_password' => 'nullable|string', // Validate old password if changing the password
        'password' => 'nullable|string|min:6|confirmed', // New password, confirmed
    ]);

    $user = $request->user();

    // Only update fields that are provided
    if ($request->has('name')) {
        $user->name = $request->name;
    }

    if ($request->has('email')) {
        $user->email = $request->email;
    }

    if ($request->has('phone')) {
        $user->phone = $request->phone;
    }

    // Handle password update
    if ($request->has('old_password') && $request->has('password')) {
        // Check if the old password is correct
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'Old password is incorrect.'
            ], 400);
        }

        // Update the password if old password is correct
        $user->password = Hash::make($request->password);
    }

    $user->save();

    return response()->json([
        'message' => 'Profile updated successfully',
        'user' => $user
    ], 200);
}

}
