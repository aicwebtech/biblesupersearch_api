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
     * @return bool Whether the install succeeded
     */
    public function install($structure_only = false): bool;

    /**
     * Uninstall the contents from the database.
     *
     * @return bool Whether the uninstall succeeded
     */
    public function uninstall(): bool;
}