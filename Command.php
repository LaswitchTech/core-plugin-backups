<?php

// Import additionnal class into the global namespace
use LaswitchTech\Core\Abstracts\Command;

class BackupsCommand extends Command {

    // Global Properties
    private $UUID;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Call Parent Constructor
        parent::__construct();

        // Import Global Variables
        global $UUID;

        // Set Properties
        $this->UUID = $UUID;
    }

    /**
     * Backup current instance
     */
    public function initAction()
    {
        // Output the backup path
        $this->Output->print("Backing up current instance to " . $this->Model->Backups->init());
    }

    /**
     * List all backups
     */
    public function listAction()
    {
        // Import Global Variables
        global $UUID;

        // Set the backup directory
        $path = $this->Config->root() . DIRECTORY_SEPARATOR . 'backup';

        // Output all backups
        $this->Output->print("Found backups:");

        $archives = $this->Model->Backups->list();

        // List all backups
        if(empty($archives)){

            // Output a failure message
            $this->Output->print("No backups found.");
        } else {

            // Output the list of backups
            foreach($this->Model->Backups->list() as $archive){

                // Output the archive name
                $this->Output->print("[".$archive['date']."] " . $archive['name'] . " (" . $archive['size'] . " bytes)");
            }
        }
    }

    /**
     * Restore a backup
     */
    public function restoreAction()
    {
        // Check if a backup uuid is provided
        if($this->Request->getArguments(3)){

            // Set the path to the backup directory
            $path = $this->Config->root() . DIRECTORY_SEPARATOR . 'backup'. DIRECTORY_SEPARATOR . $this->Request->getArguments(3);

            // Unpack the archive
            if($this->Model->Backups->unpack($path . '.zip', $path)){

                // Copy the code and data to the root directory
                if($this->Model->Backups->copy($path . "/Code", $this->Config->root())){

                    // Import the database from the backup directory
                    if($this->Model->Backups->import($path)){

                        // Delete the temporary backup directory
                        if($this->Model->Backups->delete($path)){

                            // Output a success message
                            $this->Output->print("Restore completed successfully");
                        } else {

                            // Output a failure message
                            $this->Output->print("Restore failed: Unable to delete the temporary backup directory.");
                        }
                    } else {

                        // Output a failure message
                        $this->Output->print("Restore failed: Database restoration failed.");
                    }
                } else {

                    // Output a failure message
                    $this->Output->print("Restore failed: Unable to copy the code and data to the root directory.");
                }
            } else {

                // Output a failure message
                $this->Output->print("Restore failed: Unable to unpack the archive.");
            }
        } else {

            // Output a failure message
            $this->Output->print("Restore failed: No backup uuid provided.");
            return;
        }
    }
}
