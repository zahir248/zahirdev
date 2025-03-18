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

        $ytDlpPath = base_path('bin/yt-dlp.exe'); // Get bin path dynamically
        $outputDir = storage_path('app/public/downloads'); // Store in storage directory
        $videoUrl = $request->input('url');

        // Ensure the output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
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

////////////////////////  linux based ////////////////////////////////////////

//     public function downloadMP3(Request $request)
// {
//     $debugInfo = []; // Array to store debug data
//     $debugInfo['request_url'] = $request->input('url');

//     // Validate the request
//     $request->validate([
//         'url' => 'required|url'
//     ]);

//     $outputDir = storage_path('app/public/downloads');
//     array_map('unlink', glob("$outputDir/*.mp3"));
//     $videoUrl = $request->input('url');
    
//     // Store tools in the storage directory
//     $toolsDir = storage_path('app/tools');
//     $ytDlpPath = $toolsDir . '/yt-dlp';
//     $ffmpegPath = $toolsDir . '/ffmpeg';
    
//     // Create a cookies file in the storage directory where we have write access
//     $cookiesPath = storage_path('app/youtube_cookies.txt');

//     $debugInfo['output_directory'] = $outputDir;
//     $debugInfo['yt_dlp_path'] = $ytDlpPath;
//     $debugInfo['ffmpeg_path'] = $ffmpegPath;
//     $debugInfo['cookies_path'] = $cookiesPath;

//     // Ensure directories exist
//     foreach ([$outputDir, $toolsDir, dirname($cookiesPath)] as $dir) {
//         if (!file_exists($dir)) {
//             mkdir($dir, 0755, true);
//             $debugInfo[basename($dir).'_directory_created'] = true;
//         }
//     }

//     // Create cookies file with the YouTube authentication cookies
//     $cookiesContent = <<<EOT
// # Netscape HTTP Cookie File
// # http://curl.haxx.se/rfc/cookie_spec.html
// # This is a generated file!  Do not edit.

