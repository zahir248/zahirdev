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

    $ytDlpPath = base_path('bin/yt-dlp.exe'); 
    $outputDir = storage_path('app/public/downloads');
    $videoUrl = $request->input('url');

    $debugInfo['yt_dlp_path'] = $ytDlpPath;
    $debugInfo['output_directory'] = $outputDir;

    // Ensure output directory exists
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
        $debugInfo['directory_created'] = true;
    } else {
        $debugInfo['directory_exists'] = true;
    }

    // Check if yt-dlp is executable
    if (!is_executable($ytDlpPath)) {
        $debugInfo['yt_dlp_executable'] = false;
        return response()->json([
            'success' => false,
            'error' => 'yt-dlp is not executable',
            'debug' => $debugInfo
        ], 500);
    } else {
        $debugInfo['yt_dlp_executable'] = true;
    }

    // Check if Wine is installed
    $checkWine = Process::fromShellCommandline('which wine');
    $checkWine->run();
    
    $debugInfo['wine_check_output'] = trim($checkWine->getOutput());
    $debugInfo['wine_check_error'] = trim($checkWine->getErrorOutput());

    if (!$checkWine->isSuccessful() || empty(trim($checkWine->getOutput()))) {
        return response()->json([
            'success' => false,
            'error' => 'Wine is not installed',
            'debug' => $debugInfo
        ], 500);
    }

    // Construct the yt-dlp command
    $command = "wine \"$ytDlpPath\" -x --audio-format mp3 -o \"$outputDir/%(title)s.%(ext)s\" \"$videoUrl\"";
    $debugInfo['command'] = $command;

    // Execute command
    $process = Process::fromShellCommandline($command);
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
