<?php

namespace App\Http\Controllers\API\MusicLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    public function downloadMP3(Request $request)
    {
        // Validate that 'url' is provided
        $request->validate([
            'url' => 'required|url'
        ]);

        // Check if shell_exec() is available
        if (!function_exists('shell_exec')) {
            return response()->json([
                'success' => false,
                'error' => 'shell_exec() is disabled on this server.'
            ], 500);
        }

        // Check if shell_exec() is restricted in php.ini
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        if (in_array('shell_exec', $disabledFunctions)) {
            return response()->json([
                'success' => false,
                'error' => 'shell_exec() is disabled in php.ini.'
            ], 500);
        }

        $ytDlpPath = base_path('bin/yt-dlp.exe'); // Get bin path dynamically
        $outputDir = storage_path('app/public/downloads'); // Store in storage directory
        $videoUrl = $request->input('url');

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        \Log::info('Starting audio download', ['url' => $videoUrl]);

        // Construct the shell command
        $command = "\"{$ytDlpPath}\" -x --audio-format mp3 -o \"{$outputDir}/%(title)s.%(ext)s\" \"{$videoUrl}\"";

        // Execute shell command and capture output
        $output = shell_exec($command . " 2>&1");

        // Log output
        \Log::info('Command output', ['output' => $output]);

        // Check if the command actually executed
        if ($output === null) {
            return response()->json([
                'success' => false,
                'error' => 'shell_exec() execution failed or is blocked by security policies.'
            ], 500);
        }

        // Find the most recently modified MP3 file
        $files = glob($outputDir . '/*.mp3');
        if (!$files) {
            return response()->json([
                'success' => false,
                'error' => 'No MP3 file found',
                'details' => $output
            ], 500);
        }

        // Sort files by modification time, latest first
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latestFile = $files[0]; // Get the most recent file

        return response()->download($latestFile)->deleteFileAfterSend(true);
    }
}
