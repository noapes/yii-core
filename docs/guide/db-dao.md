Database Access Objects
=======================

Built on top of [PDO](http://www.php.net/manual/en/book.pdo.php), Yii DAO (Database Access Objects) provides an
object-oriented API for accessing relational databases. It is the foundation for other more advanced database
access methods, including [query builder](db-query-builder.md) and [active record](db-active-record.md).

When using Yii DAO, you mainly need to deal with plain SQLs and PHP arrays. As a result, it is the most efficient
way to access databases. However, because SQL syntax may vary for different databases, using Yii DAO also means
you have to take extra effort to create a database-agnostic application.

In Yii 2.0, DAO supports the following databases out of the box:

- [MySQL](http://www.mysql.com/)
- [MariaDB](https://mariadb.com/)
- [SQLite](http://sqlite.org/)
- [PostgreSQL](http://www.postgresql.org/): version 8.4 or higher
- [Oracle](http://www.oracle.com/us/products/database/overview/index.html)
- [MSSQL](https://www.microsoft.com/en-us/sqlserver/default.aspx): version 2008 or higher.

> Info: In Yii 3 and later, the DAO supports for CUBRID, Oracle and MSSQL are no longer provided as the built-in
  core components of the framework. They have to be installed as the separated [extensions](structure-extensions.md).
  There are [yiisoft/yii2-oracle](https://www.yiiframework.com/extension/yiisoft/yii2-oracle) and
  [yiisoft/yii2-mssql](https://www.yiiframework.com/extension/yiisoft/yii2-mssql) in the 
  [official extensions](https://www.yiiframework.com/extensions/official).

> Note: New version of pdo_oci for PHP 7 currently exists only as the source code. Follow
  [instruction provided by community](https://github.com/yiisoft/yii2/issues/10975#issuecomment-248479268)
  to compile it or use [PDO emulation layer](https://github.com/taq/pdooci).

## Creating DB Connections <span id="creating-db-connections"></span>

To access a database, you first need to connect to it by creating an instance of [[Yiisoft\Db\Connection]]:

```php
$db = new Yiisoft\Db\Connection([
    'dsn' => 'mysql:host=localhost;dbname=example',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
]);
```

Because a DB connection often needs to be accessed in different places, a common practice is to configure it
in terms of an [application component](structure-application-components.md) like the following:

```php
return [
    // ...
    'components' => [
        // ...
        'db' => [
            '__class' => \Yiisoft\Db\Connection::class,
            'dsn' => 'mysql:host=localhost;dbname=example',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ],
    ],
    // ...
];
```

You can then access the DB connection via the expression `Yii::$app->db`.

> Tip: You can configure multiple DB application components if your application needs to access multiple databases.

When configuring a DB connection, you should always specify its Data Source Name (DSN) via the [[Yiisoft\Db\Connection::dsn|dsn]]
property. The format of DSN varies for different databases. Please refer to the [PHP manual](http://www.php.net/manual/en/function.PDO-construct.php)
for more details. Below are some examples:

* MySQL, MariaDB: `mysql:host=localhost;dbname=mydatabase`
* SQLite: `sqlite:/path/to/database/file`
* PostgreSQL: `pgsql:host=localhost;port=5432;dbname=mydatabase`
* MS SQL Server (via sqlsrv driver): `sqlsrv:Server=localhost;Database=mydatabase`
* MS SQL Server (via dblib driver): `dblib:host=localhost;dbname=mydatabase`
* MS SQL Server (via mssql driver): `mssql:host=localhost;dbname=mydatabase`
* Oracle: `oci:dbname=//localhost:1521/mydatabase`

You can set the [[Yiisoft\Db\Connection::dsn|dsn]] via configuration array. For example,

```php
'db' => [
    '__class' => Yiisoft\Db\Connection::class,    
    'dsn' => [      
        'driver' => 'mysql',
        'host' => 'localhost',
        'dbname' => 'example',
    ],
    'username' => 'root',
    'password' => '',
],
```

Note that if you are connecting with a database via ODBC, you should configure the [[Yiisoft\Db\Connection::driverName]]
property so that Yii can know the actual database type. For example,

```php
'db' => [
    '__class' => Yiisoft\Db\Connection::class,
    'driverName' => 'mysql',
    'dsn' => 'odbc:Driver={MySQL};Server=localhost;Database=test',
    'username' => 'root',
    'password' => '',
],
```

Besides the [[Yiisoft\Db\Connection::dsn|dsn]] property, you often need to configure [[Yiisoft\Db\Connection::username|username]]
and [[Yiisoft\Db\Connection::password|password]]. Please refer to [[Yiisoft\Db\Connection]] for the full list of configurable properties.

> Info: When you create a DB connection instance, the actual connection to the database is not established until
  you execute the first SQL or you call the [[Yiisoft\Db\Connection::open()|open()]] method explicitly.

> Tip: Sometimes you may want to execute some queries right after the database connection is established to initialize
> some environment variables (e.g., to set the timezone or character set). You can do so by registering an event handler
> for the [[Yiisoft\Db\Connection::EVENT_AFTER_OPEN|afterOpen]] event
> of the database connection. You may register the handler directly in the application configuration like so:
>
> ```php
> 'db' => [
>     // ...
>     'on afterOpen' => function($event) {
>         // $event->sender refers to the DB connection
>         $event->sender->createCommand("SET time_zone = 'UTC'")->execute();
>     }
> ],
> ```


## Executing SQL Queries <span id="executing-sql-queries"></span>

Once you have a database connection instance, you can execute a SQL query by taking the following steps:

1. Create a [[Yiisoft\Db\Command]] with a plain SQL query;
2. Bind parameters (optional);
3. Call one of the SQL execution methods in [[Yiisoft\Db\Command]].

The following example shows various ways of fetching data from a database:

```php
// return a set of rows. each row is an associative array of column names and values.
// an empty array is returned if the query returned no results
$posts = Yii::$app->db->createCommand('SELECT * FROM post')
            ->queryAll();

// return a single row (the first row)
// false is returned if the query has no result
$post = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=1')
           ->queryOne();

// return a single column (the first column)
// an empty array is returned if the query returned no results
$titles = Yii::$app->db->createCommand('SELECT title FROM post')
             ->queryColumn();

// return a scalar value
// false is returned if the query has no result
$count = Yii::$app->db->createCommand('SELECT COUNT(*) FROM post')
             ->queryScalar();
```

> Note: To preserve precision, the data fetched from databases are all represented as strings, even if the corresponding
  database column types are numerical.


### Binding Parameters <span id="binding-parameters"></span>

When creating a DB command from a SQL with parameters, you should almost always use the approach of binding parameters
to prevent SQL injection attacks. For example,

```php
$post = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=:id AND status=:status')
           ->bindValue(':id', $_GET['id'])
           ->bindValue(':status', 1)
           ->queryOne();
```

In the SQL statement, you can embed one or multiple parameter placeholders (e.g. `:id` in the above example). A parameter
placeholder should be a string starting with a colon. You may then call one of the following parameter binding methods
to bind the parameter values:

* [[Yiisoft\Db\Command::bindValue()|bindValue()]]: bind a single parameter value
* [[Yiisoft\Db\Command::bindValues()|bindValues()]]: bind multiple parameter values in one call
* [[Yiisoft\Db\Command::bindParam()|bindParam()]]: similar to [[Yiisoft\Db\Command::bindValue()|bindValue()]] but also
  support binding parameter references.

The following example shows alternative ways of binding parameters:

```php
$params = [':id' => $_GET['id'], ':status' => 1];

$post = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=:id AND status=:status')
           ->bindValues($params)
           ->queryOne();

$post = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=:id AND status=:status', $params)
           ->queryOne();
```

Parameter binding is implemented via [prepared statements](http://php.net/manual/en/mysqli.quickstart.prepared-statements.php).
Besides preventing SQL injection attacks, it may also improve performance by preparing a SQL statement once and
executing it multiple times with different parameters. For example,

```php
$command = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=:id');

$post1 = $command->bindValue(':id', 1)->queryOne();
$post2 = $command->bindValue(':id', 2)->queryOne();
// ...
```

Because [[Yiisoft\Db\Command::bindParam()|bindParam()]] supports binding parameters by references, the above code
can also be written like the following:

```php
$command = Yii::$app->db->createCommand('SELECT * FROM post WHERE id=:id')
              ->bindParam(':id', $id);

$id = 1;
$post1 = $command->queryOne();

$id = 2;
$post2 = $command->queryOne();
// ...
```

Notice that you bind the placeholder to the `$id` variable before the execution, and then change the value of that variable
before each subsequent execution (this is often done with loops). Executing queries in this manner can be vastly
more efficient than running a new query for every different parameter value.

> Info: Parameter binding is only used in places where values need to be inserted into strings that contain plain SQL.
> In many places in higher abstraction layers like [query builder](db-query-builder.md) and [active record](db-active-record.md)
> you often specify an array of values which will be transformed into SQL. In these places parameter binding is done by Yii
> internally, so there is no need to specify params manually.


### Executing Non-SELECT Queries <span id="non-select-queries"></span>

The `queryXyz()` methods introduced in the previous sections all deal with SELECT queries which fetch data from databases.
For queries that do not bring back data, you should call the [[Yiisoft\Db\Command::execute()]] method instead. For example,

```php
Yii::$app->db->createCommand('UPDATE post SET status=1 WHERE id=1')
   ->execute();
```

The [[Yiisoft\Db\Command::execute()]] method returns the number of rows affected by the SQL execution.

For INSERT, UPDATE and DELETE queries, instead of writing plain SQLs, you may call [[Yiisoft\Db\Command::insert()|insert()]],
[[Yiisoft\Db\Command::update()|update()]], [[Yiisoft\Db\Command::delete()|delete()]], respectively, to build the corresponding
SQLs. These methods will properly quote table and column names and bind parameter values. For example,

```php
// INSERT (table name, column values)
Yii::$app->db->createCommand()->insert('user', [
    'name' => 'Sam',
    'age' => 30,
])->execute();

// UPDATE (table name, column values, condition)
Yii::$app->db->createCommand()->update('user', ['status' => 1], 'age > 30')->execute();

// DELETE (table name, condition)
Yii::$app->db->createCommand()->delete('user', 'status = 0')->execute();
```

You may also call [[Yiisoft\Db\Command::batchInsert()|batchInsert()]] to insert multiple rows in one shot, which is much
more efficient than inserting one row at a time:

```php
// table name, column names, column values
Yii::$app->db->createCommand()->batchInsert('user', ['name', 'age'], [
    ['Tom', 30],
    ['Jane', 20],
    ['Linda', 25],
])->execute();
```

Another useful method is [[Yiisoft\Db\Command::upsert()|upsert()]]. Upsert is an atomic operation that inserts rows into
a database table if they do not already exist (matching unique constraints), or update them if they do:

```php
Yii::$app->db->createCommand()->upsert('pages', [
    'name' => 'Front page',
    'url' => 'http://example.com/', // url is unique
    'visits' => 0,
], [
    'visits' => new \Yiisoft\Db\Expression('visits + 1'),
], $params)->execute();
```

The code above will either insert a new page record or increment its visit counter atomically.

Note that the aforementioned methods only create the query and you always have to call [[Yiisoft\Db\Command::execute()|execute()]]
to actually run them.


## Quoting Table and Column Names <span id="quoting-table-and-column-names"></span>

When writing database-agnostic code, properly quoting table and column names is often a headache because
different databases have different name quoting rules. To overcome this problem, you may use the following
quoting syntax introduced by Yii:

* `[[column name]]`: enclose a column name to be quoted in double square brackets;
* `{{table name}}`: enclose a table name to be quoted in double curly brackets.

Yii DAO will automatically convert such constructs into the corresponding quoted column or table names using the
DBMS specific syntax.
For example,

```php
// executes this SQL for MySQL: SELECT COUNT(`id`) FROM `employee`
$count = Yii::$app->db->createCommand("SELECT COUNT([[id]]) FROM {{employee}}")
            ->queryScalar();
```


### Using Table Prefix <span id="using-table-prefix"></span>

If most of your DB tables names share a common prefix, you may use the table prefix feature provided
by Yii DAO.

First, specify the table prefix via the [[Yiisoft\Db\Connection::tablePrefix]] property in the application config:

```php
return [
    // ...
    'components' => [
        // ...
        'db' => [
            // ...
            'tablePrefix' => 'tbl_',
        ],
    ],
];
```

Then in your code, whenever you need to refer to a table whose name contains such a prefix, use the syntax
`{{%table_name}}`. The percentage character will be automatically replaced with the table prefix that you have specified
when configuring the DB connection. For example,

```php
// executes this SQL for MySQL: SELECT COUNT(`id`) FROM `tbl_employee`
$count = Yii::$app->db->createCommand("SELECT COUNT([[id]]) FROM {{%employee}}")
            ->queryScalar();
```


## Performing Transactions <span id="performing-transactions"></span>

When running multiple related queries in a sequence, you may need to wrap them in a transaction to ensure the integrity
and consistency of your database. If any of the queries fails, the database will be rolled back to the state as if
none of these queries were executed.

The following code shows a typical way of using transactions:

```php
Yii::$app->db->transaction(function($db) {
    $db->createCommand($sql1)->execute();
    $db->createCommand($sql2)->execute();
    // ... executing other SQL statements ...
});
```

The above code is equivalent to the following, which gives you more control about the error handling code:

```php
$db = Yii::$app->db;
$transaction = $db->beginTransaction();
try {
    $db->createCommand($sql1)->execute();
    $db->createCommand($sql2)->execute();
    // ... executing other SQL statements ...

    $transaction->commit();
} catch(\Exception $e) {
    $transaction->rollBack();
    throw $e;
} catch(\Throwable $e) {
    $transaction->rollBack();
    throw $e;
}
```

By calling the [[Yiisoft\Db\Connection::beginTransaction()|beginTransaction()]] method, a new transaction is started.
The transaction is represented as a [[Yiisoft\Db\Transaction]] object stored in the `$transaction` variable. Then,
the queries being executed are enclosed in a `try...catch...` block. If all queries are executed successfully,
the [[Yiisoft\Db\Transaction::commit()|commit()]] method is called to commit the transaction. Otherwise, if an exception
will be triggered and caught, the [[Yiisoft\Db\Transaction::rollBack()|rollBack()]] method is called to roll back
the changes made by the queries prior to that failed query in the transaction. `throw $e` will then re-throw the
exception as if we had not caught it, so the normal error handling process will take care of it.

> Note: in the above code we have two catch-blocks for compatibility
> with PHP 5.x and PHP 7.x. `\Exception` implements the [`\Throwable` interface](http://php.net/manual/en/class.throwable.php)
> since PHP 7.0, so you can skip the part with `\Exception` if your app uses only PHP 7.0 and higher.


### Specifying Isolation Levels <span id="specifying-isolation-levels"></span>

Yii also supports setting [isolation levels] for your transactions. By default, when starting a new transaction,
it will use the default isolation level set by your database system. You can override the default isolation level as follows,

```php
$isolationLevel = \Yiisoft\Db\Transaction::REPEATABLE_READ;

Yii::$app->db->transaction(function ($db) {
    ....
}, $isolationLevel);

// or alternatively

$transaction = Yii::$app->db->beginTransaction($isolationLevel);
```

Yii provides four constants for the most common isolation levels:

- [[\Yiisoft\Db\Transaction::READ_UNCOMMITTED]] - the weakest level, Dirty reads, non-repeatable reads and phantoms may occur.
- [[\Yiisoft\Db\Transaction::READ_COMMITTED]] - avoid dirty reads.
- [[\Yiisoft\Db\Transaction::REPEATABLE_READ]] - avoid dirty reads and non-repeatable reads.
- [[\Yiisoft\Db\Transaction::SERIALIZABLE]] - the strongest level, avoids all of the above named problems.

Besides using the above constants to specify isolation levels, you may also use strings with a valid syntax supported
by the DBMS that you are using. For example, in PostgreSQL, you may use `"SERIALIZABLE READ ONLY DEFERRABLE"`.

Note that some DBMS allow setting the isolation level only for the whole connection. Any subsequent transactions
will get the same isolation level even if you do not specify any. When using this feature
you may need to set the isolation level for all transactions explicitly to avoid conflicting settings.
At the time of this writing, only MSSQL and SQLite are affected by this limitation.

> Note: SQLite only supports two isolation levels, so you can only use `READ UNCOMMITTED` and `SERIALIZABLE`.
Usage of other levels will result in an exception being thrown.

> Note: PostgreSQL does not allow setting the isolation level before the transaction starts so you can not
specify the isolation level directly when starting the transaction.
You have to call [[Yiisoft\Db\Transaction::setIsolationLevel()]] in this case after the transaction has started.

[isolation levels]: http://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels


### Nesting Transactions <span id="nesting-transactions"></span>

If your DBMS supports Savepoint, you may nest multiple transactions like the following:

```php
Yii::$app->db->transaction(function ($db) {
    // outer transaction

    $db->transaction(function ($db) {
        // inner transaction
    });
});
```

Or alternatively,

```php
$db = Yii::$app->db;
$outerTransaction = $db->beginTransaction();
try {
    $db->createCommand($sql1)->execute();

    $innerTransaction = $db->beginTransaction();
    try {
        $db->createCommand($sql2)->execute();
        $innerTransaction->commit();
    } catch (\Exception $e) {
        $innerTransaction->rollBack();
        throw $e;
    } catch (\Throwable $e) {
        $innerTransaction->rollBack();
        throw $e;
    }

    $outerTransaction->commit();
} catch (\Exception $e) {
    $outerTransaction->rollBack();
    throw $e;
} catch (\Throwable $e) {
    $outerTransaction->rollBack();
    throw $e;
}
```


## Replication and Read-Write Splitting <span id="read-write-splitting"></span>

Many DBMS support [database replication](http://en.wikipedia.org/wiki/Replication_(computing)#Database_replication)
to get better database availability and faster server response time. With database replication, data are replicated
from the so-called *master servers* to *slave servers*. All writes and updates must take place on the master servers,
while reads may also take place on the slave servers.

To take advantage of database replication and achieve read-write splitting, you can configure a [[Yiisoft\Db\Connection]]
component like the following:

```php
[
    '__class' => Yiisoft\Db\Connection::class,

    // configuration for the master
    'dsn' => 'dsn for master server',
    'username' => 'master',
    'password' => '',

    // common configuration for slaves
    'slaveConfig' => [
        'username' => 'slave',
        'password' => '',
        'attributes' => [
            // use a smaller connection timeout
            PDO::ATTR_TIMEOUT => 10,
        ],
    ],

    // list of slave configurations
    'slaves' => [
        ['dsn' => 'dsn for slave server 1'],
        ['dsn' => 'dsn for slave server 2'],
        ['dsn' => 'dsn for slave server 3'],
        ['dsn' => 'dsn for slave server 4'],
    ],
]
```

The above configuration specifies a setup with a single master and multiple slaves. One of the slaves will
be connected and used to perform read queries, while the master will be used to perform write queries.
Such read-write splitting is accomplished automatically with this configuration. For example,

```php
// create a Connection instance using the above configuration
Yii::$app->db = Yii::createObject($config);

// query against one of the slaves
$rows = Yii::$app->db->createCommand('SELECT * FROM user LIMIT 10')->queryAll();

// query against the master
Yii::$app->db->createCommand("UPDATE user SET username='demo' WHERE id=1")->execute();
```

> Info: Queries performed by calling [[Yiisoft\Db\Command::execute()]] are considered as write queries, while
  all other queries done through one of the "query" methods of [[Yiisoft\Db\Command]] are read queries.
  You can get the currently active slave connection via `Yii::$app->db->slave`.

The `Connection` component supports load balancing and failover between slaves.
When performing a read query for the first time, the `Connection` component will randomly pick a slave and
try connecting to it. If the slave is found "dead", it will try another one. If none of the slaves is available,
it will connect to the master. By configuring a [[Yiisoft\Db\Connection::serverStatusCache|server status cache]],
a "dead" server can be remembered so that it will not be tried again during a
[[Yiisoft\Db\Connection::serverRetryInterval|certain period of time]].

> Info: In the above configuration, a connection timeout of 10 seconds is specified for every slave.
  This means if a slave cannot be reached in 10 seconds, it is considered as "dead". You can adjust this parameter
  based on your actual environment.


You can also configure multiple masters with multiple slaves. For example,


```php
[
    '__class' => Yiisoft\Db\Connection::class,

    // common configuration for masters
    'masterConfig' => [
        'username' => 'master',
        'password' => '',
        'attributes' => [
            // use a smaller connection timeout
            PDO::ATTR_TIMEOUT => 10,
        ],
    ],

    // list of master configurations
    'masters' => [
        ['dsn' => 'dsn for master server 1'],
        ['dsn' => 'dsn for master server 2'],
    ],

    // common configuration for slaves
    'slaveConfig' => [
        'username' => 'slave',
        'password' => '',
        'attributes' => [
            // use a smaller connection timeout
            PDO::ATTR_TIMEOUT => 10,
        ],
    ],

    // list of slave configurations
    'slaves' => [
        ['dsn' => 'dsn for slave server 1'],
        ['dsn' => 'dsn for slave server 2'],
        ['dsn' => 'dsn for slave server 3'],
        ['dsn' => 'dsn for slave server 4'],
    ],
]
```

The above configuration specifies two masters and four slaves. The `Connection` component also supports
load balancing and failover between masters just as it does between slaves. A difference is that when none
of the masters are available an exception will be thrown.

> Note: When you use the [[Yiisoft\Db\Connection::masters|masters]] property to configure one or multiple
  masters, all other properties for specifying a database connection (e.g. `dsn`, `username`, `password`)
  with the `Connection` object itself will be ignored.


By default, transactions use the master connection. And within a transaction, all DB operations will use
the master connection. For example,

```php
$db = Yii::$app->db;
// the transaction is started on the master connection
$transaction = $db->beginTransaction();

try {
    // both queries are performed against the master
    $rows = $db->createCommand('SELECT * FROM user LIMIT 10')->queryAll();
    $db->createCommand("UPDATE user SET username='demo' WHERE id=1")->execute();

    $transaction->commit();
} catch(\Exception $e) {
    $transaction->rollBack();
    throw $e;
} catch(\Throwable $e) {
    $transaction->rollBack();
    throw $e;
}
```

If you want to start a transaction with the slave connection, you should explicitly do so, like the following:

```php
$transaction = Yii::$app->db->slave->beginTransaction();
```

Sometimes, you may want to force using the master connection to perform a read query. This can be achieved
with the `useMaster()` method:

```php
$rows = Yii::$app->db->useMaster(function ($db) {
    return $db->createCommand('SELECT * FROM user LIMIT 10')->queryAll();
});
```

You may also directly set `Yii::$app->db->enableSlaves` to be `false` to direct all queries to the master connection.


## Working with Database Schema <span id="database-schema"></span>

Yii DAO provides a whole set of methods to let you manipulate the database schema, such as creating new tables,
dropping a column from a table, etc. These methods are listed as follows:

* [[Yiisoft\Db\Command::createTable()|createTable()]]: creating a table
* [[Yiisoft\Db\Command::renameTable()|renameTable()]]: renaming a table
* [[Yiisoft\Db\Command::dropTable()|dropTable()]]: removing a table
* [[Yiisoft\Db\Command::truncateTable()|truncateTable()]]: removing all rows in a table
* [[Yiisoft\Db\Command::addColumn()|addColumn()]]: adding a column
* [[Yiisoft\Db\Command::renameColumn()|renameColumn()]]: renaming a column
* [[Yiisoft\Db\Command::dropColumn()|dropColumn()]]: removing a column
* [[Yiisoft\Db\Command::alterColumn()|alterColumn()]]: altering a column
* [[Yiisoft\Db\Command::addPrimaryKey()|addPrimaryKey()]]: adding a primary key
* [[Yiisoft\Db\Command::dropPrimaryKey()|dropPrimaryKey()]]: removing a primary key
* [[Yiisoft\Db\Command::addForeignKey()|addForeignKey()]]: adding a foreign key
* [[Yiisoft\Db\Command::dropForeignKey()|dropForeignKey()]]: removing a foreign key
* [[Yiisoft\Db\Command::createIndex()|createIndex()]]: creating an index
* [[Yiisoft\Db\Command::dropIndex()|dropIndex()]]: removing an index

These methods can be used like the following:

```php
// CREATE TABLE
Yii::$app->db->createCommand()->createTable('post', [
    'id' => 'pk',
    'title' => 'string',
    'text' => 'text',
]);
```

The above array describes the name and types of the columns to be created. For the column types, Yii provides
a set of abstract data types, that allow you to define a database agnostic schema. These are converted to
DBMS specific type definitions dependent on the database, the table is created in.
Please refer to the API documentation of the [[Yiisoft\Db\Command::createTable()|createTable()]]-method for more information.

Besides changing the database schema, you can also retrieve the definition information about a table through
the [[Yiisoft\Db\Connection::getTableSchema()|getTableSchema()]] method of a DB connection. For example,

```php
$table = Yii::$app->db->getTableSchema('post');
```

The method returns a [[Yiisoft\Db\TableSchema]] object which contains the information about the table's columns,
primary keys, foreign keys, etc. All these information are mainly utilized by [query builder](db-query-builder.md)
and [active record](db-active-record.md) to help you write database-agnostic code.
