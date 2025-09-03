<?php

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Helper;

class BackupsHelper extends Helper {

    // Properties
    protected $Path;
    protected $Config;
    protected $Log;
    protected $UUID;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Import Global Variables
        global $LOG, $CONFIG, $UUID;

        // Set Properties
        $this->Config = $CONFIG;
        $this->Log = $LOG;
        $this->UUID = $UUID;

        // Add the backup log file
        $this->Log->add('backup');

        // Set Properties
        $this->Path = $CONFIG->root() . DIRECTORY_SEPARATOR . 'backup';
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
     * Fetch all backup files
     *
     * @return array List of backup files
     */
    public function fetchAll(): array
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
        foreach(array_diff(scandir($path), array('..', '.','.DS_Store')) as $file){

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
     * Check if a file exists
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        // Check if the file exists
        return file_exists($this->Path . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Get a file's size
     *
     * @param string $path
     * @return bool
     */
    public function size(string $path): int
    {
        // Get the file size
        if (!file_exists($this->Path . DIRECTORY_SEPARATOR . $path)) {
            return 0;
        }
        return filesize($this->Path . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Get a file
     *
     * @param string $path
     * @return string
     */
    public function get(string $path): string
    {
        // Get the file
        var_dump(file_exists($this->Path . DIRECTORY_SEPARATOR . $path));
        if (!file_exists($this->Path . DIRECTORY_SEPARATOR . $path)) {
            return '';
        }
        return file_get_contents($this->Path . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Save a file
     *
     * @param string $path
     * @param string $content
     * @return bool
     */
    public function save(string $path, string $content): bool
    {
        // Check if the file already exists
        if (file_exists($this->Path . DIRECTORY_SEPARATOR . basename($path))) {
            return false;
        }

        // Save the file
        file_put_contents($this->Path . DIRECTORY_SEPARATOR . basename($path), $content);

        // Check if the file was saved
        return file_exists($this->Path . DIRECTORY_SEPARATOR . basename($path));
    }
}
