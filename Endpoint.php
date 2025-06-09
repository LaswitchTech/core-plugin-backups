<?php

/**
 * Core Framework - BackupEndpoint
 *
 * @license    MIT (https://mit-license.org/)
 * @author     Louis Ouellet <louis@laswitchtech.com>
 */

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Objects;
use \LaswitchTech\Core\Abstracts\Endpoint;

class BackupsEndpoint extends Endpoint {

    /**
     * Constructor
     */
    public function __construct()
    {

        // Call Parent Constructor
        parent::__construct();

        // Retrieve the namespace
        $namespace = $this->Request->getNamespace();

        // Set Global access
        $this->Public = false;

        // Set Properties
        switch($namespace){
            case "/backup/init":
                $this->Level = $this->Config->get('application', 'maintenance') ? 0 : 2;
                break;
            case "/backup/index":
                $this->Level = 1;
                break;
            case "/backup/upload":
                $this->Level = 2;
                break;
            case "/backup/restore":
            case "/backup/delete":
                $this->Level = 4;
                break;
        }
    }

    /**
     * Upload a Backup File
     *
     * @return array
     */
    public function uploadAction(): array
    {
        // Import Global Variables
        global $CSRF,$UUID;

        // Set the default message
        $message = ["status" => 200, "message" => "OK", "data" => []];

        // Check the request method
        if($this->Request->getMethod() == "POST"){
            $message["data"]["CSRF"] = [
                "token" => $CSRF->token(),
                "key" => $CSRF->key()
            ];
        }

        // Check if the Note is accessible
        if($message['status'] == 200){

            // Check the request method
            if($this->Request->getMethod() == "POST"){

                // Retrieve the parameters
                $parameters = $this->Request->getParams('REQUEST');

                // Set Required Fields
                $required = ['content','extension','name','size','type'];

                // Check if all required fields are set
                if(count(array_intersect_key(array_flip($required), $parameters)) == count($required)){

                    // Retrieve the content
                    $content = base64_decode(explode(',', $parameters['content'])[1]);

                    // Set the path
                    $path = $this->Config->root() . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $parameters['name'];

                    // Save the file to the filesystem
                    $status = $this->Helper->Backups->save($path, $content);

                    // Check if the file was saved successfully
                    if($status){

                        // Set the record
                        $message['data']['record'] = [
                            "name" => str_replace('.zip','',$parameters['name']),
                            "path" => $path,
                            "size" => $this->Model->Backups->readable($this->Helper->Backups->size($parameters['name'])),
                            "date" => date("Y-m-d H:i:s", filemtime($path)),
                            "hash" => md5_file($path),
                        ];
                    } else {

                        // Set an error message
                        $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Unable to save the file"];
                    }
                } else {
                    $message['status'] = 400;
                    $message['message'] = "Bad Request";
                    $message['data']['error'] = "Some required fields are missing [";
                    foreach($required as $key){
                        if(!array_key_exists($key, $parameters)){
                            $message['data']['error'] .= $key.", ";
                        }
                    }
                    $message['data']['error'] = rtrim($message['data']['error'], ", ");
                    $message['data']['error'] .= "]";
                }
            } else {
                $message = ["status" => 405, "message" => "Method Not Allowed", "data" => "The method is not allowed for the requested URL."];
            }
        }

        return $message;
    }

    /**
     * Backup current instance
     */
    public function initAction()
    {
        // Import Global Variables
        global $CSRF;

        // Set the default message
        $message = ["status" => 200, "message" => "OK", "data" => []];

        // Check the request method
        if($this->Request->getMethod() == "POST"){
            $message["data"]["CSRF"] = [
                "token" => $CSRF->token(),
                "key" => $CSRF->key()
            ];
        }

        // Check if the status is still OK
        if($message['status'] == 200){

            // Check the request method
            if($this->Request->getMethod() == "POST"){

                // Start the backup process
                $path = $this->Model->Backups->init();

                // Check if the backup was successful
                if(is_file($path)){

                    // Retrieve the file name
                    $file = basename($path);

                    // Set the UUID
                    $uuid = str_replace(".zip", "", $file);

                    // Set the default message
                    $message["data"]["name"] = $uuid;
                    $message["data"]["path"] = $path;
                    $message["data"]["file"] = $file;
                    $message["data"]["uuid"] = $uuid;
                    $message["data"]["size"] = $this->Model->Backups->readable(filesize($path));
                    $message["data"]["date"] = date("Y-m-d H:i:s", filemtime($path));
                    $message["data"]["hash"] = md5_file($path);
                    $message["data"]["message"] = "Backup completed successfully";
                } else {

                    // Set an error message
                    $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Unable to create the backup"];
                }
            } else {

                // Set an error message
                $message = ["status" => 405, "message" => "Bad Request", "data" => []];
            }
        }

        // Return the message
        return $message;
    }

