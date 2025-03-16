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

        // Use Linux-compatible yt-dlp path
        $ytDlpPath = base_path('bin/yt-dlp');

        // Use a temporary writable directory (since Railway may block storage_path)
        $outputDir = "/tmp/downloads";

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $videoUrl = $request->input('url');

        Log::info('Starting audio download', ['url' => $videoUrl]);

        // Build command safely
        $command = [
            $ytDlpPath, '-x', '--audio-format', 'mp3', '-o',
            $outputDir . '/%(title)s.%(ext)s', $videoUrl
        ];

        // Use Symfony Process for better execution control
        $process = new Process($command);
        $process->run();

        // Log errors if process fails
        if (!$process->isSuccessful()) {
            Log::error('yt-dlp failed', ['error' => $process->getErrorOutput()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to download MP3',
                'details' => $process->getErrorOutput()
            ], 500);
        }

        Log::info('Command output', ['output' => $process->getOutput()]);

        // Find the most recently modified MP3 file
        $files = glob($outputDir . '/*.mp3');
        if (!$files) {
            return response()->json([
                'success' => false,
                'error' => 'No MP3 file found'
            ], 500);
        }

        // Sort files by modification time, latest first
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latestFile = $files[0];

        // Return file for download & delete after sending
        return response()->download($latestFile)->deleteFileAfterSend(true);
    }
}