// .youtube.com	TRUE	/	FALSE	1774617761	HSID	A-M4AuV2cQhVR0lW8
// .youtube.com	TRUE	/	TRUE	1774617761	SSID	AyLl5ktEwnX6FyG5Q
// .youtube.com	TRUE	/	FALSE	1774617761	APISID	T8RWldUo02n38_mA/AQAuVHPrVWZvGlUsl
// .youtube.com	TRUE	/	TRUE	1774617761	SAPISID	Y3OMSpwZfALHR311/A62s3KmmFBanIXrOE
// .youtube.com	TRUE	/	TRUE	1774617761	__Secure-1PAPISID	Y3OMSpwZfALHR311/A62s3KmmFBanIXrOE
// .youtube.com	TRUE	/	TRUE	1774617761	__Secure-3PAPISID	Y3OMSpwZfALHR311/A62s3KmmFBanIXrOE
// .youtube.com	TRUE	/	TRUE	1766836995	LOGIN_INFO	AFmmF2swRgIhAKSTOhoLj9qNtEmVFGjb1G6lzx-Voez_NPqbgtmA58z0AiEA2ljt5iDMnJOZfGNNQEBNEWFoxin9fxgPLEWfJcWjK_0:QUQ3MjNmeDcyYzBSNzY2YlBIN3lwSWJFdFhpaUVxZjllVzZwMzliUE5fUVdKb2NNN29QX2gwcW52QW1VdHZlVUVnUjRwTWlWam5NVnpCb0VCNHJleXB4WnhxV1Y4QUVwS2tDejJ0d2hGOEVvRVlKdnlFOVdQM3F0NmZGSHBqZEd4RndXQnN0cHh2bFFPdDJTaGNPNzEyUy0ta3lYRGo0WGdR
// .youtube.com	TRUE	/	TRUE	1776843373	PREF	f6=40000000&tz=Asia.Kuala_Lumpur&f7=100
// .youtube.com	TRUE	/	FALSE	1774617761	SID	g.a000twjhWlDI1lVRDg_x7L5rLU_ioihsw4zrpJ9SFUeLONrqbtjQOXwDgi6r47CSyK0dmNZp7AACgYKAdISARASFQHGX2Mi-wY7DxJSMnApugn_G9XbdRoVAUF8yKpuH3PtabUB_ExCtgTdLlDE0076
// .youtube.com	TRUE	/	TRUE	1774617761	__Secure-1PSID	g.a000twjhWlDI1lVRDg_x7L5rLU_ioihsw4zrpJ9SFUeLONrqbtjQvB_vPcwh8kU_-Hz7C-kFewACgYKAfgSARASFQHGX2MiVrS230Jw77IpmYlCWLYmsBoVAUF8yKr2lk0bHoYjusNgFdyWPkdo0076
// .youtube.com	TRUE	/	TRUE	1774617761	__Secure-3PSID	g.a000twjhWlDI1lVRDg_x7L5rLU_ioihsw4zrpJ9SFUeLONrqbtjQLD1KxE9dlcElN8_d0za_IAACgYKAX4SARASFQHGX2MiHel5dBnTV6IGefu25M2U3hoVAUF8yKqGTNKcRfp2aifGJjyc4SrS0076
// .youtube.com	TRUE	/	FALSE	0	wide	1
// .youtube.com	TRUE	/	TRUE	1773819112	__Secure-1PSIDTS	sidts-CjEB7pHptdAhxwA1elTMweMUxzA5bTGt9ALEMozGo-plNqrxmctvHSbf2mX7lqERV35WEAA
// .youtube.com	TRUE	/	TRUE	1773819112	__Secure-3PSIDTS	sidts-CjEB7pHptdAhxwA1elTMweMUxzA5bTGt9ALEMozGo-plNqrxmctvHSbf2mX7lqERV35WEAA
// .youtube.com	TRUE	/	FALSE	1742283378	ST-tladcw	session_logininfo=AFmmF2swRgIhAKSTOhoLj9qNtEmVFGjb1G6lzx-Voez_NPqbgtmA58z0AiEA2ljt5iDMnJOZfGNNQEBNEWFoxin9fxgPLEWfJcWjK_0%3AQUQ3MjNmeDcyYzBSNzY2YlBIN3lwSWJFdFhpaUVxZjllVzZwMzliUE5fUVdKb2NNN29QX2gwcW52QW1VdHZlVUVnUjRwTWlWam5NVnpCb0VCNHJleXB4WnhxV1Y4QUVwS2tDejJ0d2hGOEVvRVlKdnlFOVdQM3F0NmZGSHBqZEd4RndXQnN0cHh2bFFPdDJTaGNPNzEyUy0ta3lYRGo0WGdR
// .youtube.com	TRUE	/	FALSE	1742283379	ST-xuwub9	session_logininfo=AFmmF2swRgIhAKSTOhoLj9qNtEmVFGjb1G6lzx-Voez_NPqbgtmA58z0AiEA2ljt5iDMnJOZfGNNQEBNEWFoxin9fxgPLEWfJcWjK_0%3AQUQ3MjNmeDcyYzBSNzY2YlBIN3lwSWJFdFhpaUVxZjllVzZwMzliUE5fUVdKb2NNN29QX2gwcW52QW1VdHZlVUVnUjRwTWlWam5NVnpCb0VCNHJleXB4WnhxV1Y4QUVwS2tDejJ0d2hGOEVvRVlKdnlFOVdQM3F0NmZGSHBqZEd4RndXQnN0cHh2bFFPdDJTaGNPNzEyUy0ta3lYRGo0WGdR
// .youtube.com	TRUE	/	FALSE	1773819376	SIDCC	AKEyXzXv9CltQjfxKzd5pZ40voXj-3JW2jqhx1fqXZUBcNCtQMuI2vmEWwbyXH_xHk9TEsqH92k
// .youtube.com	TRUE	/	TRUE	1773819376	__Secure-1PSIDCC	AKEyXzXAfo2GWW1L3EMIs-mQkUmP5g63YlWrl5I8rmEnXcpmjRB8SYfiTdXBG_uc53k_4bM8nDM
// .youtube.com	TRUE	/	TRUE	1773819376	__Secure-3PSIDCC	AKEyXzWW392wvx9q4csVw6bGOKajHnLSKvWLxmc-k9Erw0oVSl_J9Mtx4BJnqB-1gLgRhTA6g0I
// EOT;

