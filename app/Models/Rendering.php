<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rendering extends Model
{
    protected $fillable = ['renderer', 'module'];

    public function deleteRenderedFile() {
        $filepath = $this->getRenderedFilePath();
        is_file($filepath) && unlink($filepath);
        $this->rendered_at   = NULL;
        $this->downloaded_at = NULL;
        $this->save();
    }

    public function getRenderedFilePath() {
        $render_base_path = \App\Renderers\RenderAbstract::getRenderBasePath();
        return $render_base_path . '' . $this->renderer . '/' . $this->file_name;
    }

    public function isPendingDownload() {
        if(!$this->rendered_at || $this->downloaded_at) {
            return FALSE;
        }

        $days      = 2; // Make this a config?
        $comp_date = strtotime('-' . $days . ' days');
        $ren_ts    = strtotime($this->rendered_at);

        return ($ren_ts > $comp_date) ? TRUE : FALSE;
    }
}
