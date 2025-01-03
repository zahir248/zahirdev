<?php

namespace App\Http\Controllers\API\ServiceLog;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use PDF;
use Illuminate\Support\Facades\Validator;

use App\Models\ServiceLog\Vehicle;

class VehicleController extends Controller
{
    public function index()
    {
        // Retrieve the authenticated user
        $user = Auth::user();

        // Ensure the vehicles are retrieved with service records
        $vehicles = Vehicle::where('user_id', $user->id)
            ->with([
                'serviceRecords' => function ($query) {
                    // Select necessary fields from ServiceRecord
                    $query->select('id', 'vehicle_id', 'service_cost');
                }
            ])
            ->get()
            ->map(function ($vehicle) {
                // Calculate the total service cost for each vehicle
                $vehicle->total_service_cost = $vehicle->serviceRecords->sum('service_cost');
                return $vehicle;
            });

        // Check if vehicles are found
        if ($vehicles->isEmpty()) {
            return response()->json([
                'status' => 404,
                'message' => 'No vehicles found for this user.',
            ]);
        }

        return response()->json([
            'status' => 200,
            'vehicles' => $vehicles,
            'user_name' => $user->name,
        ]);
    }

    public function store(Request $request)
    {
        // Check if the user is authenticated
        if (auth()->guest()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        // Validate the incoming request data
        $request->validate([
            'model' => 'nullable|string|max:255', // Model is optional
            'year' => 'nullable|integer|digits:4', // Year is optional
            'registration_number' => 'nullable|string|max:20', // Registration number is optional
        ]);

        // Get the authenticated user's ID
        $userId = auth()->user()->id;

        // Create the vehicle record with the validated data and associated user ID
        $vehicle = Vehicle::create([
            'model' => $request->input('model', null), // Default to null if not provided
            'year' => $request->input('year', null), // Default to null if not provided
            'registration_number' => $request->input('registration_number', null), // Default to null if not provided
            'user_id' => $userId,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Vehicle created successfully',
            'vehicle' => $vehicle,
        ], 201);
    }

    public function destroy($id)
    {
        // Check if the user is authenticated
        if (auth()->guest()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        // Find the vehicle by ID
        $vehicle = Vehicle::find($id);

        // Check if the vehicle exists
        if (!$vehicle) {
            return response()->json([
                'message' => 'Vehicle not found.',
            ], 404);
        }

        // Check if the vehicle belongs to the authenticated user (optional)
        if ($vehicle->user_id !== auth()->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to delete this vehicle.',
            ], 403);
        }

        // Delete the vehicle
        $vehicle->delete();

        // Return a success response
        return response()->json([
            'status' => 200,
            'message' => 'Vehicle deleted successfully.',
        ]);
    }

    public function show($id)
    {
        // Get the authenticated user
        $user = Auth::user();

        try {
            // Fetch the vehicle for the authenticated user
            $vehicle = Vehicle::where('user_id', $user->id)->findOrFail($id);

            // Return the vehicle data as JSON response
            return response()->json([
                'status' => 200,
                'vehicle' => $vehicle,
            ]);
        } catch (\Exception $e) {
            // If vehicle not found or any other error
            return response()->json([
                'status' => 404,
                'message' => 'Vehicle not found',
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        // Check if the user is authenticated
        if (auth()->guest()) {
            return response()->json([
                'message' => 'Unauthorized. Please log in first.',
            ], 401);
        }

        // Validate the incoming request data
        $request->validate([
            'model' => 'nullable|string|max:255', // Model is optional
            'year' => 'nullable|integer|digits:4', // Year is optional
            'registration_number' => 'nullable|string|max:20', // Registration number is optional
        ]);

        // Get the authenticated user's ID
        $userId = auth()->user()->id;

        // Find the vehicle by ID and ensure it belongs to the authenticated user
        $vehicle = Vehicle::where('id', $id)->where('user_id', $userId)->first();

        if (!$vehicle) {
            return response()->json([
                'message' => 'Vehicle not found or unauthorized.',
            ], 404);
        }

        // Update the vehicle with the validated data
        $vehicle->update([
            'model' => $request->input('model', $vehicle->model), // Only update if provided
            'year' => $request->input('year', $vehicle->year), // Only update if provided
            'registration_number' => $request->input('registration_number', $vehicle->registration_number), // Only update if provided
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Vehicle updated successfully',
            'vehicle' => $vehicle,
        ]);
    }

    public function exportPDF($id)
    {
        try {
            // Get vehicle with its service history
            $vehicle = Vehicle::with([
                'serviceRecords' => function ($query) {
                    $query->orderBy('service_date', 'desc');
                }
            ])->findOrFail($id);

            // Calculate total cost from service records
            $totalCost = $vehicle->serviceRecords->sum('service_cost');

            // Generate PDF
            $pdf = PDF::loadView('ServiceLog.pdf.vehicle-service-history', [
                'vehicle' => $vehicle,
                'serviceRecords' => $vehicle->serviceRecords,
                'totalCost' => $totalCost  // Pass the calculated total instead of vehicle->total_service_cost
            ]);

            // Return PDF for download
            return $pdf->download($vehicle->model . '_' . $vehicle->registration_number . '_report.pdf');

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
