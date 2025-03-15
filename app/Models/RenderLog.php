<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RenderLog extends Model
{
    use SoftDeletes;
    
    protected $table = 'render_log';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public function delete()
    {
        if($this->module == 'ALL') {
            $file_path = \App\Renderers\RenderAbstract::getRenderBasePath() . $this->file_name;

            if(is_file($file_path)){
                unlink($file_path);
            }
        }
        
        $this->deleted_at = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Delete all logs for a given IP address
     *
     * @param string $ip
     * @param int $threshold in seconds
     * @param bool $dry_run
     * @return bool|array
     */
    public static function deleteByIp($ip, $threshold = 0, $dry_run = false)
    {
        $Query = self::where('ip_address', $ip);

        if($threshold > 0) {
            $Query->where('created_at', '<=', date('Y-m-d H:i:s', strtotime('-' . $threshold . ' seconds')));
        }

        $logs = $Query->get();
        $deleted = [];
        
        foreach($logs as $log) {
            if($dry_run) {
                if($log->module == 'ALL') {
                    // If module != ALL, the file won't be deleted
                    $deleted[] = $log->filename;
                }
            } else {
                $log->delete();
            }
        }

        return $dry_run ? $deleted : true;
    }
}
