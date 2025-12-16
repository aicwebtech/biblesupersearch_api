<?php

namespace App\TextToSpeech;

class Ffmpeg
{
    protected static $canIUse = null;
    public static $useErrors = [];
    
    public static function canUse()
    {
        if(!isset(self::$canIUse)) {
            // check if we can use eval first
            if(!function_exists('exec')) {
                self::$useErrors[] = "PHP exec() function is disabled.";
                self::$canIUse = false;
                return self::$canIUse;
            }

            $output = null;
            $return_var = null;
            exec('ffmpeg -version 2>&1', $output, $return_var);
            
            if ($return_var === 0) {
                self::$canIUse = true;
            } else {
                self::$useErrors[] = "ffmpeg is not installed or not found in system PATH.";
                self::$canIUse = false;
            }
        }
        
        return self::$canIUse;
    }

    /**
     * Quickly merge multiple audio files into one using ffmpeg concat demuxer
     * 
     * @param array $input_files Array of input file paths
     * @param string $output_file Output file path
     * @return bool True on success, false on failure
     */
    public static function quickMerge($input_files, $output_file)
    {
        if(!self::canUse()) {
            return false;
        }

        if(!is_writable($output_file)) {
            self::$useErrors[] = ' PHP cannot write file ' . $output_file;
            return false;
        }

        $input_list_file = tempnam(sys_get_temp_dir(), 'ffmpeg_input_');
        $handle = fopen($input_list_file, 'w');
        
        foreach ($input_files as $file) {
            fwrite($handle, "file '" . str_replace("'", "'\\''", $file) . "'\n");
        }

        fclose($handle);

        $command = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($input_list_file) . " -c copy " . escapeshellarg($output_file) . " -y 2>&1";
        
        exec($command, $output, $return_var);

        if($return_var !== 0) {
            self::$useErrors[] = "ffmpeg merge failed: " . implode("\n", $output);
        }

        return $return_var === 0;
    }
}