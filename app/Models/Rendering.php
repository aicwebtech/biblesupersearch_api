<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rendering extends Model
{
    protected $fillable = ['renderer', 'module'];

    public function deleteRenderedFile() {
        $filepath = $this->getRenderedFilePath();
        unlink($filepath);
        $this->renderd_at = NULL;
        $this->save();
    }

    public function getRenderedFilePath() {
        $render_base_path = \App\Renderers\RenderAbstract::getRenderBasePath();
        return $render_base_path . '' . $this->renderer . '/' . $this->file_name;
    }
}
