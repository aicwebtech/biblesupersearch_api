<?php

namespace App\Models\VolumeContent;

/**
 * Interface ContentInterface
 *
 * This interface defines the contract for content classes that manage volume content.
 * Implementing classes must provide methods for installing and uninstalling content in the database.
 * 
 * @todo Among other things, this will be used to include Bibles in the new "Volume" system.
 */
interface ContentInterface
{

    /**
     * Install the contents to the database.
     *
     * @param bool $structure_only Whether to install only the database structure and not the data (default: false).
     */
    public function install($structure_only = false): void;

    /**
     * Uninstall the contents from the database.
     */
    public function uninstall(): void;
}