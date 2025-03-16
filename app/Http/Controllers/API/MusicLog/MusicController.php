<?php

namespace App\Http\Controllers\API\MusicLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MusicController extends Controller
{
    public function downloadMP3(Request $request)
    {
        // Validate that 'url' is provided
        $request->validate([
            'url' => 'required|url'
        ]);

        $ytDlpPath = base_path('bin/yt-dlp.exe'); // Get bin path dynamically
        $outputDir = storage_path('app/public/downloads'); // Store in storage directory
        $videoUrl = $request->input('url');

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (!is_writable($outputDir)) {
            chmod($outputDir, 0755);
        }

        \Log::info('Starting audio download', ['url' => $videoUrl]);

        // Construct the shell command
        $command = "\"{$ytDlpPath}\" -x --audio-format mp3 -o \"{$outputDir}/%(title)s.%(ext)s\" \"{$videoUrl}\"";
        $output = shell_exec($command . " 2>&1"); // Capture both stdout and stderr

        \Log::info('Command output', ['output' => $output]);

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
