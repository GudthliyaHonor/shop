<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Abstracts;


use PDO;
use Exception;
use InvalidArgumentException;
use PDOStatement;
use Key\Exception\DatabaseException;

/**
 * This class provides the abstract methods for Database operations.
 *
 * @package Key\Abstracts
 * @author Guanghui Li <liguanghui2006@163.com>
 */
abstract class Database
{

    /**
     * Host name or ip of database server.
     *
     * @var string
     */
    protected $host;

    /**
     * The port of database server.
     *
     * @var int
     */
    protected $port = 3306;

    /**
     * Database name.
     *
     * @var string
     */
    protected $dbname;

    /**
     * User name for DSN.
     *
     * @var string
     */
    protected $username;

    /**
     * Password for DSN.
     *
     * @var string
     */
    protected $password;

    /**
     * Charset of connection to the database server.
     *
     * @var string
     */
    protected $charset = 'utf8';

    /**
     * Driver options for connection.
     *
     * @var array
     */
    protected $driverOptions = array();

    /**
     * Transaction count.
     *
     * @var int
     */
    protected $transactionCount = 0;

    /**
     * Fetch mode.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_ASSOC;

    protected $caseFolding = PDO::CASE_NATURAL;

    /**
     * PDO instance.
     *
     * @var PDO
     */
    protected $connection = null;

    /**
     * Connection construct.
     *
     * @param string $host Database server host name or ip address.
     * @param string $dbname Database name.
     * @param string $username User name for database server.
     * @param string $password Password for database server.
     * @param string $charset Charset for connection.
     * @param int $port Database server port.
     * @throws DatabaseException
     * @throws InvalidArgumentException
     */
    public function __construct($host, $dbname, $username, $password, $charset = 'utf8', $port = 3306)
    {
        $type = $this->getPDOType();

        if (!in_array($type, PDO::getAvailableDrivers())) {
            throw new DatabaseException('The driver should be installed: ' . $type);
        }

        if (!is_string($host) || (trim($host)) == '') {
            throw new InvalidArgumentException('Invalid host');
        }

        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->charset = $charset;
    }

    /**
     * Set fetch mode.
     *
     * @param int $mode Fetch mode.
     * @throws DatabaseException
     */
    public function setFetchMode($mode)
    {
        if (!extension_loaded('pdo')) {
            throw new DatabaseException('The PDO extension is required for this adapter but the extension is not loaded');
        }

        switch ($mode) {
            case PDO::FETCH_LAZY:
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_NAMED:
            case PDO::FETCH_OBJ:
                $this->fetchMode = $mode;
                break;
            default:
                throw new DatabaseException('Invalid fetch mode '.$mode.' specified');
        }
    }

    /**
     * Set case folding setting.
     *
     * @param int $caseFolding
     */
    public function setCaseFolding($caseFolding)
    {
        switch ($caseFolding) {
            case PDO::CASE_NATURAL:
            case PDO::CASE_UPPER:
            case PDO::CASE_LOWER:
                $this->caseFolding = $caseFolding;
                break;
            default:
                throw new InvalidArgumentException('Invalid case folding value.');
        }
    }

    /**
     * Change the key case upper or lower by case folding setting.
     *
     * @param $key
     * @return string
     */
    public function foldCase($key)
    {
        switch ($this->caseFolding) {
            case PDO::CASE_LOWER:
                $key = strtolower((string) $key);
                break;
            case PDO::CASE_UPPER:
                $key = strtoupper((string) $key);
                break;
            case PDO::CASE_NATURAL:
            default:
                $key = (string) $key;
        }

        return $key;
    }

    /**
     * Get Current PDO type, such as sqlite, mysql, pgsql, sqlite2, oci.
     *
     * @return string
     * @throw DatabaseException If the driver is not installed.
     */
    abstract public function getPDOType();

    /**
     * Get database DSN.
     *
     * @return string
     */
    protected function getDSN()
    {
        return $this->getPDOType() . ':host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbname;
    }

    /**
     * The user name for the DSN string. This parameter is optional for some PDO drivers.
     *
     * @return string
     */
    protected function getUsername()
    {
        return $this->username;
    }

    /**
     * The password for the DSN string. This parameter is optional for some PDO drivers.
     *
     * @return string
     */
    protected function getPassword()
    {
        return $this->password;
    }

    /**
     * A key=>value array of driver-specific connection options.
     *
     * @return array
     */
    protected function getDriverOptions()
    {
        if (!is_array($this->driverOptions)) {
            $this->driverOptions = array();
        }

        return $this->driverOptions;
    }

