<?php

namespace App\Models\Verses;

use Illuminate\Database\Eloquent\Model;
use App\Models\Bible;

// Verses models should only be instantiated from within a Bible model instance
// Abstraction allows for the potential for Bibles other than the 'standard' format
// However, actual support for non-standard formats won't be implemented any time soon.

abstract class Abs extends Model
{
    protected $Bible;
    protected $module; // Module name
	protected $hasClass = TRUE; // Indicates if this instantiation has it's own coded extension of this class.
	public $timestamps = FALSE;
	
	public function setBible(Bible &$Bible) {
		$this->Bible = $Bible;
	}
	
	public function classFileExists() {
        return $this->hasClass;
    }
	
	abstract public function install();
	abstract public function uninstall();
}
