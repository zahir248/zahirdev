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

            $videoUrl = $request->input('url');
            Log::info('MP3 download request received', ['url' => $videoUrl]);

            // Determine the correct binary path based on environment
            $ytDlpPath = PHP_OS === 'WINNT' ? base_path('bin/yt-dlp.exe') : '/usr/local/bin/yt-dlp';
            
            // Check if binary exists
            if (!file_exists($ytDlpPath)) {
                Log::error('yt-dlp binary not found', ['path' => $ytDlpPath]);
                return response()->json([
                    'success' => false,
                    'error' => 'Server configuration error',
                    'details' => 'yt-dlp binary not found at: ' . $ytDlpPath
                ], 500);
            }

            // Set up output directory
            $outputDir = storage_path('app/public/downloads');
            
            // Ensure the output directory exists
            if (!file_exists($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    Log::error('Failed to create output directory', ['dir' => $outputDir]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to create output directory',
                    ], 500);
                }
            }

            // Check directory permissions
            if (!is_writable($outputDir)) {
                Log::error('Output directory is not writable', ['dir' => $outputDir]);
                return response()->json([
                    'success' => false,
                    'error' => 'Server configuration error',
                    'details' => 'Output directory is not writable'
                ], 500);
            }

            Log::info('Using yt-dlp binary', ['path' => $ytDlpPath]);
            Log::info('Output directory', ['dir' => $outputDir]);

            // Create a unique filename prefix to avoid conflicts
            $uniquePrefix = uniqid('download_');
            $outputTemplate = "{$outputDir}/{$uniquePrefix}_%(title)s.%(ext)s";
            
            // Use Symfony Process to run the command safely
            $process = new Process([
                $ytDlpPath,
                '-x',
                '--audio-format', 'mp3',
                '--restrict-filenames',
                '-o', $outputTemplate,
                $videoUrl
            ]);
            
            // Set a longer timeout if needed (5 minutes)
            $process->setTimeout(300);
            
            Log::info('Executing command', [
                'command' => $process->getCommandLine()
            ]);
            
            $process->run();
            
            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                Log::error('Download process failed', [
                    'exit_code' => $process->getExitCode(),
                    'error_output' => $errorOutput
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Download process failed',
                    'details' => $errorOutput
                ], 500);
            }
            
            $output = $process->getOutput();
            Log::info('Download process output', ['output' => $output]);
            
            // Find the generated MP3 file using the unique prefix
            $files = glob($outputDir . '/' . $uniquePrefix . '_*.mp3');
            
            if (empty($files)) {
                Log::error('No MP3 file found after successful process execution', [
                    'dir' => $outputDir,
                    'pattern' => $uniquePrefix . '_*.mp3'
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'No MP3 file found after processing',
                    'output' => $output
                ], 500);
            }
            
            $downloadFile = $files[0]; // Get the first matching file
            $fileName = basename($downloadFile);
            
            Log::info('Returning MP3 file', ['file' => $fileName]);
            
            return response()->download($downloadFile, $fileName)->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            Log::error('Exception in downloadMP3', [
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