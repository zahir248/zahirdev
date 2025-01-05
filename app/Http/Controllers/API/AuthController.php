<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate incoming data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        // If validation fails, return a response with errors
        if ($validator->fails()) {
            return response()->json([
                'status' => 400,
                'errors' => $validator->errors(),
            ]);
        }

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // Hash the password before storing it
        ]);

        // Return success response
        return response()->json([
            'status' => 200,
            'message' => 'Registration successful!',
            'user' => $user,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 200,
                'token' => $token,
                'user' => $user,
            ]);
        } else {
            return response()->json([
                'status' => 401,
                'message' => 'Invalid email or password.',
            ], 401);
        }
    }

    public function update(Request $request)
    {
        // Retrieve the authenticated user
        $user = Auth::user();

        // Validate the input data
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id, // Ensure the email is unique, but allow the current user's email
        ]);

        // Update the user data
        try {
            $user->name = $validatedData['name'];
            $user->email = $validatedData['email'];
            
            // Save the updated user data
            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'Profile updated successfully!',
                'user' => $user, // Return the updated user object
            ]);
        } catch (\Exception $e) {
            // Handle errors (e.g., if the database save fails)
            return response()->json([
                'status' => 500,
                'message' => 'Failed to update profile. Please try again later.',
            ], 500);
        }
    }
}
