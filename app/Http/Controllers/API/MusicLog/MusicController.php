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

    // Ensure yt-dlp has execute permissions using PHP's chmod
    if (!is_executable($ytDlpPath)) {
        \Log::info('Setting execute permission for yt-dlp');
        chmod($ytDlpPath, 0755);
        
        // Double-check if permissions were applied
        if (!is_executable($ytDlpPath)) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot set executable permissions for yt-dlp',
                'path' => $ytDlpPath
            ], 500);
        }
    }

    // Ensure the output directory exists
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    if (!is_writable($outputDir)) {
        chmod($outputDir, 0755);
    }

    \Log::info('Starting audio download', ['url' => $videoUrl]);

    // Use Process component instead of shell_exec
    $process = new Process([
        $ytDlpPath,
        '-x',
        '--audio-format', 'mp3',
        '-o', $outputDir . '/%(title)s.%(ext)s',
        $videoUrl
    ]);
    
    try {
        $process->setTimeout(300); // 5-minute timeout
        $process->run();
        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();
        
        \Log::info('Command output', [
            'output' => $output,
            'error' => $errorOutput
        ]);
        
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Process failed',
            'details' => $e->getMessage()
        ], 500);
    }

    // Find the most recently modified MP3 file
    $files = glob($outputDir . '/*.mp3');
    if (empty($files)) {
        return response()->json([
            'success' => false,
            'error' => 'No MP3 file found',
            'output' => $output,
            'error_output' => $errorOutput ?? null
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
