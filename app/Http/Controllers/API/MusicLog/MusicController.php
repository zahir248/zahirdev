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

        $ytDlpPath = base_path('bin/yt-dlp.exe'); // Windows binary path
        $outputDir = storage_path('app/public/downloads'); // Store in storage directory
        $videoUrl = $request->input('url');

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Check if Wine is installed
        $checkWine = Process::fromShellCommandline('which wine');
        $checkWine->run();

        if (!$checkWine->isSuccessful() || empty(trim($checkWine->getOutput()))) {
            Log::info('Wine is not installed. Attempting to install...');
            
            $installWine = Process::fromShellCommandline('apt update && apt install -y wine');
            $installWine->run();

            if (!$installWine->isSuccessful()) {
                Log::error('Failed to install Wine', ['error' => $installWine->getErrorOutput()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Wine installation failed',
                    'details' => $installWine->getErrorOutput()
                ], 500);
            }

            Log::info('Wine installed successfully.');
        } else {
            Log::info('Wine is already installed.');
        }

        Log::info('Starting audio download', ['url' => $videoUrl]);

        // Construct the shell command using wine
        $command = "wine \"{$ytDlpPath}\" -x --audio-format mp3 -o \"{$outputDir}/%(title)s.%(ext)s\" \"{$videoUrl}\"";

        // Execute command and capture output
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Download failed', ['error' => $process->getErrorOutput()]);
            return response()->json([
                'success' => false,
                'error' => 'Download failed',
                'details' => $process->getErrorOutput()
            ], 500);
        }

        Log::info('Command output', ['output' => $process->getOutput()]);

        // Find the most recently modified MP3 file
        $files = glob($outputDir . '/*.mp3');
        if (!$files) {
            return response()->json([
                'success' => false,
                'error' => 'No MP3 file found',
                'details' => $process->getOutput()
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
