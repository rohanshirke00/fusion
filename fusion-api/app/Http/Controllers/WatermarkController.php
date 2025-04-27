<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WatermarkController extends Controller
{
    // Handle the escaping of the intro text
    function escapeTextForIntroFFmpeg($text)
    {
        // FFmpeg requires colons (:) and percentage (%) to be escaped
        $escapedText = str_replace([':', '%'], ['\\:', '\\%'], $text);

        // Wrap in single quotes to ensure proper FFmpeg parsing
        return "'" . $escapedText . "'";
    }


    // Handle the escaping of the text for timestamp
    function escapeTextForTimeStampFFmpeg($text)
    {
        // Escape the special characters using addcslashes
        $escapedText = addcslashes($text, ':;,.!?@#$%^&*()[]{}<>|~`"\'\\'); // Escape special characters
        // Double escape backslashes so that they work in FFmpeg (e.g., for time format)
        $escapedText = str_replace('\\', '\\\\', $escapedText);
        // Wrap in single quotes to ensure proper string handling in FFmpeg
        return "'" . $escapedText . "'";
    }

    private function createTextVideo($outputPath, $text, $duration = 3, $resolution = '1280x720', $fontSize = 48, $fontColor = 'white')
    {
        $FONT_FILE = 'C\\\\:/Windows/Fonts/Arial.ttf';

        // Ensure the outputPath includes an extension
        if (!preg_match('/\.(mp4|mkv|avi|mov)$/i', $outputPath)) {
            return "Error: Output path must include a valid video file extension like .mp4, .mkv, .avi, or .mov.";
        }

        // Escape the text properly
        $escapedText = $this->escapeTextForIntroFFmpeg($text);

        // Construct the FFmpeg command to create a blank video and add text to it
        $command = sprintf(
            'ffmpeg -f lavfi -i color=c=black:s=%s:d=%d -f lavfi -t %d -i anullsrc=channel_layout=stereo:sample_rate=44100 ' .
            '-vf "drawtext=text=%s:fontfile=%s:fontcolor=%s:fontsize=%d:x=(w-text_w)/2:y=(h-text_h)/2" ' .
            '-c:v libx264 -r 30 -c:a aac -ar 44100 -b:a 192k -t %d -y %s',
            $resolution,                // Resolution of the video (e.g., 1280x720)
            $duration,                  // Duration in seconds
            $duration,                  // Duration of the silent audio
            $escapedText,               // Properly escaped text
            $FONT_FILE,                 // Path to font file
            $fontColor,                 // Font color
            $fontSize,                  // Font size
            $duration,                  // Duration again for safety
            escapeshellarg($outputPath) // Path to output video
        );

        // Log the command for debugging
        info($command);

        // Execute the command
        exec($command, $output, $status);

        // Throw exception if preprocessing fails
        if ($status !== 0) {
            throw new \Exception("Error creating text video " . implode("\n", $output));
        }
    }

    private function preprocessVideo($timeStampText, $videoPath, $outputPath)
    {

        $FONT_FILE = 'C\\\\:/Windows/Fonts/Arial.ttf';

        // $videoDuration = $this->getVideoDuration($videoPath);

        // Check if the input video has an audio stream
        $checkAudioCmd = sprintf(
            'ffprobe -i %s -show_streams -select_streams a -loglevel error',
            escapeshellarg($videoPath)
        );
        exec($checkAudioCmd, $audioOutput, $audioStatus);

        // Escape the text properly
        $escapedTimeText = $this->escapeTextForTimeStampFFmpeg($timeStampText);

        // If no audio, add silent audio and align streams with timestamp

        // If no audio, add silent audio and align streams with timestamp
        if ($audioStatus !== 0 || empty($audioOutput)) {
            // Process without audio, add timestamp
            $cmd = sprintf(
                'ffmpeg -i %s -vf "drawtext=text=\'%s\':fontfile=%s:fontsize=24:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2" ' .
                '-c:v libx264 -preset veryfast -crf 23 -r 30 -y %s',
                escapeshellarg($videoPath),
                $escapedTimeText,                // Properly escaped timestamp
                escapeshellarg($FONT_FILE),      // Path to font file
                escapeshellarg($outputPath)
            );
        } else {
            // Process normally and add timestamp with audio
            $cmd = sprintf(
                'ffmpeg -i %s -vf "drawtext=text=\'%s\':fontfile=%s:fontsize=24:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2" ' .
                '-c:v libx264 -preset veryfast -crf 23 -r 30 -c:a aac -ar 44100 -b:a 192k -y %s',
                escapeshellarg($videoPath),
                $escapedTimeText,                // Properly escaped timestamp
                escapeshellarg($FONT_FILE),      // Path to font file
                escapeshellarg($outputPath)
            );
        }

        // Execute FFmpeg command
        exec($cmd, $output, $status);

        // Throw exception if preprocessing fails
        if ($status !== 0) {
            throw new \Exception("Failed to preprocess video and add timestamp: " . implode("\n", $output));
        }
    }

    private function addTimeToTimestamp($timestamp, $secondsToAdd)
    {
        $date = \DateTime::createFromFormat('Y-m-d h:i:s A', $timestamp);

        if (!$date) {
            throw new \Exception("Invalid timestamp format: " . $timestamp);
        }

        $date->modify("+{$secondsToAdd} seconds");

        return $date->format('Y-m-d h:i:s A');  // Maintain the correct format
    }



    public function mergeVideos(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'videos' => 'required|array',
            'videos.*' => 'file|mimetypes:video/mp4,video/avi,video/mov|max:30720',
            'intro' => 'required|string',
        ]);

        try {
            // Create a unique directory for temporary files
            $uniqueDir = Str::random(10) . '-' . Carbon::now()->timestamp;
            Storage::disk('temporary')->makeDirectory($uniqueDir);

            $text = $validated['intro'];
            $duration = 3; // Duration of the video in seconds
            $resolution = '1920x1080'; // Full HD resolution
            $fontSize = 64; // Font size
            $fontColor = 'white'; // Font color

            $this->createTextVideo(Storage::disk('temporary')->path($uniqueDir . '/text_video' . '.mp4'), $text, $duration, $resolution, $fontSize, $fontColor);

            $videoPaths = [];
            $counter = 1; // Start counter at 1

            // Get the current timestamp
            $currentTimestamp = date('Y-m-d h:i:s A');

            foreach ($validated['videos'] as $video) {
                $originalPath = $video->storeAs($uniqueDir, 'original_' . $counter . '.' . $video->getClientOriginalExtension(), 'temporary');
                $preprocessedPath = $uniqueDir . '/processed_' . $counter . '.mp4';

                // ✅ Get video duration first
                $currentVideoLength = $this->getVideoDuration(Storage::disk('temporary')->path($originalPath));

                // ✅ Log duration
                info("Video $counter length: " . $currentVideoLength . " seconds");

                // ✅ Update the timestamp BEFORE passing it to preprocessVideo()
                $currentTimestamp = $this->addTimeToTimestamp($currentTimestamp, (int) $currentVideoLength);

                info("Updated Timestamp for Video $counter: " . $currentTimestamp);
                
                $this->preprocessVideo(
                    $currentTimestamp,
                    Storage::disk('temporary')->path($originalPath),
                    Storage::disk('temporary')->path($preprocessedPath)
                );

                $videoPaths[] = $preprocessedPath;
                $counter++;
            }


            // Create concat_list.txt
            $listPath = Storage::disk('temporary')->path($uniqueDir . '/concat_list.txt');
            $this->createConcatListFile($videoPaths, $listPath);

            // Define output video path
            $outputVideo = $uniqueDir . '/output.mp4';

            // Merge videos
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
        if (!$file) {
            throw new \Exception("Failed to create concat_list.txt file");
        }
        fwrite($file, "file '" . "text_video.mp4" . "'\n");
        foreach ($videoPaths as $path) {
            fwrite($file, "file '" . basename($path) . "'\n");
        }
        fclose($file);
    }

    private function processVideos(string $listPath, string $outputPath)
    {

        // FFmpeg command to concatenate the videos
        $cmd = sprintf(
            'ffmpeg -f concat -safe 0 -i %s -c:v libx264 -preset veryfast -crf 23 -r 30 -c:a aac -ar 44100 -b:a 192k -shortest -y %s',
            escapeshellarg($listPath),
            escapeshellarg(Storage::disk('temporary')->path($outputPath))
        );

        // Execute FFmpeg command
        exec($cmd, $output, $status);

        // Throw exception if FFmpeg fails
        if ($status !== 0) {
            throw new \Exception("Failed to concatenate and add watermark to videos: " . implode("\n", $output));
        }
    }

    private function getVideoDuration($videoPath)
    {
        $cmd = sprintf('ffprobe -i %s -show_entries format=duration -v quiet -of csv="p=0"', escapeshellarg($videoPath));
        exec($cmd, $output, $status);

        if ($status !== 0 || empty($output)) {
            throw new \Exception("Failed to get video duration for: $videoPath");
        }

        return (float) trim($output[0]);  // Ensure it's a valid float number
    }


}
