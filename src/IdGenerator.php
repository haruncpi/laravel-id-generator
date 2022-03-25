<?php namespace Haruncpi\LaravelIdGenerator;

use Illuminate\Support\Facades\DB, Exception;

class IdGenerator
{
    // To generate application ID| custom unique ID as prefixed and auto incremental; (Ex: INV-000001,INV-000002)
    public static function generateIncremental($configArr)
    {
        if (!array_key_exists('table', $configArr) || $configArr['table'] == '') {
            throw new Exception('Must need a table name');
        }
        if (!array_key_exists('length', $configArr) || $configArr['length'] == '') {
            throw new Exception('Must specify the length of ID');
        }
        if (!array_key_exists('prefix', $configArr) || $configArr['prefix'] == '') {
            throw new Exception('Must specify a prefix of your ID');
        }

        if (array_key_exists('where', $configArr)) {
            if (is_string($configArr['where']))
                throw new Exception('where clause must be an array, you provided string');
            if (!count($configArr['where']))
                throw new Exception('where clause must need at least an array');
        }

        $table = $configArr['table'];
        $field = array_key_exists('field', $configArr) ? $configArr['field'] : 'id';
        $prefix = $configArr['prefix'];
        $resetOnPrefixChange = array_key_exists('reset_on_prefix_change', $configArr) ? $configArr['reset_on_prefix_change'] : false;
        $length = $configArr['length'];

        $fieldInfo = static::getFieldType($table, $field);
        $tableFieldType = $fieldInfo['type'];
        $tableFieldLength = $fieldInfo['length'];

        if (in_array($tableFieldType, ['int', 'integer', 'bigint', 'numeric']) && !is_numeric($prefix)) {
            throw new Exception("$field field type is $tableFieldType but prefix is string");
        }

        if ($length > $tableFieldLength) {
            throw new Exception('Generated ID length is bigger then table field length');
        }

        $prefixLength = strlen($configArr['prefix']);
        $idLength = $length - $prefixLength;
        $whereString = '';

        if (array_key_exists('where', $configArr)) {
            $whereString .= " WHERE ";
            foreach ($configArr['where'] as $row) {
                $whereString .= $row[0] . "=" . $row[1] . " AND ";
            }
        }
        $whereString = rtrim($whereString, 'AND ');


        $totalQuery = sprintf("SELECT count(%s) total FROM %s %s", $field, $configArr['table'], $whereString);
        $total = DB::select(trim($totalQuery));

        if ($total[0]->total) {
            // See Ex: https://onecompiler.com/mysql/3xx3t2dnf
            if ($resetOnPrefixChange) {
                $maxQuery = sprintf("SELECT MAX(SUBSTR(%s, LENGTH('%s '))) AS maxid FROM %s WHERE %s LIKE %s", $field, $prefix, $table, $field, "'" . $prefix . "%'");
            } else {
                $maxQuery = sprintf("SELECT MAX(SUBSTR(%s, LENGTH('%s '))) AS maxid FROM %s", $field, $prefix, $table);
            }

            $queryResult = DB::select($maxQuery);
            $maxId = $queryResult[0]->maxid;

            return $prefix . str_pad((int)$maxId + 1, $idLength, '0', STR_PAD_LEFT);

        } else {
            return $prefix . str_pad(1, $idLength, '0', STR_PAD_LEFT);
        }
    }

