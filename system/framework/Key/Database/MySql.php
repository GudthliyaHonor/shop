<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Database;


use PDO;
use InvalidArgumentException;
use PDOException;
use Exception;
use Key\Abstracts\Database;
use Key\Exception\DatabaseException;

/**
 * Class MySql
 * @package Key\Database
 */
class MySql extends Database
{
    /**
     * Get Current PDO type, such as sqlite, mysql, pgsql, sqlite2, oci.
     *
     * @return string
     * @throw DatabaseException If the driver is not installed.
     */
    public function getPDOType()
    {
        return 'mysql';
    }

    /**
     * Get Quote character.
     *
     * @return string
     */
    protected function getQuoteIdentifierSymbol()
    {
        return '`';
    }

    /**
     * A key=>value array of driver-specific connection options.
     *
     * @return array
     */
    protected function getDriverOptions()
    {
        $options = parent::getDriverOptions();

        $initCommand = "SET NAMES '{$this->charset}'";
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $initCommand;

        return $options;
    }

    /**
     * List the tables of the database.
     *
     * @return mixed
     */
    public function listTables()
    {
        return $this->fetchCol('SHOW TABLES');
    }

    /**
     * Get the column descriptions for a table.
     *
     * The return value is an associative array keyed by the column name,
     * as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string;
     * COLUMN_NAME      => string; column name
     * COLUMN_POSITION  => number; ordinal position of column in table
     * DATA_TYPE        => string; SQL datatype name of column
     * DEFAULT          => string; default expression of column, null if none
     * NULLABLE         => boolean; true if column can have nulls
     * LENGTH           => number; length of CHAR/VARCHAR
     * SCALE            => number; scale of NUMERIC/DECIMAL
     * PRECISION        => number; precision of NUMERIC/DECIMAL
     * UNSIGNED         => boolean; unsigned property of an integer type
     * PRIMARY          => boolean; true if column is part of the primary key
     * PRIMARY_POSITION => integer; position of column in primary key
     * IDENTITY         => integer; true if column is auto-generated with unique values
     *
     * @param string $tableName Table name.
     * @param string $schemaName Column name.
     * @return mixed
     */
    public function describeTable($tableName, $schemaName)
    {
        // @todo  use INFORMATION_SCHEMA someday when MySQL's
        // implementation has reasonably good performance and
        // the version with this improvement is in wide use.

        if ($schemaName) {
            $sql = 'DESCRIBE ' . $this->quoteIdentifier("$schemaName.$tableName", true);
        } else {
            $sql = 'DESCRIBE ' . $this->quoteIdentifier($tableName, true);
        }
        $stmt = $this->query($sql);

        // Use FETCH_NUM so we are not dependent on the CASE attribute of the PDO connection
        $result = $stmt->fetchAll(PDO::FETCH_NUM);

        $field   = 0;
        $type    = 1;
        $null    = 2;
        $key     = 3;
        $default = 4;
        $extra   = 5;

        $desc = array();
        $i = 1;
        $p = 1;
        foreach ($result as $row) {
            list($length, $scale, $precision, $unsigned, $primary, $primaryPosition, $identity)
                = array(null, null, null, null, false, null, false);
            if (preg_match('/unsigned/', $row[$type])) {
                $unsigned = true;
            }
            if (preg_match('/^((?:var)?char)\((\d+)\)/', $row[$type], $matches)) {
                $row[$type] = $matches[1];
                $length = $matches[2];
            } else if (preg_match('/^decimal\((\d+),(\d+)\)/', $row[$type], $matches)) {
                $row[$type] = 'decimal';
                $precision = $matches[1];
                $scale = $matches[2];
            } else if (preg_match('/^float\((\d+),(\d+)\)/', $row[$type], $matches)) {
                $row[$type] = 'float';
                $precision = $matches[1];
                $scale = $matches[2];
            } else if (preg_match('/^((?:big|medium|small|tiny)?int)\((\d+)\)/', $row[$type], $matches)) {
                $row[$type] = $matches[1];
                // The optional argument of a MySQL int type is not precision
                // or length; it is only a hint for display width.
            }
            if (strtoupper($row[$key]) == 'PRI') {
                $primary = true;
                $primaryPosition = $p;
                if ($row[$extra] == 'auto_increment') {
                    $identity = true;
                } else {
                    $identity = false;
                }
                ++$p;
            }
            $desc[$this->foldCase($row[$field])] = array(
                'SCHEMA_NAME'      => null, // @todo
                'TABLE_NAME'       => $this->foldCase($tableName),
                'COLUMN_NAME'      => $this->foldCase($row[$field]),
                'COLUMN_POSITION'  => $i,
                'DATA_TYPE'        => $row[$type],
                'DEFAULT'          => $row[$default],
                'NULLABLE'         => (bool) ($row[$null] == 'YES'),
                'LENGTH'           => $length,
                'SCALE'            => $scale,
                'PRECISION'        => $precision,
                'UNSIGNED'         => $unsigned,
                'PRIMARY'          => $primary,
                'PRIMARY_POSITION' => $primaryPosition,
                'IDENTITY'         => $identity
            );
            ++$i;
        }
        return $desc;
    }

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $sql The valid SQL statement.
     * @param int $count The items number per page.
     * @param int $offset The offset for pagination.
     * @return string
     * @throws InvalidArgumentException
     */
    public function limit($sql, $count, $offset = 0)
    {
        if (!is_string($sql) || (trim($sql) == '')) {
            throw new InvalidArgumentException('Invalid sql argument.');
        }

        if (!is_int($count) || $count <= 0)
        {
            throw new InvalidArgumentException('Invalid count argument.');
        }

        if (!is_int($offset) || $offset < 0) {
            throw new InvalidArgumentException('Invalid offset argument.');
        }

        $sql .= ' LIMIT '.$count;
        if ($offset) {
            $sql .= ' OFFSET '.$offset;
        }

        return $sql;
    }

