<?php

namespace Danilocgsilva;

use PDO;
use Exception;
use ErrorException;

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
            http_response_code(500);
            // throw $e;
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
            $dropCommand = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $createTableCommand = $instance->generateCreateTableScript($table);

            $instance->_writeInFile($dropCommand);
            $instance->_writeInFile($createTableCommand);
            $instance->_writeInFile("LOCK TABLES `{$table}` WRITE;");
            $instance->_tableInsertsData($table);
            $instance->_writeInFile("UNLOCK TABLES;");
        }

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

        if (!is_dir($base_directory)) {
            try {
                mkdir($base_directory);
            } catch (Exception $e) {
                throw new Exception("The directory does not exists and also no permission to crete it.");
            }
        }

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

    /**
     * Adds content to the current object file
     *
     * @param string $content
     * 
     * @return void
     */
    private function _writeInFile($content)
    {
        fwrite($this->_fileResource, "\n" . $content);
    }

    /**
     * Return create table statement
     *
     * @param string $table The table
     * 
     * @return void
     */
    public function generateCreateTableScript($table)
    {
        $query = "SHOW CREATE TABLE ${table}";
        $stmt = $this->_pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_NUM);

        return $result[0][1] . ";";
    }

    /**
     * Loop through table select results and foreach
     *  adds a insert statement. Then, write the statement
     *  to the file
     *
     * @param string $table
     * 
     * @return void
     */
    private function _tableInsertsData($table) {

        $columns = $this->_fetchTableColumns($table);
        $string_columns = implode(", ", $columns);
        
        $query_loop_data = "SELECT * FROM `{$table}`";
        $stmt = $this->_pdo->prepare($query_loop_data);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {

            $result_collections = $this->_generateValuesFromQueryRowResult($row, $columns);

            $insert_statement = "INSERT INTO {$table} ({$string_columns}) VALUES ({$result_collections});";

            $this->_writeInFile($insert_statement);
        }
    }

    private function _fetchTableColumns($table)
    {
        $query_select_column = "DESCRIBE {$table}";
        $results = $this->_pdo->query($query_select_column, PDO::FETCH_NUM);
        
        $columns_array = [];

        foreach($results as $column) {
            $columns_array[] = $column[0];
        }

        return $columns_array;
    }

    private function _generateValuesFromQueryRowResult($row_result, $fields_array)
    {
        $results_array = [];

        foreach ($fields_array as $field) {
            $results_array[] = $this->_pdo->quote(
                $row_result[$field]
            );
        }

        $string_values = implode(",", $results_array);

        return $string_values;
    }
}
