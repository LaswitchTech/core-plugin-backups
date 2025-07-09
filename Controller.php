<?php

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Controller;

class BackupsController extends Controller {

    /**
     * Constructor
     */
    public function __construct()
    {

        // Call Parent Constructor
        parent::__construct();

        // Retrieve the namespace
        $namespace = $this->Request->getNamespace();

        // Set Properties
        switch($namespace){
            case "/backup/download":
                $this->Public = false;
                $this->Level = 1;
                break;
        }
    }

    /**
     * Get a Backup File
     *
     * @return mixed
     */
    public function downloadAction(): mixed
    {

        // Retrieve the parameters
        $name = $this->Request->getParams('GET', 'name') ?? null;

        // Initialize the file
        $file = ['name' => $name . '.zip'];
        $file['exists'] = $this->Helper->Backups->exists($file['name']);

        // Check if the file exists
        if($file['exists']){

            // Get the file size
            $file['size'] = $this->Helper->Backups->size($file['name']);

            // Get the file content
            $file['content'] = $this->Helper->Backups->get($file['name']);
        }

        // Return the file
        return $file;
    }
}
