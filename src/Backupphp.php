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
     * Filename generated
     *
     * @var string
     */
    public $fileName;

    /**
     * The constructor
     *
     * @param string $host   Database host
     * @param string $user   Database user
     * @param string $dbname Database name
     * @param string $pass   Password for the user
     */
    public function __construct($host, $user, $dbname, $pass, $encoding)
    {
        $this->_pdo = new PDO('mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $encoding, $user, $pass);
        $this->_generateFileName();
    }

    /**
     * Makes the mani proccess on the fly
     *
     * @param string $host     Database host
     * @param string $user     Database user
     * @param string $dbname   Database name
     * @param string $pass     Password for the user
     * @param string $filePath The full server path where to save the backup
     */
    public static function backup(
        $host, 
        $user, 
        $dbname, 
        $pass, 
        $filePath, 
        $databasePrefix = null,
        $encoding = 'utf8'
    ) {
        $instance = null;

        // First resource: database connection
        try {
            $instance = new Backupphp($host, $user, $dbname, $pass, $encoding);
        } catch (Exception $e) {
            echo "Could not connect to database.";
            http_response_code(500);
            return;
        }

        try {
            $instance->_fillDatabaseTables($databasePrefix);
        } catch (Exception $e) {
            echo $e->getMessage();
            http_response_code(500);
            return;
        }

        // Second resource: filesystem
        try {
            $instance->_createBackupFile($filePath);
        } catch (Exception $e) {
            echo $e->getMessage();
            http_response_code(500);
            return;
        }

        foreach ($instance->_tables as $table) {
            $dropCommand = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $createTableCommand = $instance->generateCreateTableScript($table);

            $instance->_writeInFile($dropCommand);
            $instance->_writeInFile($createTableCommand);
            $instance->_writeInFile("LOCK TABLES `{$table}` WRITE;");
            $instance->_tableInsertsData($table);
            $instance->_writeInFile("UNLOCK TABLES;");
        }

        echo 'Successfully created the backup file: ' . $instance->fileName;
    }

    /**
     * Fetches the databases tables
     * 
     * @param string $prefix In case of existing, only fetches the tables with the 
     *                        following prefix
     *
     * @return void
     */
    public function _fillDatabaseTables($prefix = null) {

        $query_show_tables = "SHOW TABLES";
        $stmt = $this->_pdo->prepare($query_show_tables);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($results) === 0) {
            throw new Exception("Your database is empty!");
        }

        if ($prefix) {
            foreach ($results as $key => $result) {
                if (!preg_match('/^' . $prefix . '/', $result[0])) {
                    unset($results[$key]);
                }
            }

            if (!count($results)) {
                throw new Exception("No table with given prefix.");
            }

            $results = array_values($results);
        }

        foreach ($results as $entry) {
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

        $fullFilePath = $base_directory . $this->fileName;

        $this->_fileResource = fopen($fullFilePath, 'w');

        if ($this->_fileResource === false) {
            throw new Exception("Problem on creating the file.");
        }
    }

    /**
     * Sets backup file name
     *
     * @return void
     */
    private function _generateFileName()
    {
        $format_friendly = "Ymd-H\hi\ms\s";
        $file_name = date($format_friendly, time()) . ".sql";

        $this->fileName = $file_name;
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
        
        $query_loop_data = "SELECT * FROM `{$table}`";
        $stmt = $this->_pdo->prepare($query_loop_data);
        $stmt->execute();

        // $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = $stmt->fetchAll();

        foreach ($results as $row) {

            $result_collections = $this->_generateValuesFromQueryRowResult($row, $columns);

            $insert_statement = "INSERT INTO {$table} VALUES ({$result_collections});";

            $this->_writeInFile($insert_statement);
        }
    }

    /**
     * Return an array of two arrays: The first one containing the the array of field
     *  names. The second one, containing the fields types
     *
     * @param string $table The name's table
     * 
     * @return array
     */
    private function _fetchTableColumns($table)
    {
        $query_select_column = "DESCRIBE {$table}";
        $results = $this->_pdo->query($query_select_column, PDO::FETCH_ASSOC);
        
        $columns_array = [];
        $columns_types_array = [];

        foreach($results as $column) {
            $columns_array[]      = $column['Field'];
            $columns_type_array[] = $column['Type'];
        }

        return [ $columns_array, $columns_type_array ];
    }

    /**
     * Generates the string values to be inserted in the insert statement
     *
     * @param array $row_result   The result row provided by a query execution
     * @param array $fields_array The array of fields to loop through
     * 
     * @return string
     */
    private function _generateValuesFromQueryRowResult($row_result, $columns_and_types)
    {
        $results_array = [];

        for ($i = 0; $i < count($row_result) / 2; $i++) {
            $results_array[] = $this->_decidesTermType($columns_and_types, $i, $row_result);
        }

        $string_values = implode(",", $results_array);

        return $string_values;
    }

    /**
     * Decides what it will append to the string that will be inserted in the insert 
     *  statement
     *
     * @param array $columns_and_types The array containing the array of column name 
     *                                 and the one with the column type
     * @param int   $loopValue         In which iteration does it will test
     * 
     * @return string
     */
    private function _decidesTermType($columns_and_types, $loopValue, $row_result)
    {
        $field_name = $columns_and_types[0][$loopValue];
        $field_type = $columns_and_types[1][$loopValue];
        $raw_value  = $row_result[$loopValue];

        if ($raw_value === null) {
            return "NULL";
        } elseif (preg_match('/^(int|bigint)/', $field_type) && $raw_value !== "") {
            return $raw_value;
        } else {
            return $this->_pdo->quote($raw_value);
        }
    }

}