    /**
     * Creates a PDO instance to represent a connection to the requested database.
     * @see http://www.php.net/pdo.construct
     *
     * @return PDO A PDO object.
     * @throws DatabaseException If the attempt to connect to the requested database fails.
     */
    protected function connect()
    {
        if (!$this->connection) {
            try {
                $this->connection = new PDO(
                    $this->getDSN(),
                    $this->getUsername(),
                    $this->getPassword(),
                    $this->getDriverOptions()
                );

                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch(Exception $ex) {
                throw new DatabaseException($ex->getMessage(), $ex->getCode());
            }
        }

        return $this->connection;
    }

    /**
     * Reconnect the server.
     */
    public function reconnect()
    {
        $this->connection = null;
        $this->connect();
    }

    /**
     * Quote the field.
     *
     * @param string $field
     * @return string
     */
    protected function quoteIdentifier($field)
    {
        $q = $this->getQuoteIdentifierSymbol();
        return ($q . str_replace("$q", "$q$q", $field) . $q);
    }

    /**
     * Get Quote character.
     *
     * @return string
     */
    protected function getQuoteIdentifierSymbol()
    {
        return '"';
    }

    /**
     * Get the database connection instance.
     *
     * @return PDO A PDO object.
     * @throws DatabaseException
     */
    public function getConnection()
    {
        return $this->connect();
    }

    /**
     * Check if database connected.
     *
     * @return bool
     */
    public function isConnected()
    {
        return (bool)($this->getConnection() instanceof PDO);
    }

    /**
     * @param $sql
     * @return int
     * @throws DatabaseException
     */
    public function exec($sql)
    {
        $affected = $this->getConnection()->exec($sql);
        if ($affected === false) {
            $errorInfo = $this->getConnection()->errorInfo();
            throw new DatabaseException($errorInfo[2]);
        }

        return $affected;
    }

    /**
     * Initiates a transaction.
     *
     * @return bool
     * @throws DatabaseException
     */
    public function beginTransaction()
    {
        if (!$this->transactionCount++) {
            return $this->getConnection()->beginTransaction();
        }

        $this->exec('SAVEPOINT trans'.$this->transactionCount);

        return $this->transactionCount >= 0;
    }

    /**
     * Commits a transaction.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commit()
    {
        if (!--$this->transactionCount) {
            return $this->getConnection()->commit();
        }

        return $this->transactionCount >= 0;
    }

    /**
     * Rolls back a transaction.
     *
     * @return bool TRUE on success or FALSE on failure.
     * @throws DatabaseException
     */
    public function rollback()
    {
        if (--$this->transactionCount) {
            $this->exec('ROLLBACK TO trans'.($this->transactionCount + 1));
            return true;
        }

        return $this->getConnection()->rollBack();
    }

    /**
     * Prepares a statement for execution and returns a statement object.
     *
     * @param string $sql
     * @return PDOStatement
     */
    public function prepare($sql)
    {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->setFetchMode($this->fetchMode);

        return $stmt;
    }

    /**
     * The SQL statement to prepare and execute.
     * Data inside the query should be properly escaped.
     *
     * @param string $sql Valid SQL string.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @return PDOStatement A PDOStatement object, or FALSE on failure.
     */
    public function query($sql, $bind = array())
    {
        if (!is_array($bind)) {
            $bind = array($bind);
        }

        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($bind);
        $stmt->setFetchMode($this->fetchMode);

        return $stmt;
    }

    /**
     * Insert a row data to the database table.
     *
     * @param string $table Table name
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @return bool|int int the number of rows, or false on failure.
     */
    public function insert($table, $bind = array())
    {
        $cols = array();
        $holders = array();
        foreach ($bind as $col => $val) {
            $cols[] = $this->quoteIdentifier($col);
            $holders[] = '?';
        }

        $sql = 'INSERT INTO '.$this->quoteIdentifier($table)
            .'('.implode(', ', $cols).')'
            .'VALUES('.implode(', ', $holders).')';
        $stmt = $this->query($sql, array_values($bind));
        $result = $stmt->rowCount();

        return $result;
    }

    /**
     * Update.
     *
     * @param string $table Table name.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @param string $where where statement, such as 'id = 1'.
     * @return bool|int the number of rows, or false on failure.
     * @throws InvalidArgumentException
     */
    public function update($table, $bind = array(), $where = '')
    {
        if (!is_string($table) || (trim($table)) == '') {
            throw new InvalidArgumentException('Invalid table name.');
        }
        if (!is_array($bind) || count($bind) == 0) {
            throw new InvalidArgumentException('Invalid bind data. The bind data should be a array and it should not empty.');
        }

        $set = array();
        foreach ($bind as $col => $val) {
            $set[] = $this->quoteIdentifier($col).' = ?';
        }
        $sql = 'UPDATE '.$this->quoteIdentifier($table)
            .' SET '.implode(', ', $set)
            .($where ? ' WHERE '.$where : '');
        $stmt = $this->query($sql, array_values($bind));
        $result = $stmt->rowCount();

        return $result;
    }

    /**
     * @param $table Table name.
     * @param string $where where statement, such as 'id = 1'.
     * @return int the number of rows, or false on failure.
     * @throws InvalidArgumentException
     */
    public function delete($table, $where = '')
    {
        if (!is_string($table) || (trim($table)) == '') {
            throw new InvalidArgumentException('Invalid table name.');
        }
        $sql = 'DELETE FROM '.$this->quoteIdentifier($table)
            .($where ? ' WHERE '.$where : '');
        $stmt = $this->query($sql);
        $result = $stmt->rowCount();

        return $result;
    }

    /**
     * Fetch the rows.
     *
     * @param string $sql This must be a valid SQL statement for the target database server.
     * @param array $bind
     * @param int $fetchMode Controls the contents of the returned array as documented in
     * <b>PDOStatement::fetch</b>.
     * Defaults to value of <b>PDO::ATTR_DEFAULT_FETCH_MODE</b>
     * (which defaults to <b>PDO::FETCH_BOTH</b>)
     * @return array
     */
    public function fetchAll($sql, $bind = array(), $fetchMode = PDO::FETCH_ASSOC)
    {
        if (is_null($fetchMode)) {
            $fetchMode = $this->fetchMode;
        }

        $stmt = $this->query($sql, $bind);
        $result = $stmt->fetchAll($fetchMode);

        return $result;
    }

    /**
     * Fetch the rows as <b>PDO::FETCH_ASSOC</b> mode.
     *
     * @param string $sql This must be a valid SQL statement for the target database server.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @return array
     */
    public function fetchAssoc($sql, $bind = array())
    {
        $stmt = $this->query($sql, $bind);

        $data = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tmp = array_values(array_slice($row, 0, 1));
            $data[$tmp[0]] = $row;
        }

        return $data;
    }

