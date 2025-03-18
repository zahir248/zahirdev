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
    $debugInfo = []; // Array to store debug data
    $debugInfo['request_url'] = $request->input('url');

    // Validate the request
    $request->validate([
        'url' => 'required|url'
    ]);

    $outputDir = storage_path('app/public/downloads');
    $videoUrl = $request->input('url');
    
    // Store yt-dlp in the storage directory instead of bin
    $toolsDir = storage_path('app/tools');
    $ytDlpPath = $toolsDir . '/yt-dlp';

    $debugInfo['output_directory'] = $outputDir;
    $debugInfo['yt_dlp_path'] = $ytDlpPath;

    // Ensure directories exist
    foreach ([$outputDir, $toolsDir] as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            $debugInfo[basename($dir).'_directory_created'] = true;
        }
    }

    // Check if yt-dlp exists and is executable
    if (!file_exists($ytDlpPath) || !is_executable($ytDlpPath)) {
        $debugInfo['yt_dlp_status'] = 'Not found or not executable - attempting to install';
        
        // Download yt-dlp binary
        $installProcess = Process::fromShellCommandline(
            "curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o \"$ytDlpPath\" && chmod +x \"$ytDlpPath\""
        );
        $installProcess->setTimeout(60); // Allow time for download
        $installProcess->run();
        
        $debugInfo['install_stdout'] = trim($installProcess->getOutput());
        $debugInfo['install_stderr'] = trim($installProcess->getErrorOutput());
        
        if (!$installProcess->isSuccessful() || !file_exists($ytDlpPath)) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to install yt-dlp',
                'debug' => $debugInfo
            ], 500);
        }
        
        $debugInfo['yt_dlp_installed'] = true;
    } else {
        $debugInfo['yt_dlp_status'] = 'Already installed';
    }

    // Check for FFmpeg
    $checkFfmpeg = Process::fromShellCommandline('which ffmpeg');
    $checkFfmpeg->run();
    $debugInfo['ffmpeg_check'] = trim($checkFfmpeg->getOutput());
    
    if (!$checkFfmpeg->isSuccessful()) {
        return response()->json([
            'success' => false,
            'error' => 'FFmpeg is required but not installed',
            'debug' => $debugInfo
        ], 500);
    }

    // Construct the yt-dlp command
    $command = "\"$ytDlpPath\" -x --audio-format mp3 -o \"$outputDir/%(title)s.%(ext)s\" \"$videoUrl\"";
    $debugInfo['command'] = $command;

    // Execute command
    $process = Process::fromShellCommandline($command);
    $process->setTimeout(300); // Set a longer timeout for large files
    $process->run();

    // Capture command output
    $debugInfo['yt_dlp_stdout'] = trim($process->getOutput());
    $debugInfo['yt_dlp_stderr'] = trim($process->getErrorOutput());

    if (!$process->isSuccessful()) {
        return response()->json([
            'success' => false,
            'error' => 'Download failed',
            'debug' => $debugInfo
        ], 500);
    }

    // Find the latest MP3 file
    $files = glob($outputDir . '/*.mp3');
    $debugInfo['mp3_files_found'] = $files ? count($files) : 0;

    if (!$files) {
        return response()->json([
            'success' => false,
            'error' => 'No MP3 file found',
            'debug' => $debugInfo
        ], 500);
    }

    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    $latestFile = $files[0];

    $debugInfo['latest_file'] = $latestFile;

    return response()->json([
        'success' => true,
        'message' => 'Download successful',
        'file_url' => asset('storage/downloads/' . basename($latestFile)),
        'debug' => $debugInfo
    ]);
}

}
