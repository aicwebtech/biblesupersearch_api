<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Upload staging and import-file resolution shared by the Bible importers
 * (App\Importers\ImporterAbstract) and the volume content importers
 * (App\ContentImporters\ContentImportAbstract).
 *
 * The using class must provide:
 *  - getImportDir(): string - the importer's own directory, with a trailing slash
 *  - $path_short - that directory, relative to the $upload_disk root
 *  - $upload_dir_short - subdirectory of it holding HTTP uploads; empty for none
 *  - $upload_disk - filesystem disk the uploads are stored on
 *  - $file - the import file name (no directory)
 *  - checkUploadedFile(UploadedFile $File): bool
 *  - $test_mode, and the Error trait
 */
trait ImportFileHandling
{
    /**
     * TRUE while an HTTP upload is being checked, before it has been stored.
     *
     * getImportFileDir() tells an upload from an operator-placed source by looking for
     * the file in the upload directory, which cannot work during the preflight step:
     * the upload is still in PHP's temp directory and $this->file is not set yet. The
     * MySword and MyBible checks have to decompress the archive before they can read
     * it, so without this the extracted database was written to the importer directory
     * -- the one bibles:prune-imports deliberately never touches -- and an import
     * abandoned at the confirmation step left it there for good.
     *
     * @var bool
     */
    protected $_accepting_upload = FALSE;

    /**
     * The importer's own directory, with a trailing slash
     *
     * @return string
     */
    abstract public function getImportDir(): string;

    /**
     * Directory HTTP uploads are written to: a subdirectory of the importer's own
     * directory, never the directory itself.
     *
     * The two kinds of file are not interchangeable, and sharing a directory is what
     * made them indistinguishable. An upload is transient -- the preflight step stores
     * it, the commit step reads it, and an import that is never completed leaves it
     * behind, which is the only thing bibles:prune-imports is meant to remove. The
     * importer directory itself is where an operator is told to put a source file (the
     * CLI import commands print the path in their help, and App\Importers\Text reads a
     * fixed file from bibles/misc), and those are kept indefinitely.
     *
     * An importer whose upload is not transient opts out with an empty
     * $upload_dir_short and stores into its own directory instead; see
     * App\Importers\BibleSuperSearch.
     *
     * @return string
     */
    public function getUploadDir(): string
    {
        return ((string) $this->upload_dir_short === '')
            ? $this->getImportDir()
            : $this->getImportDir() . $this->upload_dir_short . '/';
    }

    /**
     * The same directory as a path relative to the $upload_disk root, for storeAs().
     *
     * @return string
     */
    public function getUploadStoragePath(): string
    {
        return ((string) $this->upload_dir_short === '')
            ? $this->path_short
            : $this->path_short . '/' . $this->upload_dir_short;
    }

    /**
     * Directory the file this import is working with actually lives in.
     *
     * Uploads first: a name present in both trees is the one that was just uploaded.
     * The fallback is what keeps CLI imports working -- they read a source the operator
     * placed in the importer directory -- and it also covers an upload staged by an
     * older version of this code, before uploads moved into their own directory.
     *
     * An upload still being checked has nothing on disk to find yet, so
     * $_accepting_upload answers for it; see the note on that property.
     *
     * @return string
     */
    public function getImportFileDir(): string
    {
        $upload_dir = $this->getUploadDir();

        if($this->_accepting_upload) {
            return $upload_dir;
        }

        return ((string) $this->file !== '' && is_file($upload_dir . $this->file))
            ? $upload_dir
            : $this->getImportDir();
    }

    /**
     * Resolve a client-supplied import filename to a real path inside this
     * importer's own directories.
     *
     * The preflight step (checkImportFile) sanitizes the upload and hands the
     * safe name back to the client, but the commit step receives that name back
     * as arbitrary request input. Everything below therefore treats it as
     * untrusted: it is re-sanitized, forced to a bare basename, and the resolved
     * path must still sit beneath one of this importer's own directories.
     *
     * The directories of importFileSearchDirs() are searched, in order.
     *
     * @param  mixed  $file_name
     * @return string|null  Canonical path, or NULL when invalid or missing
     */
    public function resolveImportFile(mixed $file_name): ?string
    {
        if(!static::rawNameIsBare($file_name)) {
            return NULL;
        }

        $file_name = static::sanitizeFileName($file_name);

        if($file_name === '' || $file_name !== basename($file_name)) {
            return NULL;
        }

        foreach($this->importFileSearchDirs() as $dir) {
            $path = $this->_containedFilePath($dir, $file_name);

            if($path !== NULL) {
                return $path;
            }
        }

        return NULL;
    }

