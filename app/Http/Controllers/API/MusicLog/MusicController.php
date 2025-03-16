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

        // Collect environment information
        $debugInfo = [
            'pwd' => shell_exec('pwd'),
            'ls_bin' => shell_exec('ls -la ' . base_path('bin')),
            'current_user' => shell_exec('id'),
            'file_exists' => file_exists($ytDlpPath),
            'ytdlp_path' => $ytDlpPath,
            'output_dir' => $outputDir
        ];

        // Check if file exists and try to fix permissions
        if (!file_exists($ytDlpPath)) {
            return response()->json([
                'success' => false,
                'error' => 'yt-dlp binary not found',
                'debug_info' => $debugInfo
            ], 500);
        }

        try {
            // Get initial state
            $initialPerms = substr(sprintf('%o', fileperms($ytDlpPath)), -4);
            
            // Try multiple chmod approaches
            chmod($ytDlpPath, 0755);
            $shellChmod = shell_exec("chmod 755 {$ytDlpPath} 2>&1");
            $bashChmod = shell_exec("bash -c 'chmod 755 {$ytDlpPath}' 2>&1");

            // Test file after chmod
            $permissionInfo = [
                'initial_perms' => $initialPerms,
                'final_perms' => substr(sprintf('%o', fileperms($ytDlpPath)), -4),
                'is_executable' => is_executable($ytDlpPath),
                'chmod_output' => $shellChmod,
                'bash_chmod_output' => $bashChmod
            ];

            // Test yt-dlp directly
            $versionTest = shell_exec("{$ytDlpPath} --version 2>&1");
            
            if (!is_executable($ytDlpPath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to set executable permissions',
                    'debug_info' => array_merge($debugInfo, $permissionInfo, [
                        'version_test' => $versionTest
                    ])
                ], 500);
            }

            // Ensure the output directory exists
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Try to execute yt-dlp
            $command = "bash -c '{$ytDlpPath} -x --audio-format mp3 -o \"{$outputDir}/%(title)s.%(ext)s\" \"{$videoUrl}\" 2>&1'";
            $output = shell_exec($command);

            if (empty(glob($outputDir . '/*.mp3'))) {
                return response()->json([
                    'success' => false,
                    'error' => 'Download failed',
                    'command_output' => $output,
                    'debug_info' => array_merge($debugInfo, $permissionInfo, [
                        'version_test' => $versionTest,
                        'command_used' => $command
                    ])
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
