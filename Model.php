<?php

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Model;

class BackupsModel extends Model {

    // Properties
    private $Helper;
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
        global $HELPER, $LOG, $CONFIG, $UUID;

        // Set Properties
        $this->Helper = $HELPER;
        $this->Config = $CONFIG;
        $this->Log = $LOG;
        $this->UUID = $UUID;

        // Add the backup log file
        $this->Log->add('backup');
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
        $this->Helper->Backups->delete($path . DIRECTORY_SEPARATOR . "Definition");

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
            $this->Helper->Backups->delete($localDefinitionDir);
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
            $definitions = array_diff(scandir($definitionPath), ['.', '..', '.DS_Store']);

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
        if($this->Helper->Backups->copy($this->Config->root(), $path . "/Code", $exclude)){

            // Backup the database to the backup directory
            if($this->dump($path)){

                // Create an archive of the backup directory
                if($this->Helper->Backups->archive($path, $path . '.zip', $exclude)){

                    // Delete the temporary backup directory
                    if($this->Helper->Backups->delete($path)){

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
