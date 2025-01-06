<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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
        try {
            // Retrieve the authenticated user
            $user = Auth::user();

            // Debug line: Log the request data
            \Log::info('Data received from frontend:', $request->all());

            // Build basic validation rules
            $rules = [
                'name' => 'nullable|string|max:255',  // Changed to required since frontend always sends it
                'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            ];

            // Add password validation rules only if password is provided
            if ($request->has('password')) {
                $rules['password'] = [
                    'required',
                    'string',
                    'min:4',
                    'confirmed'
                ];
            }

            // Validate the input data
            $validatedData = $request->validate($rules);

            // Debug line: Log the validated data
            \Log::info('Validated data:', $validatedData);

            // Update user data
            $user->name = $validatedData['name'];
            $user->email = $validatedData['email'];

            // Update password if provided
            if ($request->filled('password')) {
                $user->password = Hash::make($validatedData['password']);

                // Debug line: Log password update (correctly formatted)
                \Log::info('Password updated for user', ['user_id' => $user->id]);
            }

            // Debug line: Log user data before save
            \Log::info('User data before save:', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'password_changed' => $request->has('password')
            ]);

            // Save the updated user data
            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'Profile updated successfully!',
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email
                ],
            ]);

        } catch (ValidationException $e) {
            \Log::error('Validation errors:', [
                'errors' => $e->errors(),
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'status' => 422,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('Exception during profile update:', [
                'message' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to update profile. Please try again later.',
            ], 500);
        }
    }

}
