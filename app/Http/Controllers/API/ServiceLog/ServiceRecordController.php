<?php

namespace App\Http\Controllers\API\ServiceLog;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Models\ServiceLog\ServiceRecord;
use App\Models\ServiceLog\Vehicle;

class ServiceRecordController extends Controller
{
    public function index($vehicleId)
    {
        try {
            // Eager load the associated Vehicle model
            $serviceRecords = ServiceRecord::with('vehicle')  // Load vehicle data for each service record
                ->where('vehicle_id', $vehicleId)
                ->get();

            if ($serviceRecords->isEmpty()) {
                return response()->json([
                    'status' => 404,
                    'message' => 'No service records found for this vehicle.',
                ], 404);
            }

            // Include vehicle data in the response
            return response()->json([
                'status' => 200,
                'history' => $serviceRecords,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while fetching service records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, $vehicleId)
    {
        // Validate the incoming data
        $validator = Validator::make($request->all(), [
            'service_date' => 'nullable|date', // Nullable field
            'service_place' => 'nullable|string|max:255', // Nullable field
            'service_cost' => 'nullable|numeric', // Nullable field
            'description' => 'nullable|string|max:1000', // Nullable field
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the vehicle exists
        $vehicle = Vehicle::find($vehicleId);
        if (!$vehicle) {
            return response()->json(['message' => 'Vehicle not found'], 404);
        }

        // Create a new service record
        $serviceRecord = ServiceRecord::create([
            'vehicle_id' => $vehicleId,
            'service_date' => $request->service_date, // Nullable field
            'service_place' => $request->service_place, // Nullable field
            'service_cost' => $request->service_cost, // Nullable field
            'description' => $request->description, // Nullable field
        ]);

        // Return the created service record
        return response()->json([
            'status' => 200,
            'message' => 'Service record created successfully',
            'service_record' => $serviceRecord, // Return the created service record
        ], 201);
    }

    public function destroy($id)
    {
        // Find the service record by its ID
        $serviceRecord = ServiceRecord::find($id);

        // Check if the service record exists
        if (!$serviceRecord) {
            return response()->json([
                'message' => 'Service record not found.'
            ], 404);
        }

        // Delete the service record
        $serviceRecord->delete();

        // Return a success response
        return response()->json([
            'message' => 'Service record deleted successfully.'
        ], 200);
    }
}