//     file_put_contents($cookiesPath, $cookiesContent);
//     $debugInfo['cookies_file_created'] = true;

//     // Install yt-dlp standalone binary if needed
//     if (!file_exists($ytDlpPath) || !is_executable($ytDlpPath)) {
//         $debugInfo['yt_dlp_status'] = 'Not found or not executable - attempting to install standalone binary';
        
//         // Get architecture
//         $archProcess = Process::fromShellCommandline("uname -m");
//         $archProcess->run();
//         $arch = trim($archProcess->getOutput());
        
//         // Determine the appropriate URL based on architecture
//         $ytDlpUrl = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp";
//         if ($arch == "x86_64") {
//             $ytDlpUrl = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux";
//         } elseif ($arch == "aarch64") {
//             $ytDlpUrl = "https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux_aarch64";
//         }
        
//         $debugInfo['yt_dlp_arch'] = $arch;
//         $debugInfo['yt_dlp_url'] = $ytDlpUrl;
        
//         $installProcess = Process::fromShellCommandline(
//             "curl -L $ytDlpUrl -o \"$ytDlpPath\" && chmod +x \"$ytDlpPath\""
//         );
//         $installProcess->setTimeout(60);
//         $installProcess->run();
        
//         $debugInfo['yt_dlp_install_stdout'] = trim($installProcess->getOutput());
//         $debugInfo['yt_dlp_install_stderr'] = trim($installProcess->getErrorOutput());
        
//         if (!$installProcess->isSuccessful() || !file_exists($ytDlpPath)) {
//             return response()->json([
//                 'success' => false,
//                 'error' => 'Failed to install yt-dlp standalone binary',
//                 'debug' => $debugInfo
//             ], 500);
//         }
        
//         $debugInfo['yt_dlp_installed'] = true;
//     } else {
//         $debugInfo['yt_dlp_status'] = 'Already installed';
//     }

//     // Install FFmpeg if needed (same as before)
//     if (!file_exists($ffmpegPath) || !is_executable($ffmpegPath)) {
//         $debugInfo['ffmpeg_status'] = 'Not found or not executable - attempting to install';
        
//         // Download FFmpeg (static build for Linux)
//         $ffmpegTempDir = storage_path('app/temp');
//         if (!file_exists($ffmpegTempDir)) {
//             mkdir($ffmpegTempDir, 0755, true);
//         }
        
//         $ffmpegArchive = $ffmpegTempDir . '/ffmpeg.tar.xz';
        
//         // Download FFmpeg static build
//         $downloadProcess = Process::fromShellCommandline(
//             "curl -L https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o \"$ffmpegArchive\""
//         );
//         $downloadProcess->setTimeout(120); // Allow time for download
//         $downloadProcess->run();
        
//         $debugInfo['ffmpeg_download_stdout'] = trim($downloadProcess->getOutput());
//         $debugInfo['ffmpeg_download_stderr'] = trim($downloadProcess->getErrorOutput());
        
//         if (!$downloadProcess->isSuccessful() || !file_exists($ffmpegArchive)) {
//             return response()->json([
//                 'success' => false,
//                 'error' => 'Failed to download FFmpeg',
//                 'debug' => $debugInfo
//             ], 500);
//         }
        
//         // Extract FFmpeg
//         $extractProcess = Process::fromShellCommandline(
//             "tar -xf \"$ffmpegArchive\" -C \"$ffmpegTempDir\""
//         );
//         $extractProcess->setTimeout(60);
//         $extractProcess->run();
        
//         $debugInfo['ffmpeg_extract_stdout'] = trim($extractProcess->getOutput());
//         $debugInfo['ffmpeg_extract_stderr'] = trim($extractProcess->getErrorOutput());
        
