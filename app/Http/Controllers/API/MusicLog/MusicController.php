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

        $url = escapeshellarg($request->input('url')); // Escape URL to prevent injection

        // Define Python, yt-dlp, and ffmpeg paths
        $pythonPath = base_path('python/python-3.10/python.exe');
        $ytDlpPath = base_path('python/python-3.10/Scripts/yt-dlp.exe');
        $ffmpegPath = base_path('python/python-3.10/ffmpeg/ffmpeg.exe'); 

        // Check if yt-dlp and ffmpeg exist
        if (!file_exists($ytDlpPath)) {
            Log::error("yt-dlp not found at: {$ytDlpPath}");
            return response()->json(['message' => 'yt-dlp not found'], 500);
        }

        if (!file_exists($ffmpegPath)) {
            Log::error("ffmpeg not found at: {$ffmpegPath}");
            return response()->json(['message' => 'ffmpeg not found'], 500);
        }

        // Ensure yt-dlp is executable (Linux/macOS only)
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            shell_exec("chmod +x " . escapeshellarg($ytDlpPath));
            shell_exec("chmod +x " . escapeshellarg($ffmpegPath));
        }

        // Use yt-dlp to get the video title
        $getTitleCommand = "{$pythonPath} -m yt_dlp --get-title {$url}";
        $title = trim(shell_exec($getTitleCommand));

        if (empty($title)) {
            Log::error("Failed to retrieve video title.");
            return response()->json(['message' => 'Failed to retrieve video title'], 500);
        }

        // Clean title for safe filename
        $safeTitle = preg_replace('/[\/:*?"<>|]/', '_', $title);
        $filename = "{$safeTitle}.mp3";
        $outputFile = storage_path("app/public/{$filename}");

        // Run yt-dlp command with ffmpeg support
        $ytDlpCommand = "{$pythonPath} -m yt_dlp -x --audio-format mp3 --ffmpeg-location " . escapeshellarg($ffmpegPath) . " -o " . escapeshellarg($outputFile) . " " . $url;

        Log::info("Starting yt-dlp download...", ['command' => $ytDlpCommand]);

        $output = shell_exec($ytDlpCommand . ' 2>&1');

        Log::info("yt-dlp output:", ['output' => $output]);

        if (!file_exists($outputFile)) {
            Log::error("yt-dlp failed: File was not created.", ['output' => $output]);
            return response()->json(['message' => 'Conversion failed. Check logs for details.'], 500);
        }

        Log::info("yt-dlp download completed successfully.", ['file' => $outputFile]);

        // Register a function to delete the file after response is sent
        register_shutdown_function(function () use ($outputFile) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
                Log::info("File deleted successfully after response: " . $outputFile);
            }
        });

        // Return the MP3 file as a direct response to Flutter
        return response()->file($outputFile, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="' . basename($outputFile) . '"'
        ]);
    }
}
