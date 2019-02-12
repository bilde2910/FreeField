<?php
/*
    This library file contains functions relating to database connections.

    There are three tables in use:

    [ffield_]user
        The table containing a list of all registered users. This table has the
        following columns:

        VARCHAR(64) ìd` PRIMARY
            Contains the ID and authentication provider of each registered user.
            Example: "discord:123456789012345678"

        VARCHAR(64) `provider_id`
            Contains the provider identity of the given user. The provider
            identity is a human-readable string identifying the user by their
            username or other identity on the authentication provider they
            choose to use. Example: "FooBarBaz#1234" or "@FooBarBaz". This
            property is transient and should never be used to uniquely identify
            any particular account in code - use the `id` field instead.

        VARCHAR(64) `nick`
            Contains the nickname used by this user on FreeField. Example:
            "FooBarBaz"

        CHAR(32) `token`
            A unique authentication token generated for each user that is stored
            in the session cookie data for each device the user is logged in to.
            A session is only valid if the token stored in the database matches
            the one stored in the session cookie. This allows an easy means for
            users to invalidate all of their sessions on all devices - simply
            request a new token to be created and saved in the database.
            Existing sessions will no longer have a matching token, hence the
            sessions are automatically invalidated.

        TINYINT(1) `approved`
            A boolean flag on whether or not the account is considered approved,
            if approval of new user accounts is required. If approval is not
            required, new users are considered already approved on account
            creation, and will have this value pre-set to 1.

        SMALLINT `permission`
            A number indicating the permission level of the current user. This
            level number will correspond to a group, but may also not correspond
            to a group if the group in question has been deleted. The value is
            used to calculate whether or not the user has permission to do
            various tasks on FreeField. Example: 80

        TIMESTAMP `user_signup`
            A timestamp indicating when the user's account was first seen on
            this instance of FreeField. There is no record stored for the last
            time the user was active or signed in, since sessions may last for
            years and updating the database every time someone was active for
            any reason on the site would be a lot of wasted `UPDATE` queries for
            little additional benefit for local FreeField administrators.

    [ffield_]group
        The table used to store a list of all groups currently active on
        FreeField. Groups are used to categorize users into different permission
        tiers. This table has the following columns:

        INT `group_id` PRIMARY AUTO_INCREMENT
            A unique numeric ID identifying any particular group. Automatically
            incremented by the database. Used when making changes to groups. For
            all other purposes, `level` is used to identify each group.

        SMALLINT `level` UNIQUE
            The permission level for this group. The permission level is unique
            for every group. It is used to identify the group in other places
            (such as the users table and in the configuration) and is used to
            determine whether the users within the group have permission to do
            various tasks on FreeField. Example: 80

        VARCHAR(64) `label`
            The name of the group. It may contain an I18N token - please see
            comments for `Auth::resolvePermissionLabelI18N()` in
            /includes/lib/auth.php for more information on how those work.
            Example: "Administrator" or "{i18n:group.level.admin}"

        CHAR(6) `color`
            The hexadecimal RGB color code that represents the color used for
            this group and its members. Can be `NULL` if no color is set.

    [ffield_]poi
        The table used to store a list of all POIs and their research tasks. The
        table has the following columns:

        INT `id` PRIMARY AUTO_INCREMENT
            A unique numeric ID identifying any particular POI. Automatically
            incremented by the database. Used when making all kinds of changes
            to the POI and when reporting research to the POI.

        VARCHAR(128) `name`
            The name of the POI. Example: "John Doe Statue"

        DOUBLE `latitude`
            A positive or negative latitude coordinate indicating this POI's
            North-South location. Example: 42.63445

        DOUBLE `longitude`
            A positive or negative longitude coordinate indicating this POI's
            East-West location. Combined with `latitude`, these fields make up
            the location of the POI. Example: -87.12012

        TIMESTAMP `created_on`
            The date and time this POI was added to FreeField.

        VARCHAR(64) `created_by`
            The ID user who added this POI to FreeField. Example:
            "discord:123456789012345678"

        TIMESTAMP `last_updated`
            The date and time of when someone last reported research to this
            POI. This is not ON UPDATE CURRENT_TIMESTAMP because editing other
            properties of the POI, such as its name, would also result in a
            timestamp update.

        VARCHAR(64) `updated_by`
            The ID user who last reported research on this POI. Example:
            "discord:123456789012345678"

        VARCHAR(32) `objective`
            The objective last reported on the POI. Example: "catch_type"

        VARCHAR(128) `obj_params`
            Parameters for the last reported objective in JSON format. Example:
            "{"species":[90],"quantity":2}"

        VARCHAR(32) `reward`
            The reward last reported on the POI. Example: "potion"

        VARCHAR(128) `rew_params`
            Parameters for the last reported objective in JSON format. Example:
            "{"quantity":4}"
*/

