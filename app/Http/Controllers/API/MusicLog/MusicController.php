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

        $ytDlpPath = base_path('bin/yt-dlp_linux');
        $outputDir = storage_path('app/public/downloads');
        $videoUrl = $request->input('url');

        // Debug current state
        $debugInfo = [
            'pwd' => shell_exec('pwd'),
            'ls_bin' => shell_exec('ls -la ' . base_path('bin')),
            'current_user' => shell_exec('id'),
            'file_exists' => file_exists($ytDlpPath)
        ];

        if (!file_exists($ytDlpPath)) {
            return response()->json([
                'success' => false,
                'error' => 'yt-dlp binary not found',
                'debug_info' => $debugInfo
            ], 500);
        }

        try {
            // Try to set permissions using Process
            $chmodProcess = new Process(['chmod', '755', $ytDlpPath]);
            $chmodProcess->run();

            // Create output directory if it doesn't exist
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Run yt-dlp using Process
            $process = new Process([
                $ytDlpPath,
                '-x',
                '--audio-format', 'mp3',
                '-o', $outputDir . '/%(title)s.%(ext)s',
                $videoUrl
            ]);
            
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Download failed',
                    'output' => $process->getErrorOutput(),
                    'command' => $process->getCommandLine(),
                    'debug_info' => array_merge($debugInfo, [
                        'exit_code' => $process->getExitCode(),
                        'working_directory' => $process->getWorkingDirectory()
                    ])
                ], 500);
            }

            // Find the most recently modified MP3 file
            $files = glob($outputDir . '/*.mp3');
            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No MP3 file found',
                    'output' => $process->getOutput(),
                    'error_output' => $process->getErrorOutput()
                ], 500);
            }

            // Sort files by modification time, latest first
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $latestFile = $files[0];
            return response()->download($latestFile)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'debug_info' => $debugInfo
            ], 500);
        }
    }

}
