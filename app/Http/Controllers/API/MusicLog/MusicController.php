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

        $ytDlpPath = base_path('bin/yt-dlp_linux'); // Get bin path dynamically
        $outputDir = storage_path('app/public/downloads'); // Store in storage directory
        $videoUrl = $request->input('url');

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (!is_writable($outputDir)) {
            chmod($outputDir, 0755);
        }

        if (!is_executable($ytDlpPath)) {
            \Log::info('Fixing permissions for yt-dlp');
            shell_exec("chmod +x {$ytDlpPath} && chown www-data:www-data {$ytDlpPath}");
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
    public function debugYtDlp()
{
    $ytDlpPath = base_path('bin/yt-dlp_linux');

    // Check if the file exists
    $fileExists = file_exists($ytDlpPath);
    
    // Check permissions
    $filePermissions = shell_exec("ls -l " . escapeshellarg($ytDlpPath));
    
    // Check if the file is executable
    $isExecutable = is_executable($ytDlpPath);
    
    // Try running yt-dlp and capture output
    $testCommand = "{$ytDlpPath} --version";
    $output = shell_exec($testCommand . " 2>&1");

    // Log results
    \Log::info('Debugging yt-dlp', [
        'file_exists' => $fileExists,
        'permissions' => $filePermissions,
        'is_executable' => $isExecutable,
        'output' => $output
    ]);

    return response()->json([
        'file_exists' => $fileExists,
        'permissions' => $filePermissions,
        'is_executable' => $isExecutable,
        'output' => $output
    ]);
}


}