__require("config");

class Database {

    // Holds PDO connection object.
    private $pdo;

    // Action constants for use in `execute()`.
    const ACTION_INSERT = 1;
    const ACTION_UPDATE = 2;
    const ACTION_DELETE = 3;
    const ACTION_INSERT_MANY = 4;

    // Current action for use in `execute()`.
    private $action = null;

    // Data provided by calling functions in this class.
    private $table = null;
    private $select = null;
    private $join = array();
    private $where = array();
    private $data = null;
    private $order = null;
    private $limit = null;

    private function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /*
        This function lists all available implemented drivers in the current
        execution environment.
    */
    public static function getAvailableDrivers() {
        $drivers = PDO::getAvailableDrivers();
        $implTypes = array();

        // MySQL - tested, should be stable
        if (in_array("mysql", $drivers)) {
            $implTypes[] = "mysql";
        }
        // PostgreSQL - not tested, experimental
        if (in_array("pgsql", $drivers)) {
            $implTypes[] = "pgsql";
        }
        // SQLite 2 and 3 - not tested, experimental
        if (in_array("sqlite", $drivers)) {
            $implTypes[] = "sqlite2";
            $implTypes[] = "sqlite";
        }
        // Microsoft SQL Server and SQL Azure - not tested, experimental
        if (in_array("sqlsrv", $drivers)) {
            $implTypes[] = "sqlsrv";
        }
        // 4D - not tested, highly experimental
        if (in_array("4d", $drivers)) {
            $implTypes[] = "4D";
        }
        // Oracle Instant Client - not tested, highly experimental
        if (in_array("oci", $drivers)) {
            $implTypes[] = "oci";
        }
        // Cubrid - not tested, highly experimental
        if (in_array("cubrid", $drivers)) {
            $implTypes[] = "cubrid";
        }
        // DBLIB drivers - not tested, highly experimental
        if (in_array("dblib", $drivers)) {
            $implTypes[] = "sybase";
            $implTypes[] = "mssql";
            $implTypes[] = "dblib";
        }

        return $implTypes;
    }

    /*
        Returns an initialized PDO instance.
    */
    public static function connect() {
        $type = Config::get("database/type")->value();
        $host = Config::get("database/host")->value();
        $port = Config::get("database/port")->value();
        $database = Config::get("database/database")->value();
        $user = Config::get("database/username")->value();
        $pass = Config::get("database/password")->value();

        $dsnOptions =  array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        );

        // SQL queries to run once a connection is established.
        $runOnConnect = array();
        // An array of DSN options.
        $dsnArr = array();
        // The DSN string used to connect with PDO.
        $dsnStr = null;