    /**
     * Add ORDER statement to the sql statement.
     *
     * @param string $sql A valid SQL statement.
     * @param string $sortField The field be sorted.
     * @param string $direction The sort direction.
     * @return string
     */
    public function order($sql, $sortField, $direction)
    {
        $sortField = trim($sortField);
        $direction = strtoupper(trim($direction));

        if (empty($sortField)) {
            return $sql;
        }

        switch ($direction) {
            case 'ASC':
            case 'DESC':
                break;
            default:
                $direction = 'ASC';
        }

        $sql .= ' ORDER BY '.str_replace(';', '', addslashes($sortField)).' '.$direction;

        return $sql;
    }

    /**
     * Batch Insert data.
     *
     * @param string $table Table name.
     * @param array $data Inserting data, such as:
     * <code>
     * array(array('col1' => 1, 'col2' => 2), array('col1'=>1, 'col2'=>4))
     * </code>
     * @param bool|false $ext_set Extension data, such as array('col' => 'column_name1', 'val' => 'value 1').
     * @return bool
     * @throws DatabaseException
     */
    public function batchInsert($table, $data)
    {
        if (!is_string($table) || ($table = trim($table)) == '') {
            throw new InvalidArgumentException('Invalid table name.');
        }
        if (!$data || !is_array($data) || count($data) < 0) return false;

        $columns = array_keys($data[0]);

        try {
            $this->beginTransaction();

            foreach ($data as $idx => $item) {
                $cols = array();
                $vals = array_values($item);
                $holders = array();

                foreach($item as $col => $val) {
                    $cols[] = $this->quoteIdentifier($col);
                    $holders[] = '?';
                }

                $sql = 'INSERT INTO '. $this->quoteIdentifier($table). '(' . implode(', ', $cols) . ') VALUES (' . implode(', ', $holders) . ')';
                $stmt = $this->prepare($sql);

                // Execute statement to add to transaction
                $stmt->execute($vals);

                // Clear statement for next record (not necessary, but good practice)
                $stmt = null;
            }

            // Commit all inserts
            $this->commit();
            return true;
        } catch(PDOException $ex) {
            $this->rollBack();
            throw new DatabaseException($ex->getMessage());
        } catch (Exception $ex) {
            $this->rollBack();
            throw new DatabaseException($ex->getMessage(), $ex->getCode());
        }

        return false;
    }

}