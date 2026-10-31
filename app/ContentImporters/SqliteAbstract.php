<?php

namespace App\ContentImporters;

/**
 * Class SqliteAbstract
 *
 * FOR FUTURE USE: base for content importers that read SQLite database files.
 *
 * Intended hooks:
 *  openFile()    - open the file with PDO ('sqlite:' . $path), read only
 *  readMeta()    - volume attributes from the database's own info table, if it has one
 *  readBatches() - SELECT from the content table with LIMIT / OFFSET, keyed by rowid
 *  mapRow()      - by column name, from a per-format column => content field map
 *  closeFile()   - drop the PDO connection
 */
abstract class SqliteAbstract extends ContentImportAbstract
{

}