        /*
            Build the DSN string.
        */
        switch ($type) {
            case "mysql":
                $dsnArr["host"] = $host;
                if ($port != -1) $dsnArr["port"] = $port;
                $dsnArr["dbname"] = $database;
                $dsnArr["charset"] = "utf8mb4";
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            case "pgsql":
                $dsnArr["host"] = $host;
                if ($port != -1) $dsnArr["port"] = $port;
                $dsnArr["dbname"] = $database;
                $runOnConnect[] = "SET CLIENT_ENCODING TO 'UTF8';";
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            case "sqlite":
            case "sqlite2":
                $dsnStr = "{$type}:{$database}";

            case "sqlsrv":
                if ($port != -1) {
                    $dsnArr["Server"] = "$host,$port";
                } else {
                    $dsnArr["Server"] = $host;
                }
                $dsnArr["Database"] = $database;
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            case "4D":
                $dsnArr["host"] = $host;
                if ($port != -1) $dsnArr["port"] = $port;
                $dsnArr["dbname"] = $database;
                $dsnArr["charset"] = "UTF-8";
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            case "oci":
                if ($port != -1) {
                    $dsnArr["dbname"] = "//{$host}:{$port}/{$database}";
                } else {
                    $dsnArr["dbname"] = "//{$host}/{$database}";
                }
                $dsnArr["charset"] = "UTF-8";
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            case "cubrid":
                $dsnArr["host"] = $host;
                if ($port != -1) $dsnArr["port"] = $port;
                $dsnArr["dbname"] = $database;
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            case "sybase":
            case "mssql":
            case "dblib":
                $dsnArr["host"] = $host;
                $dsnArr["dbname"] = $database;
                $dsnArr["charset"] = "UTF-8";
                $dsnStr = self::buildDSN($type, $dsnArr);
                break;

            default:
                throw new Exception("Unsupported database type!");
                exit;
        }

        /*
            Connect to the database.
        */
        $pdo = new PDO($dsnStr, $user, $pass, $dsnOptions);

        /*
            Execute commands queued for connection.
        */
        foreach ($runOnConnect as $sql) {
            $pdo->execute($sql);
        }