    // To generate application ID| custom unique ID as prefixed and random; (Ex: INV-456432,INV-876123)
    public static function generateRandom($configArr)
    {
        if (!array_key_exists('table', $configArr) || $configArr['table'] == '') {
            throw new Exception('Must need a table name');
        }
        if (!array_key_exists('length', $configArr) || $configArr['length'] == '') {
            throw new Exception('Must specify the length of ID');
        }

        // format option to specify the characters used to generate ID; 
        if (array_key_exists('format', $configArr) &&
            !in_array($configArr['format'], ['alpha', 'numeric', 'alpha_num'])){

            throw new Exception('The format value must one this options (alpha | numeric) of ID, Default is alpha_num');
        }

        $table = $configArr['table'];
        $field = array_key_exists('field', $configArr) ? $configArr['field'] : 'id';
        $prefix = array_key_exists('prefix', $configArr) ? $configArr['prefix'] : '';
        $length = $configArr['length'];
        $format = isset($configArr['format']) ? $configArr['format'] : 'alpha_num';

        $fieldInfo = static::getFieldType($table, $field);
        $tableFieldType = $fieldInfo['type'];
        $tableFieldLength = $fieldInfo['length'];

        $prefixLength = strlen($prefix);
        $idLength = $length - $prefixLength;

        if(isset($prefix)){
            if (in_array($tableFieldType, ['int', 'integer', 'bigint', 'numeric']) &&
                !empty($prefix) && !is_numeric($prefix)) {
                throw new Exception("$field field type is $tableFieldType but prefix is string");
            }
 }

        if (in_array($tableFieldType, ['int', 'integer', 'bigint', 'numeric']) &&
            in_array($format, ['alpha','alpha_num'])) {

            throw new Exception("$field field type is $tableFieldType but format is (Alpha | Alpha Numeric)");
        }

        if ($length > $tableFieldLength) {
            throw new Exception('Generated ID length is bigger then table field length');
        }

        do {
            $generated_id = match($format){
                'alpha' => $prefix.static::randomStrFrom('alpha', $idLength),
                'numeric' => $prefix.static::randomStrFrom('numeric', $idLength),
                default => $prefix.static::randomStrFrom('alpha_num', $idLength),
            };

        } while (!static::validateGeneratedId($table, $field, $generated_id));

        return $generated_id;
    }


    private static function getFieldType($table, $field)
    {
        $connection = config('database.default');
        $driver = DB::connection($connection)->getDriverName();
        $database = DB::connection($connection)->getDatabaseName();

        if ($driver == 'mysql') {
            $sql = 'SELECT column_name AS "column_name",data_type AS "data_type",column_type AS "column_type" FROM information_schema.columns ';
            $sql .= 'WHERE table_schema=:database AND table_name=:table';
        } else {
            // column_type not available in postgres SQL
            // table_catalog is database in postgres
            $sql = 'SELECT column_name AS "column_name",data_type AS "data_type" FROM information_schema.columns ';
            $sql .= 'WHERE table_catalog=:database AND table_name=:table';
        }

        $rows = DB::select($sql, ['database' => $database, 'table' => $table]);
        $fieldType = null;
        $fieldLength = 20; // Set field default length equal to 20 to cover on the lack of field column_type in postgres SQL

        foreach ($rows as $col) {
            if ($field == $col->column_name) {

                $fieldType = $col->data_type;
                //column_type not available in postgres SQL
                //mysql 8 optional display width for int,bigint numeric field

                if ($driver == 'mysql') {
                    //example: column_type int(11) to 11
                    preg_match("/(?<=\().+?(?=\))/", $col->column_type, $tblFieldLength);
                    if(count($tblFieldLength)){
                        $fieldLength = $tblFieldLength[0];
                    }
                }

                break;
            }
        }

        if ($fieldType == null) throw new Exception("$field not found in $table table");
        return ['type' => $fieldType, 'length' => $fieldLength];
    }

    private static function randomStrFrom($set, $length)
    {
        if ($set == 'alpha') {
            $set = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        }
        if ($set == 'alpha_num') {
            $set = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        }
        if ($set == 'numeric') {
            $set = '0123456789';
        }
        $set_length = strlen($set);
        $str = '';
        for ($i=0; $i < $length; $i++) {
            $str .= $set[mt_rand(0,$set_length - 1)];
        }
        return $str;
    }

    private static function validateGeneratedId($table, $field, $value)
    {

        $sql = sprintf("SELECT count(%s) totalMatch FROM %s WHERE %s = %s", $field, $table, $field, $value);

        return DB::select($sql)[0]->totalMatch == 0 ;
    }
}
