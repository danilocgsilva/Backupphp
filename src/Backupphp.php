<?php

namespace Danilocgsilva;

use PDO;
use Exception;

class Backupphp
{
    /**
     * PDO Object used through this object
     *
     * @var PDO
     */
    private $_pdo;

    /**
     * All tables from a given database
     *
     * @var array
     */
    private $_tables;

    /**
     * File resource
     *
     * @var resource
     */
    private $_fileResource;

    /**
     * The constructor
     *
     * @param string $host   Database host
     * @param string $user   Database user
     * @param string $dbname Database name
     * @param string $pass   Password for the user
     */
    public function __construct($host, $user, $dbname, $pass)
    {
        $this->_pdo = new PDO('mysql:host=' . $host . ';dbname=' . $dbname, $user, $pass);
    }

    /**
     * Makes the mani proccess on the fly
     *
     * @param string $host   Database host
     * @param string $user   Database user
     * @param string $dbname Database name
     * @param string $pass   Password for the user
     */
    public static function backup($host, $user, $dbname, $pass, $filePath)
    {
        $instance = null;

        // First resource: database connection
        try {
            $instance = new Backupphp($host, $user, $dbname, $pass);
        } catch (Exception $e) {
            echo "Could not connect to database.";
            throw $e;
            return;
        }

        // Second resource: filesystem
        try {
            $instance->_createBackupFile($filePath);
        } catch (Exception $e) {
            echo $e->getMessage();
            throw $e;
            return;
        }

        $instance->_fillDatabaseTables();

        foreach ($instance->_tables as $table) {
            $command = 'DROP TABLE ' . $table . ';';
        }

        $instance->_writeInFile();

        echo 'Reached the end!';
    }

    /**
     * Fetches the databases tables
     *
     * @return void
     */
    public function _fillDatabaseTables() {
        foreach ($this->_pdo->query('SHOW TABLES', PDO::FETCH_NUM) as $entry) {
            $this->_tables[] = $entry[0];
        }
    }

    /**
     * Creates the file in the file system and assing to the current object
     *
     * @return void
     */
    private function _createBackupFile($filePath)
    {
        $base_directory = $filePath . DIRECTORY_SEPARATOR;
        if (!is_writable($base_directory)) {
            throw new Exception("No permission to write in the current provided folder.");
        }

        $fullFilePath = $base_directory . $this->_generateFileName();

        $this->_fileResource = fopen($fullFilePath, 'w');

        if ($this->_fileResource === false) {
            throw new Exception("Problem on creating the file.");
        }
    }

    /**
     * Generates the friendly file name for backup
     *
     * @return void
     */
    private function _generateFileName()
    {
        $format_friendly = "Ymd-H\hi\ms\s";
        $file_name = date($format_friendly, time()) . ".sql";

        return $file_name;
    }

    private function _writeInFile()
    {
        fwrite($this->_fileResource, 'oioioio');
    }
}
