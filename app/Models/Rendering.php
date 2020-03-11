<?php

namespace aicwebtech\BibleSuperSearch\Models;

use Illuminate\Database\Eloquent\Model;

class Rendering extends Model
{
    protected $fillable = ['renderer', 'module'];

    public function deleteRenderedFile() {
        $filepath = $this->getRenderedFilePath();
        is_file($filepath) && unlink($filepath);
        $this->rendered_at = NULL;
        $this->save();
    }

    public function getRenderedFilePath() {
        $render_base_path = \aicwebtech\BibleSuperSearch\Renderers\RenderAbstract::getRenderBasePath();
        return $render_base_path . '' . $this->renderer . '/' . $this->file_name;
    }
}
