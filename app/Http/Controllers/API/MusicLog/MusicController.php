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
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = escapeshellarg($request->input('url')); // Prevent shell injection

        // Use system-installed Python and yt-dlp
        $pythonPath = 'python3';
        $ytDlpPath = 'yt-dlp';

        // Check if Python is installed
        $checkPython = shell_exec("which python3");
        if (empty(trim($checkPython))) {
            Log::error("Python3 is not installed. Installing...");
            shell_exec("apt update && apt install -y python3 python3-pip");
        }

        // Check if yt-dlp is installed
        $checkYtDlp = shell_exec("which yt-dlp");
        if (empty(trim($checkYtDlp))) {
            Log::error("yt-dlp is not installed. Installing...");
            shell_exec("pip3 install --upgrade yt-dlp");
        }

        // Verify again after installation
        if (empty(trim(shell_exec("which yt-dlp")))) {
            Log::error("yt-dlp installation failed.");
            return response()->json(['message' => 'yt-dlp installation failed'], 500);
        }

        // Get video title
        $getTitleCommand = "{$pythonPath} -m yt_dlp --get-title {$url}";
        $title = trim(shell_exec($getTitleCommand));

        if (empty($title)) {
            Log::error("Failed to retrieve video title.");
            return response()->json(['message' => 'Failed to retrieve video title'], 500);
        }

        // Sanitize filename
        $safeTitle = preg_replace('/[\/:*?"<>|]/', '_', $title);
        $filename = "{$safeTitle}.mp3";
        $outputFile = "/tmp/{$filename}"; // Use /tmp for ephemeral storage

        // Run yt-dlp command to download and convert
        $ytDlpCommand = "{$pythonPath} -m yt_dlp -x --audio-format mp3 -o " . escapeshellarg($outputFile) . " " . $url;

        Log::info("Starting yt-dlp download...", ['command' => $ytDlpCommand]);

        $output = shell_exec($ytDlpCommand . ' 2>&1');

        Log::info("yt-dlp output:", ['output' => $output]);

        if (!file_exists($outputFile)) {
            Log::error("yt-dlp failed: File was not created.", ['output' => $output]);
            return response()->json(['message' => 'Conversion failed. Check logs for details.'], 500);
        }

        Log::info("yt-dlp download completed successfully.", ['file' => $outputFile]);

        // Automatically delete file after response
        register_shutdown_function(function () use ($outputFile) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
                Log::info("File deleted successfully after response: " . $outputFile);
            }
        });

        // Return the MP3 file
        return response()->file($outputFile, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="' . basename($outputFile) . '"'
        ]);
    }

}
