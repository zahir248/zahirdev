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

        $ytDlpPath = base_path('app/bin/yt-dlp_linux'); // Get binary path dynamically
        $outputDir = storage_path('app/public/downloads'); // Store in storage directory
        $videoUrl = $request->input('url');

        // Ensure yt-dlp has execute permissions
        if (!is_executable($ytDlpPath)) {
            Log::info('Setting execute permissions for yt-dlp');
            shell_exec("chmod +x " . escapeshellarg($ytDlpPath));
        }

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        Log::info('Starting audio download', ['url' => $videoUrl]);

        // Construct the shell command
        $command = escapeshellcmd("$ytDlpPath -x --audio-format mp3 -o \"$outputDir/%(title)s.%(ext)s\" \"$videoUrl\"");
        $output = shell_exec($command . " 2>&1"); // Capture both stdout and stderr

        Log::info('Command output', ['output' => $output]);

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
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $latestFile = $files[0]; // Get the most recent file

        return response()->download($latestFile)->deleteFileAfterSend(true);
    }
}
