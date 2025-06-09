<?php

/**
 * Core Framework - BackupHelper
 *
 * @license    MIT (https://mit-license.org/)
 * @author     Louis Ouellet <louis@laswitchtech.com>
 */

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Helper;

class BackupsHelper extends Helper {

    // Properties
    private $Path;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Import Global Variables
        global $CONFIG;

        // Set Properties
        $this->Path = $CONFIG->root() . DIRECTORY_SEPARATOR . 'backup';
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
