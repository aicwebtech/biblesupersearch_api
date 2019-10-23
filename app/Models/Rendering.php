<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rendering extends Model
{
    protected $fillable = ['renderer', 'module'];

    public function deleteRenderedFile() {
        $render_base_path = \App\Renderers\RenderAbstract::getRenderBasePath();
        // need to get file path, however, file extension is non static on RenderAbstract

        // probably should just add file name as property to $this (ie to Rendering)

        $this->renderd_at = NULL;
        $this->save();
    }
}
