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
    
    // Store tools in the storage directory
    $toolsDir = storage_path('app/tools');
    $ytDlpPath = $toolsDir . '/yt-dlp';
    $ffmpegPath = $toolsDir . '/ffmpeg';

    $debugInfo['output_directory'] = $outputDir;
    $debugInfo['yt_dlp_path'] = $ytDlpPath;
    $debugInfo['ffmpeg_path'] = $ffmpegPath;

    // Ensure directories exist
    foreach ([$outputDir, $toolsDir] as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
            $debugInfo[basename($dir).'_directory_created'] = true;
        }
    }

    // Install yt-dlp if needed
    if (!file_exists($ytDlpPath) || !is_executable($ytDlpPath)) {
        $debugInfo['yt_dlp_status'] = 'Not found or not executable - attempting to install';
        
        $installProcess = Process::fromShellCommandline(
            "curl -L https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -o \"$ytDlpPath\" && chmod +x \"$ytDlpPath\""
        );
        $installProcess->setTimeout(60);
        $installProcess->run();
        
        $debugInfo['yt_dlp_install_stdout'] = trim($installProcess->getOutput());
        $debugInfo['yt_dlp_install_stderr'] = trim($installProcess->getErrorOutput());
        
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

    // Install FFmpeg if needed
    if (!file_exists($ffmpegPath) || !is_executable($ffmpegPath)) {
        $debugInfo['ffmpeg_status'] = 'Not found or not executable - attempting to install';
        
        // Download FFmpeg (static build for Linux)
        $ffmpegTempDir = storage_path('app/temp');
        if (!file_exists($ffmpegTempDir)) {
            mkdir($ffmpegTempDir, 0755, true);
        }
        
        $ffmpegArchive = $ffmpegTempDir . '/ffmpeg.tar.xz';
        
        // Download FFmpeg static build
        $downloadProcess = Process::fromShellCommandline(
            "curl -L https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o \"$ffmpegArchive\""
        );
        $downloadProcess->setTimeout(120); // Allow time for download
        $downloadProcess->run();
        
        $debugInfo['ffmpeg_download_stdout'] = trim($downloadProcess->getOutput());
        $debugInfo['ffmpeg_download_stderr'] = trim($downloadProcess->getErrorOutput());
        
        if (!$downloadProcess->isSuccessful() || !file_exists($ffmpegArchive)) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to download FFmpeg',
                'debug' => $debugInfo
            ], 500);
        }
        
        // Extract FFmpeg
        $extractProcess = Process::fromShellCommandline(
            "tar -xf \"$ffmpegArchive\" -C \"$ffmpegTempDir\""
        );
        $extractProcess->setTimeout(60);
        $extractProcess->run();
        
        $debugInfo['ffmpeg_extract_stdout'] = trim($extractProcess->getOutput());
        $debugInfo['ffmpeg_extract_stderr'] = trim($extractProcess->getErrorOutput());
        
        if (!$extractProcess->isSuccessful()) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to extract FFmpeg',
                'debug' => $debugInfo
            ], 500);
        }
        
        // Find the extracted ffmpeg binary
        $findFfmpegProcess = Process::fromShellCommandline(
            "find \"$ffmpegTempDir\" -name ffmpeg -type f"
        );
        $findFfmpegProcess->run();
        $foundFfmpeg = trim($findFfmpegProcess->getOutput());
        
        if (empty($foundFfmpeg)) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to locate FFmpeg binary',
                'debug' => $debugInfo
            ], 500);
        }
        
        // Copy FFmpeg to tools directory
        $copyProcess = Process::fromShellCommandline(
            "cp \"$foundFfmpeg\" \"$ffmpegPath\" && chmod +x \"$ffmpegPath\""
        );
        $copyProcess->run();
        
        if (!$copyProcess->isSuccessful() || !file_exists($ffmpegPath)) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to install FFmpeg',
                'debug' => $debugInfo
            ], 500);
        }
        
        $debugInfo['ffmpeg_installed'] = true;
        
        // Clean up
        $cleanupProcess = Process::fromShellCommandline(
            "rm -rf \"$ffmpegTempDir\""
        );
        $cleanupProcess->run();
    } else {
        $debugInfo['ffmpeg_status'] = 'Already installed';
    }

    // Construct the yt-dlp command with path to FFmpeg
    $command = "\"$ytDlpPath\" -x --audio-format mp3 --ffmpeg-location \"$ffmpegPath\" -o \"$outputDir/%(title)s.%(ext)s\" \"$videoUrl\"";
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