    /**
     * Fetch the first column value in the result data.
     *
     * @param string $sql This must be a valid SQL statement for the target database server.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @return string
     */
    public function fetchOne($sql, $bind = array())
    {
        $stmt = $this->query($sql, $bind);
        $result = $stmt->fetchColumn(0);

        return $result;
    }


    /**
     * Fetch the one row.
     *
     * @param string $sql This must be a valid SQL statement for the target database server.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @return array
     */
    public function fetchPairs($sql, $bind = array())
    {
        $stmt = $this->query($sql, $bind);

        $data = array();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $data[$row[0]] = $row[1];
        }

        return $data;
    }

    /**
     * Fetch the first column value, such as `name => 'abc'`.
     *
     * @param string $sql This must be a valid SQL statement for the target database server.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @return array
     */
    public function fetchCol($sql, $bind = array())
    {
        $stmt = $this->query($sql, $bind);
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        return $result;
    }

    /**
     * Fetches the next row from a result set.
     *
     * @param string $sql This must be a valid SQL statement for the target database server.
     * @param array $bind An array of values with as many elements as there are bound
     * parameters in the SQL statement being executed.
     * All values are treated as <b>PDO::PARAM_STR</b>.
     * @param int $fetchMode Fetch mode.
     * @return mixed
     */
    public function fetchRow($sql, $bind = array(), $fetchMode = PDO::FETCH_ASSOC)
    {
        if (is_null($fetchMode)) {
            $fetchMode = $this->fetchMode;
        }

        $stmt = $this->query($sql, $bind);
        $result = $stmt->fetch($fetchMode);

        return $result;
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string|null $table Name of the sequence object from which the ID should be returned.
     * @return string If a sequence name was not specified for the <i>name</i>
     * parameter, <b>PDO::lastInsertId</b> returns a
     * string representing the row ID of the last row that was inserted into
     * the database.
     * @throws DatabaseException
     */
    public function lastInsertId($table = null)
    {
        $connection = $this->connect();
        return $connection->lastInsertId($table);
    }

    /**
     * Close the connection?
     */
    public function closeConnection()
    {
        $this->connection = null;
    }

    /**
     * Get the version of database server.
     *
     * @return string|null The version of database server, or null on failure.
     */
    public function getServerVersion()
    {
        $connection = $this->getConnection();
        $version = $connection->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (preg_match('/((?:[0-9]{1,2}\.){1,3}[0-9]{1,2})/', $version, $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }

    /**
     * List the tables of the database.
     *
     * @return mixed
     */
    abstract public function listTables();

    /**
     * Get the column descriptions for a table.
     *
     * @param string $tableName Table name.
     * @param string $schemaName Column name.
     * @return mixed
     */
    abstract public function describeTable($tableName, $schemaName);

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param string $sql The valid SQL statement.
     * @param int $count The items number per page.
     * @param int $offset The offset for pagination.
     * @return string
     */
    abstract public function limit($sql, $count, $offset = 0);
}