        return new Database($pdo);
    }

    /*
        This function takes an associative array of DSN parameters and converts
        them into a DSN connection string.
    */
    private static function buildDSN($type, $dsnArr) {
        $dsnStr = "{$type}:";
        foreach ($dsnArr as $key => $value) {
            $dsnStr .= "{$key}={$value};";
        }
        // Remove trailing semicolon
        return substr($dsnStr, 0, -1);
    }

    /*
        Select from table. The global table prefix is prepended to the given
        table name.

        $table
            Table name, without prefix.
    */
    public function from($table) {
        $this->table = self::getTable($table);
        return $this;
    }

    /*
        Select the following columns from the table.

        $columns
            An array of column names.
    */
    public function select($columns) {
        $this->select = $columns;
        return $this;
    }

    /*
        LEFT JOINs another table on this query.

        $table
            The other table to join.

        $on
            An associative array describing the connection between colums in the
            origin table and the joined table. The global table prefix is
            prepended to the given table names. This can be suppressed by
            prepending `~` to the table name.

            Examples:

            $table = "group";
            $on = array("group.level" => "user.permission");

                LEFT JOIN ffield_group
                ON ffield_group.level = ffield_user.permission

            $table = "group";
            $on = array("group.level" => "~user.permission");

                LEFT JOIN ffield_group
                ON ffield_group.level = user.permission
    */
    public function leftJoin($table, $on) {
        $prefixedOn = array();
        foreach ($on as $where => $match) {
            $prefixedOn[] = self::getTable($where)." = ".self::getTable($match);
        }
        $this->join[] = array(
            "type" => "LEFT",
            "table" => self::getTable($table),
            "on" => $prefixedOn
        );
        return $this;
    }

    /*
        Limits affected or selected rows to rows matching the given equality.

        Option 1:
            $key
                The name of the column.
            $value
                The value that the row must match for the given column.

        Option 2:
            $key
                An associative array of `$key => $value` pairs as defined in
                option 1.
    */
    public function where($key, $value = null) {
        if (is_array($key)) {
            $this->where = array_merge($this->where, $key);
        } else {
            $this->where[$key] = $value;
        }
        return $this;
    }

    /*
        Orders the returned results by the given column and sorting order.

        $by
            A column and sorting order, e.g. "id DESC".
    */
    public function order($by) {
        $this->order = $by;
        return $this;
    }

    /*
        Limits the returned results to the given number of rows.

        $limit
            The maximum number of rows to return.
    */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    /*
        Executes a SELECT query with any given arguments and returns the value
        of the given column for exactly one result from matching rows in the
        returned list of results.
    */
    public function value($column) {
        $data = $this->select(array($column))->one();
        if ($data === null) return null;
        return $data[$column];
    }

    /*
        Executes a SELECT query with any given arguments and returns exactly one
        result. Returns `null` if no rows were found.
    */
    public function one() {
        $data = $this->limit(1)->many();
        if (is_array($data) && count($data) >= 1) return $data[0];
        return null;
    }

    /*
        Executes a SELECT query with any given arguments.
    */
    public function many() {
        $table = $this->table;
        $columns = "*";
        if ($this->select !== null) {
            $columns = implode(", ", $this->select);
        }
        $sql = "SELECT {$columns} FROM {$table}";
        $values = null;
        /*
            Add any JOIN clauses.
        */
        foreach ($this->join as $join) {
            $type = $join["type"];
            $table = $join["table"];
            $on = implode(" AND ", $join["on"]);
            $sql .= " {$type} JOIN {$table} ON {$on}";
        }
        /*
            Append WHERE, if applicable.
        */
        if (count($this->where) > 0) {
            $where = self::buildPrepareString(" AND ", array_keys($this->where));
            $sql .= " WHERE {$where}";
            $values = array_values($this->where);
        }
        /*
            Append ORDER BY, if applicable.
        */
        if ($this->order !== null) {
            $order = $this->order;
            $sql .= " ORDER BY {$order}";
        }
        /*
            Append LIMIT, if applicable.
        */
        if ($this->limit !== null) {
            $limit = $this->limit;
            $sql .= " LIMIT {$limit}";
        }
        /*
            Execute the query and fetch results.
        */
        $data = null;
        if ($values === null) {
            $stmt = $this->pdo->query($sql);
            $data = $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $data = $stmt->fetchAll();
        }
        /*
            Clear all arguments and clauses for this query.
        */
        $this->clear();
        return $data;
    }

    /*
        Indicates that `execute()` should INSERT data.

        $data
            The data to insert.
    */
    public function insert($data) {
        $this->data = $data;
        $this->action = self::ACTION_INSERT;
        return $this;
    }

    /*
        Indicates that `execute()` should INSERT multiple rows of data.

        $data
            An array of rows of data to insert.
    */
    public function insertMany($data) {
        $this->data = $data;
        $this->action = self::ACTION_INSERT_MANY;
        return $this;
    }

    /*
        Indicates that `execute()` should UPDATE data.

        $data
            The data to update.
    */
    public function update($data) {
        $this->data = $data;
        $this->action = self::ACTION_UPDATE;
        return $this;
    }

    /*
        Indicates that `execute()` should DELETE all rows matching WHERE clauses
        provided through `where()`.
    */
    public function delete() {
        $this->action = self::ACTION_DELETE;
        return $this;
    }

    /*
        Executes the built SQL query.

        $sql
            An optional SQL query. If given, executes the provided query, then
            returns.
    */
    public function execute($sql = null) {
        if ($sql !== null) {
            $this->pdo->exec($sql);
            return;
        }
        switch ($this->action) {
            case self::ACTION_UPDATE:
                $table = $this->table;
                $set = self::buildPrepareString(", ", array_keys($this->data));

                $sql = "UPDATE {$table} SET {$set}";
                $values = array_values($this->data);
                // Append WHERE clause, if necessary.
                if (count($this->where) > 0) {
                    $where = self::buildPrepareString(" AND ", array_keys($this->where));
                    $sql .= " WHERE {$where}";
                    $values = array_merge($values, array_values($this->where));
                }
                $this->pdo->prepare($sql)->execute($values);
                break;

            case self::ACTION_INSERT:
                $table = $this->table;
                $columns = implode(", ", array_keys($this->data));
                // Get a placeholder string (e.g. "?,?,?").
                $placeholders = self::chainPlaceholders(count($this->data));

                $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
                $values = array_values($this->data);
                $this->pdo->prepare($sql)->execute($values);
                break;

            case self::ACTION_INSERT_MANY:
                $table = $this->table;
                $rowCount = count($this->data);
                if ($rowCount < 1) break;
                $columns = implode(", ", array_keys($this->data[0]));
                $colCount = count($this->data[0]);

                // Create an insert string with many named placeholders. (Non-
                // named placeholders will not work!) Using ":ph<n>" for place-
                // holders is fine.
                $phArray = array();
                $phNo = 0;
                for ($i = 0; $i < $rowCount; $i++) {
                    $placeholders = array();
                    for ($j = 0; $j < $colCount; $j++) $placeholders[] = ":ph".($phNo++);
                    $phArray[] = "(".implode(",", $placeholders).")";
                }
                $phString = implode(",", $phArray);

                // Add all values to a single array, and set invalid if not all
                // rows have same number of columns.
                $valid = true;
                $values = array();
                $phNo = 0;
                foreach ($this->data as $row) {
                    if (count($row) !== $colCount) $valid = false;
                    foreach ($row as $value) $values[":ph".($phNo++)] = $value;
                }
                if (!$valid) break;

                $sql = "INSERT INTO {$table} ({$columns}) VALUES {$phString}";
                $this->pdo->prepare($sql)->execute($values);
                break;

            case self::ACTION_DELETE:
                $table = $this->table;
                $where = self::buildPrepareString(" AND ", array_keys($this->where));

                $sql = "DELETE FROM {$table} WHERE {$where}";
                $values = array_values($this->where);
                $this->pdo->prepare($sql)->execute($values);
                break;
        }

        /*
            Clear all arguments and clauses for this query.
        */
        $this->clear();
    }

    /*
        This function resets the values of all caller-provided data in this
        instance of `Database`.
    */
    private function clear() {
        $this->action = null;
        $this->select = null;
        $this->join = array();
        $this->where = array();
        $this->data = null;
        $this->order = null;
        $this->limit = null;
    }

    /*
        Starts a transaction (disables autocommit). End the transaction using
        `commit()`.
    */
    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }

    /*
        Commits all queries since `beginTransaction()` and resumes autocommit.
    */
    public function commit() {
        $this->pdo->commit();
    }

    /*
        Returns the full name of the given table (prefix + table name) for use
        when querying specific tables in the database.
    */
    public static function getTable($table) {
        if (substr($table, 0, 1) == "~") return substr($table, 1);
        return Config::get("database/table-prefix")->value().$table;
    }

    /*
        Builds a prepared statements string for the given list of fields using
        the given glue.

        $glue
            The glue to bind the prepared column clauses.

        $fields
            The columns to prepare data for.

        Example:
            $o = buildPrepareString(", ", array(
                "name", "latitude", "longitude"
            ));
            $o == "name=?, latitude=?, longitude=?";
    */
    private static function buildPrepareString($glue, $fields) {
        for ($i = 0; $i < count($fields); $i++) {
            $fields[$i] .= "=?";
        }
        return implode($glue, $fields);
    }

    /*
        Chains a list of comma-separated placeholders, by the given quantity.

        $qty
            The quantity of prepared placeholders.

        Example:
            $o = chainPlaceholders(5);
            $o == "?,?,?,?,?";
    */
    private static function chainPlaceholders($qty) {
        $str = "";
        for ($i = 0; $i < $qty; $i++) {
            $str .= "?,";
        }
        return substr($str, 0, -1);
    }
}

?>
