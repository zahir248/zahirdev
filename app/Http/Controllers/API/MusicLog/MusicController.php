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
        try {
            // Validate that 'url' is provided
            $request->validate([
                'url' => 'required|url'
            ]);

            // Get relevant paths
            $ytDlpPath = base_path('bin/yt-dlp');
            $ffmpegPath = base_path('bin/ffmpeg');
            $outputDir = storage_path('app/public/downloads');
            $videoUrl = $request->input('url');

            \Log::info('Starting audio download', [
                'url' => $videoUrl, 
                'ytDlpPath' => $ytDlpPath,
                'ffmpegPath' => $ffmpegPath
            ]);
            
            // Check if yt-dlp executable exists (try without .exe first)
            if (!file_exists($ytDlpPath)) {
                // Try with .exe as fallback
                $ytDlpPath = base_path('bin/yt-dlp.exe');
                \Log::info('Trying fallback path for yt-dlp', ['fallbackPath' => $ytDlpPath]);
                
                if (!file_exists($ytDlpPath)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'yt-dlp executable not found',
                    ], 500);
                }
            }
            
            // Ensure the output directory exists
            if (!file_exists($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    \Log::error('Failed to create output directory', ['dir' => $outputDir]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to create output directory',
                    ], 500);
                }
            }
            
            // Check if directory is writable
            if (!is_writable($outputDir)) {
                \Log::error('Output directory is not writable', ['dir' => $outputDir]);
                return response()->json([
                    'success' => false,
                    'error' => 'Output directory is not writable',
                ], 500);
            }

            // Generate a unique filename prefix
            $uniqueId = uniqid('download_');
            $outputPattern = "{$outputDir}/{$uniqueId}_%(title)s.%(ext)s";
            
            // Determine if on Windows or Linux
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || file_exists(base_path('bin/yt-dlp.exe'));
            
            // Prepare the command
            if ($isWindows) {
                // Windows-style command with .exe files
                $command = "\"{$ytDlpPath}\" -x --audio-format mp3 --ffmpeg-location \"{$ffmpegPath}\" -o \"{$outputPattern}\" \"{$videoUrl}\"";
                \Log::info('Windows command: ' . $command);
                $output = shell_exec($command . " 2>&1");
                \Log::info('Command output', ['output' => $output]);
            } else {
                // Linux environment - use Wine to run Windows executables if needed
                if (strpos($ytDlpPath, '.exe') !== false) {
                    \Log::info('Attempting to use wine to run Windows executable');
                    
                    // Check if wine is installed
                    $wineCheckProcess = new Process(['which', 'wine']);
                    $wineCheckProcess->run();
                    
                    if (!$wineCheckProcess->isSuccessful()) {
                        \Log::error('Wine is not installed but needed to run .exe files');
                        return response()->json([
                            'success' => false,
                            'error' => 'System configuration error',
                            'details' => 'Wine is required to run Windows executables on Linux'
                        ], 500);
                    }
                    
                    $process = new Process([
                        'wine',
                        $ytDlpPath,
                        '-x',
                        '--audio-format', 'mp3',
                        '--ffmpeg-location', $ffmpegPath,
                        '-o', $outputPattern,
                        $videoUrl
                    ]);
                } else {
                    // Native Linux binaries
                    $process = new Process([
                        $ytDlpPath,
                        '-x',
                        '--audio-format', 'mp3',
                        '--ffmpeg-location', $ffmpegPath,
                        '-o', $outputPattern,
                        $videoUrl
                    ]);
                }
                
                $process->setTimeout(300); // 5 minutes timeout
                \Log::info('Executing command', ['command' => $process->getCommandLine()]);
                
                $process->run();
                
                if (!$process->isSuccessful()) {
                    \Log::error('Download process failed', [
                        'exitCode' => $process->getExitCode(),
                        'errorOutput' => $process->getErrorOutput()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'Download failed',
                        'details' => $process->getErrorOutput()
                    ], 500);
                }
                
                $output = $process->getOutput();
                \Log::info('Command output', ['output' => $output]);
            }

            // Find the most recently modified MP3 file with our unique ID
            $files = glob($outputDir . '/' . $uniqueId . '_*.mp3');
            if (!$files) {
                // Try without the unique ID as fallback
                $files = glob($outputDir . '/*.mp3');
                if (!$files) {
                    \Log::error('No MP3 files found after download', ['dir' => $outputDir]);
                    return response()->json([
                        'success' => false,
                        'error' => 'No MP3 file found',
                        'details' => $output
                    ], 500);
                }
            }

            // Sort files by modification time, latest first
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $latestFile = $files[0]; // Get the most recent file
            \Log::info('Sending file', ['file' => $latestFile]);

            return response()->download($latestFile)->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            \Log::error('Exception in downloadMP3', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}