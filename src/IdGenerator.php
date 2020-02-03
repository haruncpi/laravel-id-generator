<?php

namespace Haruncpi\LaravelIdGenerator;

use Illuminate\Support\Facades\DB, Exception;

class IdGenerator
{

    private function getFieldType($table, $field)
    {
        $colsType = DB::select('describe ' . $table);
        $fieldType = null;
        foreach ($colsType as $col) {
            if ($field == $col->Field) {
                $fieldType = $col->Type;
                break;
            }
        }

        if ($fieldType == null) throw new Exception("$field not found in $table table");
        return $fieldType;
    }

    public static function generate($configArr)
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

        $fieldType = (new self)->getFieldType($table, $field);
        preg_match("/^([\w\-]+)/", $fieldType, $type);
        $tableFieldType = $type[0];
        preg_match("/(?<=\().+?(?=\))/", $fieldType, $tblFieldLength);
        $tableFieldLength = $tblFieldLength[0];

        if (in_array($tableFieldType, ['int', 'bigint', 'numeric']) && !is_numeric($prefix)) {
            throw new Exception("table field type is $tableFieldType but prefix is string");
        }

        if ($length > $tableFieldLength) {
            throw new Exception('ID length is bigger then field length');
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
        $total = DB::select($totalQuery);

        if ($total[0]->total) {
            if ($resetOnPrefixChange) {
                $maxQuery = sprintf("SELECT MAX(%s) maxId from %s WHERE %s like %s", $field, $table, $field, "'" . $prefix . "%'");
            } else {
                $maxQuery = sprintf("SELECT MAX(%s) maxId from %s", $field, $table);
            }

            $queryResult = DB::select($maxQuery);
            $maxFullId = $queryResult[0]->maxId;

            $maxId = substr($maxFullId, $prefixLength, $idLength);
            return $prefix . str_pad($maxId + 1, $idLength, '0', STR_PAD_LEFT);

        } else {
            return $prefix . str_pad(1, $idLength, '0', STR_PAD_LEFT);
        }
    }
}