    /**
     * Fetch all backups
     */
    public function indexAction(): array
    {
        // Set the default message
        $message = ["status" => 200, "message" => "OK", "data" => $this->Model->Backups->list()];

        // Return the message
        return $message;
    }

    /**
     * Delete a backup
     */
    public function deleteAction(): array
    {
        // Set the default message
        $message = ["status" => 200, "message" => "OK", "data" => []];

        // Retrieve the backup UUID
        $uuid = $this->Request->getParams('GET','uuid');

        // Check if the UUID is valid
        if($uuid){

            // Check if the request method is GET
            if($this->Request->getMethod() == "GET"){

                // Check if the backup exists
                if(file_exists($this->Config->root() . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $uuid . ".zip")){

                    // Delete the backup
                    unlink($this->Config->root() . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $uuid . ".zip");

                    // Check if the backup was deleted
                    if(!file_exists($this->Config->root() . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $uuid . ".zip")){

                        // Set a success message
                        $message["data"] = ["message" => "Backup deleted successfully"];
                    } else {

                        // Set an error message
                        $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Unable to delete the backup"];
                    }
                } else {

                    // Set an error message
                    $message = ["status" => 404, "message" => "Not Found", "data" => "Backup not found"];
                }
            } else {

                // Set an error message
                $message = ["status" => 405, "message" => "Bad Request", "data" => []];
            }
        } else {

            // Set an error message
            $message = ["status" => 400, "message" => "Bad Request", "data" => []];
        }

        // Return the message
        return $message;
    }

    /**
     * Restore a backup
     */
    public function restoreAction(): array
    {
        // Set the default message
        $message = ["status" => 200, "message" => "OK", "data" => []];

        // Retrieve the backup UUID
        $uuid = $this->Request->getParams('GET','uuid');

        // Check if the UUID is valid
        if($uuid){

            // Check if the request method is GET
            if($this->Request->getMethod() == "GET"){

                // Set the path to the backup directory
                $path = $this->Config->root() . DIRECTORY_SEPARATOR . 'backup'. DIRECTORY_SEPARATOR . $uuid;

                // Check if the backup exists
                if(file_exists($path . ".zip")){

                    // Unpack the archive
                    if($this->Model->Backups->unpack($path . '.zip', $path)){

                        // Copy the code and data to the root directory
                        if($this->Model->Backups->copy($path . "/Code", $this->Config->root())){

                            // Import the database from the backup directory
                            if($this->Model->Backups->import($path)){

                                // Delete the temporary backup directory
                                if($this->Model->Backups->delete($path)){

                                    // Set a success message
                                    $message["data"] = ["message" => "Backup restored successfully"];
                                } else {

                                    // Set an error message
                                    $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Unable to delete the temporary backup directory"];
                                }
                            } else {

                                // Set an error message
                                $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Database restoration failed"];
                            }
                        } else {

                            // Set an error message
                            $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Unable to copy the code and data to the root directory"];
                        }
                    } else {

                        // Set an error message
                        $message = ["status" => 500, "message" => "Internal Server Error", "data" => "Unable to unpack the archive"];
                    }
                } else {

                    // Set an error message
                    $message = ["status" => 404, "message" => "Not Found", "data" => "Backup not found"];
                }
            } else {

                // Set an error message
                $message = ["status" => 405, "message" => "Bad Request", "data" => []];
            }
        } else {
            // Set an error message
            $message = ["status" => 400, "message" => "Bad Request", "data" => []];
        }

        // Return the message
        return $message;
    }
}
