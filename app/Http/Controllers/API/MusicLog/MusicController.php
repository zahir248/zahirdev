<?php

namespace App\Http\Controllers\API\MusicLog;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MusicController extends Controller
{
    public function downloadMP3(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $ytDlpPath = '/usr/local/bin/yt-dlp'; // Linux-compatible yt-dlp path
        $outputDir = sys_get_temp_dir(); // Temporary directory for Railway
        $videoUrl = $request->input('url');

        \Log::info('Starting audio download', ['url' => $videoUrl]);

        // Construct the shell command
        $command = "{$ytDlpPath} -x --audio-format mp3 -o \"{$outputDir}/%(title)s.%(ext)s\" \"$videoUrl\" 2>&1";
        $output = shell_exec($command);

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

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $latestFile = $files[0]; // Get the most recent file

        return response()->download($latestFile)->deleteFileAfterSend(true);
    }
}

