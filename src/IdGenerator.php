<?php

namespace Haruncpi\LaravelIdGenerator;

use Illuminate\Support\Facades\DB, Exception;

class IdGenerator
{

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

        $field = array_key_exists('field', $configArr) ? $configArr['field'] : 'id';
        $prefixLength = strlen($configArr['prefix']);
        $idLength = $configArr['length'] - $prefixLength;
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
            $maxQuery = sprintf("SELECT MAX(SUBSTR(%s,%s,%s)) maxId FROM %s %s",
                        $field, ($prefixLength + 1), $idLength, $configArr['table'], $whereString);
            $maxId = DB::select($maxQuery);
            $maxId = $maxId[0]->maxId + 1;

            return $configArr['prefix'] . str_pad($maxId, $idLength, '0', STR_PAD_LEFT);
        } else {
            return $configArr['prefix'] . str_pad(1, $idLength, '0', STR_PAD_LEFT);
        }
    }
}