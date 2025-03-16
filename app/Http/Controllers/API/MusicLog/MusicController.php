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

        $ytDlpPath = base_path('bin/yt-dlp_linux'); // Changed to Linux binary
        $outputDir = storage_path('app/public/downloads');
        $videoUrl = $request->input('url');

        // Debug current state
        \Log::info('Binary status check', [
            'file_exists' => file_exists($ytDlpPath),
            'current_perms' => file_exists($ytDlpPath) ? substr(sprintf('%o', fileperms($ytDlpPath)), -4) : 'file_missing',
            'is_executable' => is_executable($ytDlpPath),
            'user' => shell_exec('whoami'),
            'file_owner' => file_exists($ytDlpPath) ? posix_getpwuid(fileowner($ytDlpPath)) : 'file_missing'
        ]);

        // Try multiple permission setting approaches
        if (file_exists($ytDlpPath)) {
            try {
                // Method 1: PHP chmod
                chmod($ytDlpPath, 0755);
                
                // Method 2: Shell chmod
                shell_exec("chmod 755 {$ytDlpPath} 2>&1");
                
                // Method 3: Explicit shell chmod with sudo (if available)
                shell_exec("sudo chmod 755 {$ytDlpPath} 2>&1");
                
                // Log the results after permission changes
                \Log::info('Permission update results', [
                    'new_perms' => substr(sprintf('%o', fileperms($ytDlpPath)), -4),
                    'is_executable' => is_executable($ytDlpPath)
                ]);

                if (!is_executable($ytDlpPath)) {
                    throw new \Exception('Failed to set executable permissions');
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'error' => 'Permission setting failed',
                    'message' => $e->getMessage(),
                    'details' => [
                        'current_perms' => substr(sprintf('%o', fileperms($ytDlpPath)), -4),
                        'is_executable' => is_executable($ytDlpPath)
                    ]
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'error' => 'yt-dlp_linux binary not found',
                'path' => $ytDlpPath
            ], 500);
        }

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        \Log::info('Starting audio download', ['url' => $videoUrl]);

        // Use bash explicitly for Linux environment
        $command = "bash -c '{$ytDlpPath} -x --audio-format mp3 -o \"{$outputDir}/%(title)s.%(ext)s\" \"{$videoUrl}\" 2>&1'";
        $output = shell_exec($command);

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
