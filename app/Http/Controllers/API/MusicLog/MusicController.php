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

        // Enhanced debug information
        $debugInfo = [
            'pwd' => shell_exec('pwd'),
            'ls_bin' => shell_exec('ls -la ' . base_path('bin')),
            'current_user' => shell_exec('id'),
            'file_exists' => file_exists($ytDlpPath),
            'file_perms' => file_exists($ytDlpPath) ? substr(sprintf('%o', fileperms($ytDlpPath)), -4) : 'N/A',
            'is_executable' => file_exists($ytDlpPath) ? is_executable($ytDlpPath) : 'N/A',
            'output_dir' => $outputDir,
            'output_dir_exists' => file_exists($outputDir),
            'output_dir_writable' => is_writable($outputDir),
            'php_version' => PHP_VERSION,
            'operating_system' => PHP_OS
        ];

        if (!file_exists($ytDlpPath)) {
            return response()->json([
                'success' => false,
                'error' => 'yt-dlp binary not found',
                'debug_info' => $debugInfo
            ], 500);
        }

        try {
            // Test if yt-dlp works
            $testProcess = new Process([$ytDlpPath, '--version']);
            $testProcess->run();
            $debugInfo['yt_dlp_version_test'] = [
                'output' => $testProcess->getOutput(),
                'error' => $testProcess->getErrorOutput(),
                'exit_code' => $testProcess->getExitCode()
            ];

            if (!$testProcess->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'yt-dlp test failed',
                    'debug_info' => $debugInfo
                ], 500);
            }

            // Try to set permissions using Process
            $chmodProcess = new Process(['chmod', '755', $ytDlpPath]);
            $chmodProcess->run();
            $debugInfo['chmod_result'] = [
                'output' => $chmodProcess->getOutput(),
                'error' => $chmodProcess->getErrorOutput(),
                'exit_code' => $chmodProcess->getExitCode()
            ];

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

            $debugInfo['download_attempt'] = [
                'command' => $process->getCommandLine(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode()
            ];

            if (!$process->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Download failed',
                    'debug_info' => $debugInfo
                ], 500);
            }

            // Find the most recently modified MP3 file
            $files = glob($outputDir . '/*.mp3');
            $debugInfo['mp3_search'] = [
                'found_files' => $files,
                'output_dir_contents' => shell_exec('ls -la ' . $outputDir)
            ];

            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No MP3 file found after download',
                    'debug_info' => $debugInfo
                ], 500);
            }

            // Sort files by modification time, latest first
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $latestFile = $files[0];
            if (!file_exists($latestFile)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generated MP3 file not found',
                    'debug_info' => $debugInfo
                ], 500);
            }

            return response()->download($latestFile)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'debug_info' => array_merge($debugInfo, [
                    'exception_trace' => $e->getTraceAsString()
                ])
            ], 500);
        }
    }

}
