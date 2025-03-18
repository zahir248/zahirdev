<?php

namespace App\Http\Controllers\API\MusicLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Str;

class MusicController extends Controller
{
    public function downloadMP3(Request $request)
    {
        // Validate that 'url' is provided
        $request->validate([
            'url' => 'required|url'
        ]);

        $outputDir = storage_path('app/public/downloads');
        $videoUrl = $request->input('url');

        // Ensure the output directory exists with proper permissions
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Generate a unique filename to avoid conflicts
        $uniqueId = Str::random(8);
        $outputTemplate = "{$outputDir}/{$uniqueId}_%(title)s.%(ext)s";
        
        // Check if we're in a Linux environment
        $isLinux = (PHP_OS_FAMILY === 'Linux');
        
        Log::info('Starting audio download', ['url' => $videoUrl, 'environment' => PHP_OS_FAMILY]);
        
        if ($isLinux) {
            // On Linux, we'll use the Linux version of yt-dlp directly
            $ytDlpPath = base_path('bin/yt-dlp');
            
            // Make sure yt-dlp exists and is executable
            if (!file_exists($ytDlpPath)) {
                // If yt-dlp doesn't exist, download it
                Log::info('Downloading yt-dlp for Linux...');
                
                // Ensure the bin directory exists
                $binDir = base_path('bin');
                if (!file_exists($binDir)) {
                    mkdir($binDir, 0755, true);
                }
                
                $downloadProcess = Process::fromShellCommandline("curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o {$ytDlpPath}");
                $downloadProcess->setTimeout(120); // 2 minutes timeout for download
                $downloadProcess->run();
                
                if (!$downloadProcess->isSuccessful()) {
                    Log::error('Failed to download yt-dlp', ['error' => $downloadProcess->getErrorOutput()]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to download yt-dlp',
                        'details' => $downloadProcess->getErrorOutput()
                    ], 500);
                }
                
                Log::info('yt-dlp downloaded successfully');
            }
            
            // Explicitly set execute permissions
            Log::info('Setting execute permissions on yt-dlp');
            $chmodProcess = Process::fromShellCommandline("chmod +x {$ytDlpPath}");
            $chmodProcess->run();
            
            if (!$chmodProcess->isSuccessful()) {
                Log::error('Failed to set execute permissions on yt-dlp', ['error' => $chmodProcess->getErrorOutput()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to set permissions on yt-dlp',
                    'details' => $chmodProcess->getErrorOutput()
                ], 500);
            }
            
            // Verify file exists and is executable
            if (!file_exists($ytDlpPath) || !is_executable($ytDlpPath)) {
                Log::error('yt-dlp is not executable', [
                    'file_exists' => file_exists($ytDlpPath),
                    'is_executable' => is_executable($ytDlpPath),
                    'file_permissions' => fileperms($ytDlpPath)
                ]);
                
                // Try PHP's chmod function directly
                chmod($ytDlpPath, 0755);
                
                if (!is_executable($ytDlpPath)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'yt-dlp is not executable',
                        'details' => 'Failed to set executable permissions'
                    ], 500);
                }
            }
            
            // Construct the command for Linux
            $command = "\"{$ytDlpPath}\" -x --audio-format mp3 -o \"{$outputTemplate}\" \"{$videoUrl}\"";
        } else {
            // Windows path (kept for local development)
            $ytDlpPath = base_path('bin/yt-dlp.exe');
            
            // Check if Wine is installed
            $checkWine = Process::fromShellCommandline('which wine');
            $checkWine->setTimeout(60);
            $checkWine->run();
            
            if (!$checkWine->isSuccessful() || empty(trim($checkWine->getOutput()))) {
                Log::info('Wine is not installed. Attempting to install...');
                
                $installWine = Process::fromShellCommandline('apt-get update && apt-get install -y wine');
                $installWine->setTimeout(300); // Give it more time
                $installWine->run();
                
                if (!$installWine->isSuccessful()) {
                    Log::error('Failed to install Wine', ['error' => $installWine->getErrorOutput()]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Wine installation failed',
                        'details' => $installWine->getErrorOutput()
                    ], 500);
                }
                
                Log::info('Wine installed successfully.');
            } else {
                Log::info('Wine is already installed.');
            }
            
            // Construct the command using wine
            $command = "wine \"{$ytDlpPath}\" -x --audio-format mp3 -o \"{$outputTemplate}\" \"{$videoUrl}\"";
        }

        // Execute command with increased timeout
        Log::info('Executing command', ['command' => $command]);
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Download failed', [
                'error' => $process->getErrorOutput(),
                'command' => $command
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Download failed',
                'details' => $process->getErrorOutput()
            ], 500);
        }

        Log::info('Command output', ['output' => $process->getOutput()]);

        // Find the most recently modified MP3 file with our unique ID
        $files = glob($outputDir . '/' . $uniqueId . '_*.mp3');
        if (empty($files)) {
            return response()->json([
                'success' => false,
                'error' => 'No MP3 file found',
                'details' => $process->getOutput(),
                'search_pattern' => $outputDir . '/' . $uniqueId . '_*.mp3'
            ], 500);
        }

        // Sort files by modification time, latest first
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latestFile = $files[0]; // Get the most recent file
        $fileName = basename($latestFile);

        return response()->download($latestFile, $fileName)->deleteFileAfterSend(true);
    }
}