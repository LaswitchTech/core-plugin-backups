<?php

/**
 * Core Framework - BackupModel
 *
 * @license    MIT (https://mit-license.org/)
 * @author     Louis Ouellet <louis@laswitchtech.com>
 */

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Model;

class BackupsModel extends Model {

    // Properties
    private $Config;
    private $Log;
    private $UUID;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Call the parent constructor
        parent::__construct();

        // Import Global Variables
        global $LOG, $CONFIG, $UUID;

        // Set Properties
        $this->Config = $CONFIG;
        $this->Log = $LOG;
        $this->UUID = $UUID;

        // Add the backup log file
        $this->Log->add('backup');
    }

    /**
     * Convert bytes to a human-readable filesize string
     *
     * @param int $bytes Number of bytes
     * @param int $decimals Number of decimal places to include
     * @return string Human-readable filesize (e.g., "1.23 MB")
     */
    public function readable(int $bytes, int $decimals = 2): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['B','KB','MB','GB','TB','PB','EB','ZB','YB'];
        $factor = floor(log($bytes, 1024)); // which power of 1024 to use

        // Cap the factor index if it exceeds our array range
        $factor = min($factor, count($units) - 1);

        $size = $bytes / pow(1024, $factor);

        return sprintf("%.{$decimals}f", $size) . ' ' . $units[$factor];
    }


    /**
     * List all backup files
     *
     * @return array List of backup files
     */
    public function list(): array
    {
        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // Initialize the list of files
        $files = [];

        // Get the list of backup files
        $path = $this->Config->root() . DIRECTORY_SEPARATOR . 'backup';

        // Initialize the backup directory if it doesn't exist
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            $this->Log->error("Could not create or access backup directory: $path");
            return [];
        }

        // Loop through the files in the backup directory
        foreach(array_diff(scandir($path), array('..', '.')) as $file){

            // Check if the file is a zip file
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {

                // Add the file to the list
                $files[] = [
                    "name" => str_replace('.zip','',$file),
                    "path" => $path . DIRECTORY_SEPARATOR . $file,
                    "size" => $this->readable(filesize($path . DIRECTORY_SEPARATOR . $file)),
                    "date" => date("Y-m-d H:i:s", filemtime($path . DIRECTORY_SEPARATOR . $file)),
                    "hash" => md5_file($path . DIRECTORY_SEPARATOR . $file),
                ];
            }
        }

        // Return the list of files
        return $files;
    }

    /**
     * Copy directory recursively
     *
     * @param string $source Source directory
     * @param string $destination Destination directory
     * @param array $exclude List of exclusions
     * @return bool true on success, false on failure
     */
    public function copy(string $source, string $destination, array $exclude = []): bool
    {
        // Set the log file
        $this->Log->set('backup');

        // The source directory must exist
        if (!is_dir($source)) {
            $this->Log->error("Source directory does not exist: $source");
            return false;
        }

        // Attempt to create the destination directory if it doesn't exist
        // The check for is_dir($destination) after mkdir() handles the case
        // where multiple processes might try to create it in parallel
        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            $this->Log->error("Failed to create destination directory: $destination");
            return false;
        }

        // Open the source directory to read files
        $dirHandle = opendir($source);
        if (!$dirHandle) {
            $this->Log->error("Failed to open source directory: $source");
            return false;
        }

        // Iterate through each file/folder in the source
        while (false !== ($item = readdir($dirHandle))) {
            // Skip pointers
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $item;
            $dstPath = $destination . DIRECTORY_SEPARATOR . $item;

            // If this folder is excluded, just skip it
            if (is_dir($srcPath) && in_array($item, $exclude, true)) {
                continue;
            }

            // Directory – recurse
            if (is_dir($srcPath)) {
                if (!$this->copy($srcPath, $dstPath, $exclude)) {
                    closedir($dirHandle);
                    $this->Log->error("Failed to copy directory: $srcPath to $dstPath");
                    return false;
                }
            } else {
                // File – just copy
                if (!copy($srcPath, $dstPath)) {
                    closedir($dirHandle);
                    $this->Log->error("Failed to copy file: $srcPath to $dstPath");
                    return false;
                }
            }
        }

        closedir($dirHandle);
        $this->Log->success("Backup completed successfully from $source to $destination");
        return true;
    }

    /**
     * Recursively delete a directory (including its contents).
     *
     * @param string $directory Path to the directory you want to remove
     * @return bool true on success, false on failure
     */
    public function delete(string $directory): bool
    {
        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // If it doesn't exist, treat it as an error or success depending on your preference
        if (!file_exists($directory)) {
            // Option 1: Treat as an error
            $this->Log->error("Directory does not exist: $directory");
            return false;

            // Option 2: Treat as success since there's nothing to delete
            // $this->Log->info("Directory does not exist, nothing to delete: $directory");
            // return true;
        }

        // If it's a file or symlink, just unlink it
        if (!is_dir($directory)) {
            if (!@unlink($directory)) {
                $this->Log->error("Failed to delete file or symlink: $directory");
                return false;
            }
            $this->Log->success("Deleted file or symlink: $directory");
            return true;
        }

        // Otherwise, recursively remove contents
        $items = scandir($directory);
        if ($items === false) {
            $this->Log->error("Failed to scan directory: $directory");
            return false;
        }

        foreach ($items as $item) {
            // Skip pointers
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            // Recursively call delete on each item
            if (!$this->delete($path)) {
                // If any item fails to be deleted, return false
                return false;
            }
        }

        // Finally, remove the now-empty directory
        if (!@rmdir($directory)) {
            $this->Log->error("Failed to delete directory (it might not be empty or permission denied): $directory");
            return false;
        }

        $this->Log->success("Deleted directory: $directory");
        return true;
    }

    /**
     * Archive directory into a zip file
     *
     * @param string $source      Source directory
     * @param string $destination Destination zip file
     * @param array  $exclude     List of exclusions (by directory name)
     * @param bool   $append      Append to an existing zip file (if true)
     * @return bool  true on success, false on failure
     */
    public function archive(string $source, string $destination, array $exclude = [], bool $append = false): bool
    {
        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // Make sure the source directory exists
        if (!is_dir($source)) {
            $this->Log->error("Source directory does not exist for archiving: $source");
            return false;
        }

        $zip = new ZipArchive();

        // Decide on the mode (append or overwrite)
        // - CREATE alone = open for append if file exists; create otherwise.
        // - CREATE | OVERWRITE = always create new, overwriting if file already exists.
        $mode = $append
            ? (ZipArchive::CREATE)
            : (ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Attempt to open the zip in the chosen mode
        if ($zip->open($destination, $mode) !== true) {
            $this->Log->error("Could not open or create zip file: $destination");
            return false;
        }

        // Helper closure to recursively add files/folders
        $addFiles = function ($dir, $baseDir) use (&$addFiles, $zip, $exclude) {
            if (!($handle = opendir($dir))) {
                return false;
            }

            while (false !== ($item = readdir($handle))) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
                $relativePath = substr($fullPath, strlen($baseDir) + 1);

                // Skip if the directory is in the exclude list
                if (is_dir($fullPath) && in_array($item, $exclude, true)) {
                    continue;
                }

                // Recurse into directories
                if (is_dir($fullPath)) {
                    $zip->addEmptyDir($relativePath); // optional, but good practice
                    if ($addFiles($fullPath, $baseDir) === false) {
                        closedir($handle);
                        return false;
                    }
                } else {
                    // Add or overwrite a file in the zip
                    if (!$zip->addFile($fullPath, $relativePath)) {
                        closedir($handle);
                        return false;
                    }
                }
            }

            closedir($handle);
            return true;
        };

        // Recursively add everything from $source
        if ($addFiles($source, $source) === false) {
            $zip->close();
            $this->Log->error("Failed while adding files to archive: $destination");
            return false;
        }

        // Close the archive
        $zip->close();

        // Done
        $this->Log->success(($append ? 'Appended to' : 'Created') . " archive: $destination");
        return true;
    }

    /**
     * Unpack (extract) a zip archive to a given location.
     *
     * @param string $source Path to the ZIP file.
     * @param string $destination Directory where files should be extracted.
     * @return bool true on success, false on failure
     */
    public function unpack(string $source, string $destination): bool
    {
        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // Check if the archive file exists
        if (!file_exists($source) || !is_file($source)) {
            $this->Log->error("Archive file does not exist: $source");
            return false;
        }

        // Attempt to create the destination directory if it doesn't exist
        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            $this->Log->error("Failed to create or access destination directory: $destination");
            return false;
        }

        // Initialize a new ZipArchive instance
        $zip = new ZipArchive();

        // Try opening the ZIP file
        if ($zip->open($source) !== true) {
            $this->Log->error("Could not open the zip file: $source");
            return false;
        }

        // Extract the contents to the specified destination
        if (!$zip->extractTo($destination)) {
            $this->Log->error("Failed to extract $source to $destination");
            $zip->close();
            return false;
        }

        // Close the ZIP
        $zip->close();

        // Done
        $this->Log->success("Unpacked archive $source to $destination successfully");
        return true;
    }

    /**
     * Perform a dump of the database
     *
     * @param string $path Path to the dump directory
     * @return bool true on success, false on failure
     * @throws Throwable
     */
    public function dump(string $path): bool
    {
        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // Ensure the dump path exists (and create if needed)
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            $this->Log->error("Could not create or access dump directory: $path");
            return false;
        }

        // Ensure that the Definition directory is cleared
        $this->delete($path . DIRECTORY_SEPARATOR . "Definition");

        // We might also want dedicated subdirectories for definitions and data:
        $definitionDir = $path . DIRECTORY_SEPARATOR . "Definition";
        $dataDir       = $path . DIRECTORY_SEPARATOR . "Data";

        // Create them if they don't exist
        foreach ([$definitionDir, $dataDir] as $subDir) {
            if (!is_dir($subDir) && !mkdir($subDir, 0755, true) && !is_dir($subDir)) {
                $this->Log->error("Could not create or access subdirectory: $subDir");
                return false;
            }
        }

        // Loop through the tables
        try {
            foreach ($this->Database->schema()->tables() as $table) {

                // Create & save the schema
                $Schema = $this->Database->schema()
                    ->define($table)
                    ->save();

                // Move the .map file to the Definition directory
                $fromPath = $this->Config->root() . DIRECTORY_SEPARATOR . "Definition" . DIRECTORY_SEPARATOR . $table . ".map";
                $toPath   = $definitionDir . DIRECTORY_SEPARATOR . $table . ".map";

                if (!file_exists($fromPath)) {
                    $this->Log->warning("Schema map file not found for table '$table' at $fromPath (skipped).");
                } else {
                    if (!@rename($fromPath, $toPath)) {
                        $this->Log->error("Could not rename $fromPath to $toPath for table '$table'.");
                        return false;
                    }
                }

                // Create a Query
                $Query = $this->Database->query()
                    ->table($table)
                    ->select('*');

                // Retrieve the data
                $data = $Query->fetch();

                // Save the data as JSON
                $dataFile = $dataDir . DIRECTORY_SEPARATOR . $table . ".dump";
                if (file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
                    $this->Log->error("Could not write data to $dataFile for table '$table'.");
                    return false;
                }
            }
        } catch (Throwable $ex) {
            // Catch any unexpected exceptions and log them
            $this->Log->error("Exception while dumping database: " . $ex->getMessage());
            return false;
        }

        // If we reached this point, everything succeeded
        $this->Log->success("Database dump completed successfully: $path");
        return true;
    }

    /**
     * Perform an import of the database
     *
     * @param string $path Path to the dump directory
     * @return bool true on success, false on failure
     * @throws Throwable
     */
    public function import(string $path): bool
    {

        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // Verify the import directory exists
        if (!is_dir($path)) {
            $this->Log->error("Could not find the dump directory: $path");
            return false;
        }

        // Clear the local Definition directory
        $localDefinitionDir = $this->Config->root() . DIRECTORY_SEPARATOR . 'Definition';
        if (is_dir($localDefinitionDir)) {
            $this->delete($localDefinitionDir);
        }

        // Check for the "Definition" and "Data" subdirectories
        $definitionPath = $path . DIRECTORY_SEPARATOR . 'Definition';
        $dataPath       = $path . DIRECTORY_SEPARATOR . 'Data';

        if (!is_dir($definitionPath)) {
            $this->Log->error("Could not find 'Definition' directory in $path");
            return false;
        }

        if (!is_dir($dataPath)) {
            $this->Log->error("Could not find 'Data' directory in $path");
            return false;
        }

        // Ensure local Definition directory exists in your application
        $localDefinitionDir = $this->Config->root() . DIRECTORY_SEPARATOR . 'Definition';
        if (!is_dir($localDefinitionDir) && !mkdir($localDefinitionDir, 0755, true) && !is_dir($localDefinitionDir)) {
            $this->Log->error("Failed to create or access local definition directory: $localDefinitionDir");
            return false;
        }

        try {
            // Scan for all .map files in the dump's Definition folder
            $definitions = array_diff(scandir($definitionPath), ['.', '..']);

            foreach ($definitions as $defFile) {
                // Only process .map files
                if (pathinfo($defFile, PATHINFO_EXTENSION) !== 'map') {
                    continue;
                }

                // Extract table name by removing ".map"
                $table = basename($defFile, '.map');

                // Copy the definition file from the dump into the local Definition folder
                $sourceMap = $definitionPath . DIRECTORY_SEPARATOR . $defFile;
                $targetMap = $localDefinitionDir . DIRECTORY_SEPARATOR . $defFile;

                // Remove any existing file
                if (file_exists($targetMap)) {
                    if (!@unlink($targetMap)) {
                        $this->Log->error("Could not remove existing definition file: $targetMap");
                        return false;
                    }
                }

                // Copy new definition from the dump
                if (!@copy($sourceMap, $targetMap)) {
                    $this->Log->error("Could not copy definition file: $defFile to $targetMap");
                    return false;
                }

                // Build a fresh Schema object for the table
                $Schema = $this->Database->schema()->define($table);

                // Drop it if it already exists
                if ($Schema->exists()) {
                    $Schema->drop();
                }

                // Add Debugging information
                $this->Log->debug("Creating: $table");

                // Create it from the newly copied definition
                $Schema->create();

                // Look for data file to import (from the "Data" folder)
                // Our "dump" method stored everything as table.dump
                $dumpFile = $dataPath . DIRECTORY_SEPARATOR . $table . '.dump';
                if (is_file($dumpFile)) {
                    $json = file_get_contents($dumpFile);
                    $records = json_decode($json, true);

                    if (!is_array($records)) {
                        $this->Log->warning("No valid JSON data found in $dumpFile; skipping import for table '$table'.");
                        continue;
                    }

                    // Insert each record
                    foreach ($records as $record) {

                        // Add Debugging information
                        $this->Log->debug("Importing " . PHP_EOL . json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . " into table '$table'");

                        // Insert the record into the table
                        $this->Database->query()
                            ->table($table)
                            ->insert($record)
                            ->execute();
                    }
                    $this->Log->info("Imported " . count($records) . " rows into table '$table'");
                } else {
                    $this->Log->warning("No .dump file found for table '$table' at $dumpFile; skipping data import.");
                }
            }
        } catch (Throwable $ex) {
            // Log and return false if anything unexpected happens
            $this->Log->error("Exception while importing database: " . $ex->getMessage());
            return false;
        }

        // If we reached here, import was successful
        $this->Log->success("Database import from '$path' completed successfully.");
        return true;
    }

    /**
     * Initialize the backup process
     *
     * @return string Path to the backup file
     */
    public function init(): string
    {

        // Set the log channel to 'backup'
        $this->Log->set('backup');

        // Set the backup directory
        $path = $this->Config->root() . DIRECTORY_SEPARATOR . 'backup'. DIRECTORY_SEPARATOR . $this->UUID->toString("Manual-".time());

        // Create the list of excluded directories
        $exclude = [
            '.git',
            'backup',
            'cache',
            'error',
            'log',
            'tmp',
            'vendor',
        ];

        // Backup the code and data to the backup directory
        if($this->copy($this->Config->root(), $path . "/Code", $exclude)){

            // Backup the database to the backup directory
            if($this->dump($path)){

                // Create an archive of the backup directory
                if($this->archive($path, $path . '.zip', $exclude)){

                    // Delete the temporary backup directory
                    if($this->delete($path)){

                        // Output a success message
                        $this->Log->success("Backup completed successfully");
                        return $path . '.zip';
                    } else {

                        // Output a failure message
                        $this->Log->error("Backup failed: Unable to delete the temporary backup directory.");
                    }
                } else {

                    // Output a failure message
                    $this->Log->error("Backup failed: Archive step failed.");
                }
            } else {

                // Output a failure message
                $this->Log->error("Backup failed: Database dump step failed.");
            }
        } else {

            // Output a failure message
            $this->Log->error("Backup failed: Code backup step failed.");
        }

        return '';
    }
}