//         if (!$extractProcess->isSuccessful()) {
//             return response()->json([
//                 'success' => false,
//                 'error' => 'Failed to extract FFmpeg',
//                 'debug' => $debugInfo
//             ], 500);
//         }
        
//         // Find the extracted ffmpeg binary
//         $findFfmpegProcess = Process::fromShellCommandline(
//             "find \"$ffmpegTempDir\" -name ffmpeg -type f"
//         );
//         $findFfmpegProcess->run();
//         $foundFfmpeg = trim($findFfmpegProcess->getOutput());
        
//         if (empty($foundFfmpeg)) {
//             return response()->json([
//                 'success' => false,
//                 'error' => 'Failed to locate FFmpeg binary',
//                 'debug' => $debugInfo
//             ], 500);
//         }
        
//         // Copy FFmpeg to tools directory
//         $copyProcess = Process::fromShellCommandline(
//             "cp \"$foundFfmpeg\" \"$ffmpegPath\" && chmod +x \"$ffmpegPath\""
//         );
//         $copyProcess->run();
        
//         if (!$copyProcess->isSuccessful() || !file_exists($ffmpegPath)) {
//             return response()->json([
//                 'success' => false,
//                 'error' => 'Failed to install FFmpeg',
//                 'debug' => $debugInfo
//             ], 500);
//         }
        
//         $debugInfo['ffmpeg_installed'] = true;
        
//         // Clean up
//         $cleanupProcess = Process::fromShellCommandline(
//             "rm -rf \"$ffmpegTempDir\""
//         );
//         $cleanupProcess->run();
//     } else {
//         $debugInfo['ffmpeg_status'] = 'Already installed';
//     }

//     // Test if yt-dlp runs successfully
//     $testProcess = Process::fromShellCommandline("\"$ytDlpPath\" --version");
//     $testProcess->run();
//     $debugInfo['yt_dlp_test_output'] = trim($testProcess->getOutput());
//     $debugInfo['yt_dlp_test_error'] = trim($testProcess->getErrorOutput());
    
//     if (!$testProcess->isSuccessful()) {
//         return response()->json([
//             'success' => false,
//             'error' => 'yt-dlp binary test failed',
//             'debug' => $debugInfo
//         ], 500);
//     }

//     $cookiesPath = storage_path('app/cookies.txt');

//     // Construct the yt-dlp command with path to FFmpeg and custom cookies file
//     $command = "\"$ytDlpPath\" --no-cache-dir -x --audio-format mp3 --ffmpeg-location \"$ffmpegPath\" --cookies \"$cookiesPath\" -o \"$outputDir/%(title)s.%(ext)s\" \"$videoUrl\"";

//     $debugInfo['command'] = $command;

//     // Execute command
//     $process = Process::fromShellCommandline($command);
//     $process->setTimeout(300); // Set a longer timeout for large files
//     $process->run();

//     // Capture command output
//     $debugInfo['yt_dlp_stdout'] = trim($process->getOutput());
//     $debugInfo['yt_dlp_stderr'] = trim($process->getErrorOutput());

//     if (!$process->isSuccessful()) {
//         return response()->json([
//             'success' => false,
//             'error' => 'Download failed',
//             'debug' => $debugInfo
//         ], 500);
//     }

//     // Find the latest MP3 file
//     $files = glob($outputDir . '/*.mp3');
//     $debugInfo['mp3_files_found'] = $files ? count($files) : 0;

//     if (!$files) {
//         return response()->json([
//             'success' => false,
//             'error' => 'No MP3 file found',
//             'debug' => $debugInfo
//         ], 500);
//     }

//     usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
//     $latestFile = $files[0];

//     $debugInfo['latest_file'] = $latestFile;

//     // Return the MP3 file as a binary response
//     return response()->file($latestFile, [
//         'Content-Type' => 'audio/mpeg',
//         'Content-Disposition' => 'attachment; filename="' . basename($latestFile) . '"',
//         'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
//         'Pragma' => 'no-cache',
//         'Expires' => '0',
//     ]);
//     }

}
