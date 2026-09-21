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
            // $useErrors is interpolated into the errors.audio.merge_failed API response
            // (AudioManager::getAudio), so nothing here may name a server path. The detail
            // goes to the log, where an operator can still find it.
            \Log::error('ffmpeg merge: output file is not writable: ' . $output_file);
            self::$useErrors[] = 'The audio file could not be written.';

            return false;
        }

        $input_list_file = tempnam(sys_get_temp_dir(), 'ffmpeg_input_');

        if(!static::writeConcatList($input_list_file, $input_files)) {
            @unlink($input_list_file);

            return false;
        }

        $command = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($input_list_file) . " -c copy " . escapeshellarg($output_file) . " -y 2>&1";
        
        exec($command, $output, $return_var);

        @unlink($input_list_file);

        if($return_var !== 0) {
            // ffmpeg echoes both the input-list and output paths on failure, so its output
            // is logged rather than returned to the caller.
            \Log::error("ffmpeg merge failed: " . implode("\n", $output));
            self::$useErrors[] = 'The audio files could not be merged.';
        }

        // var_dump($return_var); die();

        return $return_var === 0;
    }

    /**
     * Write the concat demuxer's input list.
     *
     * Every write is checked. A truncated list is the dangerous case: ffmpeg happily
     * concatenates whatever entries it can read and exits 0, so a dropped line becomes an
     * audio file that is silently missing verses rather than a reported failure. fwrite()
     * can return a short count without returning FALSE, so the byte count is compared.
     *
     * @param  string  $path
     * @param  array   $input_files
     * @return bool
     */
    protected static function writeConcatList($path, $input_files)
    {
        // Suppressed because the FALSE return is handled and reported below; compare
        // InstallManager::installLockIsStale(). Without it an unwritable temp directory
        // raises a PHP warning on top of the error already recorded.
        $handle = @fopen($path, 'w');

        if(!$handle) {
            \Log::error('ffmpeg merge: could not open the input list for writing: ' . $path);
            self::$useErrors[] = 'The audio file could not be assembled.';

            return false;
        }

        foreach ($input_files as $file) {
            $line = "file '" . str_replace("'", "'\\''", $file) . "'\n";
            $written = fwrite($handle, $line);

            if($written !== strlen($line)) {
                self::$useErrors[] = 'Could not write the ffmpeg input list (disk full?)';
                fclose($handle);

                return false;
            }
        }

        // Buffered lines are flushed here, so a full disk can surface only at close.
        if(!fclose($handle)) {
            self::$useErrors[] = 'Could not finish writing the ffmpeg input list';

            return false;
        }

        return true;
    }
}