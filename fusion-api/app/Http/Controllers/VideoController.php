<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function mergeVideos(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'videos' => 'required|array',
            'videos.*' => 'file|mimetypes:video/mp4,video/avi,video/mov|max:30720',
        ]);

        try {
            // Create a unique directory for temporary files
            $uniqueDir = Str::random(10) . '-' . Carbon::now()->timestamp;
            Storage::disk('temporary')->makeDirectory($uniqueDir);

            // Store videos in the temporary directory
            $videoPaths = [];
            $counter = 1; // Start counter at 1
            foreach ($validated['videos'] as $video) {
                $fileName = $counter . '_video_' . Str::random(6) . '.' . $video->getClientOriginalExtension(); // Sequential names
                $videoPaths[] = $video->storeAs($uniqueDir, $fileName, 'temporary');
                $counter++; // Increment counter
            }
            
            // Create concat_list.txt with video file references
            $listPath = Storage::disk('temporary')->path($uniqueDir . '/concat_list.txt');
            $this->createConcatListFile($videoPaths, $listPath);

            // Define output video path
            $outputVideo = $uniqueDir . '/output.mp4';

            // Process videos
            $this->processVideos($listPath, $outputVideo);

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Videos processed successfully.',
                'data' => [
                    'download_url' => $outputVideo,
                ],
            ]);
        } catch (\Exception $e) {
            // Log error and return failure response
            \Log::error('Video processing failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Video processing failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createConcatListFile(array $videoPaths, string $listPath)
    {
        // Create a list file with relative paths
        $file = fopen($listPath, 'w');
        if(!$file){
            throw new \Exception("Failed to create concat_list.txt file");
        }
        foreach ($videoPaths as $path) {
            fwrite($file, "file '" . basename($path) . "'\n");
        }
        fclose($file);
    }

    private function processVideos(string $listPath, string $outputPath)
    {
        // Prepare the FFmpeg command
    
        $cmd = sprintf(
            'ffmpeg -f concat -safe 0 -i %s -c:v libx264 -preset veryfast -crf 23 -c:a aac -b:a 192k %s',
            escapeshellarg($listPath),
            escapeshellarg(Storage::disk('temporary')->path($outputPath))
        );        
        

        // Execute the FFmpeg command
        exec($cmd, $output, $status);

        // Throw exception if FFmpeg fails
        if ($status !== 0) {
            throw new \Exception("FFmpeg failed with status code $status: " . implode("\n", $output));
        }
    }
}