    /**
     * Directories resolveImportFile() may find a file in: the upload directory, then the
     * importer's own, for the reasons given on getImportFileDir().
     *
     * @return array<int, string>
     */
    protected function importFileSearchDirs(): array
    {
        return [$this->getUploadDir(), $this->getImportDir()];
    }

    /**
     * Canonical path to an existing $file_name directly inside $dir, or NULL.
     *
     * $file_name must already be a sanitized bare basename; this establishes the other
     * half of the guarantee, that what it resolves to really does sit in $dir.
     *
     * @param  string  $dir
     * @param  string  $file_name
     * @return string|null
     */
    protected function _containedFilePath(string $dir, string $file_name): ?string
    {
        $dir = realpath($dir);

        if($dir === FALSE) {
            return NULL;
        }

        $path = realpath($dir . DIRECTORY_SEPARATOR . $file_name);

        // Separator-aware containment: "bibles/unofficial_evil" must not pass
        // as being inside "bibles/unofficial".
        if($path === FALSE || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0) {
            return NULL;
        }

        return is_file($path) ? $path : NULL;
    }

    /**
     * Sanitized basename of the import file, for callers that build their own
     * paths from $this->file.
     *
     * @param  mixed  $file_name
     * @return string|null
     */
    public function safeImportFileName(mixed $file_name): ?string
    {
        return $this->resolveImportFile($file_name) ? static::sanitizeFileName($file_name) : NULL;
    }

    public function acceptUploadedFile(UploadedFile $File): bool
    {
        // The checks decompress into the upload directory, and storeAs() below only
        // creates it once they have already run.
        Storage::disk($this->upload_disk)->makeDirectory($this->getUploadStoragePath());

        $this->_accepting_upload = TRUE;

        try {
            if(!$this->checkUploadedFile($File)) {
                return FALSE;
            }

            if(!$this->test_mode) {        
                try {
                    $this->file = static::sanitizeFileName( $File->getClientOriginalName() );

                    // Into the upload subdirectory, never the importer directory itself:
                    // that one holds operator-placed sources, which nothing may prune.
                    $npath = $File->storeAs($this->getUploadStoragePath(), $this->file, $this->upload_disk);
                }
                catch(\Exception $e) {
                    return $this->addError('Could not save import file: ' . $e->getMessage());
                }
            }

            return TRUE;
        }
        finally {
            $this->_accepting_upload = FALSE;
        }
    }

    /**
     * Is this raw, client-supplied name a bare filename?
     *
     * Checked *before* sanitizeFileName(), which strips '/' and collapses '..' and so
     * turns "../existing.mybible" into "existing.mybible" -- a name that then passes a
     * basename() test and resolves to a real staged file. Containment was never broken by
     * that (the resolved path is still required to sit under the importer directory), but
     * a traversing name silently aliasing onto another staged file is not what the
     * surrounding code claims to do, and it lets a commit step address a file other than
     * the one that was preflighted.
     *
     * @param  mixed  $file_name
     * @return bool
     */
    public static function rawNameIsBare(mixed $file_name): bool
    {
        if(!is_string($file_name) || $file_name === '') {
            return FALSE;
        }

        // Backslash is checked explicitly: basename() does not treat it as a separator on
        // POSIX, so "..\\existing.mybible" would otherwise survive.
        foreach(['/', '\\', "\0"] as $needle) {
            if(strpos($file_name, $needle) !== FALSE) {
                return FALSE;
            }
        }

        return $file_name === basename($file_name);
    }

    /**
     * Strips everything but a safe set of filename characters, and runs of dots.
     *
     * mb_ereg_replace() returns FALSE / NULL on failure (ie invalid UTF-8); the cast turns
     * that into '', which every caller already rejects as an invalid name.
     *
     * @param  string  $file_name
     * @return string
     */
    public static function sanitizeFileName(string $file_name): string
    {
        $file_name = trim($file_name);
        $file_name = (string) mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $file_name);
        $file_name = (string) mb_ereg_replace("([\.]{2,})", '', $file_name);
        return $file_name;
    }
}
