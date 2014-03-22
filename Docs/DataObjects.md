#DataObject and Database

##Fetching DataObjects from the database

There are two shorthand functions to fetch data objects with simple criteria. 

```php
	/**
	 * Fetch by an id or key value pair map
	 * @param int|array $criteria the integer id or an array of column value pairs
	 * @param array $order an array of column direction pairs ['column'=>'ASC']
	 * @param int $limit result limit
	 * @param int $offset result offset
	 * @throws MudpuppyException
	 * @return \Mudpuppy\DataObject[]
	 */
	public static function fetch($criteria, $order = null, $limit = 0, $offset = 0) {}

	/**
	 * Fetch by an id or key value pair map, but only return the first result or null
	 * @param int|array $criteria
	 * @return DataObject|null
	 */
	public static function fetchOne($criteria) {}
```

###Examples
For the following examples, assume you have a table in your database called Users. Mudpuppy autogenerates the class `Model\User` which extends DataObject.

```php
// fetch the user with id = 15
$user = User::fetchOne(15);

// fetch an array of users who are named "Bob Carson"
$users = User::fetch(['firstName' => 'Bob', 'lastName' => 'Carson']);

// fetch the first 25 users alphabetically by last name, first name
$users = User::fetch([], ['lastName' => 'ASC', 'firstName' => 'ASC'], 25);

// fetch 10 more users (after the first 25) alphabetically by last name, first name
$users = User::fetch([], ['lastName' => 'ASC', 'firstName' => 'ASC'], 10, 25);
```

##Inserting and Updating

DataObjects are saved using the `save()` method on the object. To insert a new row into a database table, create a new instance of the data object and call `save`.

```php
$user = new User();
$user->name = "Sample User";
$user->email = "sample@sample.com"
if (!$user->save()) {
	// write failed, you can use App::getDBO()->getLastError() for details if necessary}
```

Updating an object follows the same form. First fetch an existing object from the database, then update any necessary values and call `save`.

```php
// fetch an existing user from the database
$user = User::fetchOne(15);
$user->lastLoginTime = time();
if (!$user->save()) {
	// update failed, you can use App::getDBO()->getLastError() for details if necessary}
```

##Custom Queries

For more complex queries, to encourge proper security practices, the Database class requires you to use prepared statements.

In the `Mudpuppy\Database` class:

```php
	/**
	 * Prepare a statement using PDO
	 * @param string $query The sql statement to be prepared for execution
	 * @param array $driverOptions optional PDO driver options
	 * @return bool
	 */
	public function prepare($query, array $driverOptions = []) {}
	
	/**
	 * Execute the current prepared statement
	 * @param array|null $bindArray optional array of values or associative array of [named parameter => value] to bind
	 * @return \PDOStatement|bool
	 */
	public function execute($bindArray = null) {}
	
	// the following mirror the PDO object syntax and give you access to the PDO statement
	public function bindParams($params, $types=null) {
	public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null) {}
	public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR) {}

	public function setStatementAttribute($attribute, $value) {}
	public function getStatementAttribute($attribute) {}
	public function setStatementFetchMode($mode) {}
```

And fetching from result sets:

```php
	/**
	 * fetch the first value of the next result
	 * @return int|string|null
	 */
	public function fetchFirstValue() {}

	/**
	 * Fetch one row into a data object of type $type or an associative array
	 * @param $type string Class name of the data object
	 * @return DataObject|array|null
	 */
	public function fetch($type = 'array') {}
	
	/**
	 * Fetch all results into data objects of type $type or an associative array
	 * @param $type string Class name of the data object
	 * @return DataObject[]|array
	 */
	public function fetchAll($type = 'array') {}
```

###Examples

```php
$db = App::getDBO();

// Count all users
$db->prepare("SELECT COUNT(*) FROM Users");
$db->execute();
$userCount = $db->fetchFirstValue();

// Query for users who recently logged in
$db->prepare("SELECT * FROM Users WHERE type = ? AND lastLogin > ?");
$db->bindValue(1, ACCOUNT_TYPE, \PDO::PARAM_INT);
$db->bindValue(2, $db->formatDate(time()-24*60*60));
$db->execute();
$users = $db->fetchAll('Model\User');

// Query for a custom result set that is not for a DataObject
$db->prepare("SELECT Users.name,Accounts.type FROM Users JOIN Accounts  ON Accounts.userId = Users.id WHERE Users.active=:isActive ORDER BY Users.name ASC LIMIT 10");
$db->execute(['isActive'=>1]);
foreach ($db->fetchAll() as $row) {
	// do something with each result row
	echo $row['someColumn'];}
```

##Foreign Keys

Mudpuppy can recognize foreign keys if you follow these conventions: 

- The database column must end with `Id` for example, `modifiedById`
- The database column must have a foreign key defined in the schema
- The foreign key must reference a table represented by a data object in the model

If these criteria are met, when updating data objects, Mudpuppy will recognize the foreign key and add an additional propery for the column name sans Id. In this example, there will be a property for `modifiedById` which will contain the value in the column, and there will be a property for `modifiedBy` that will be of the type referenced by the foreign key. This object is lazily fetched when the property is used.

###Example: Using Foreign Key dereferencing

For this example, assume we have a table called Notes, and a table called Users. The Notes table is described as follows:

```
+--------------+----------+------+-----+---------+----------------+
| Field        | Type     | Null | Key | Default | Extra          |
+--------------+----------+------+-----+---------+----------------+
| id           | int(11)  | NO   | PRI | NULL    | auto_increment |
| note         | text     | NO   |     | NULL    |                |
| dateRecorded | datetime | NO   |     | NULL    |                |
| recordedById | int(11)  | NO   | MUL | NULL    |                |
+--------------+----------+------+-----+---------+----------------+
```

Because mudpuppy singularizes table names to create dataobjects, the Notes table is represented by a Note data object. The field `recordedById` has a foreign key constraint that references the `id` column of the `Users` table.

```php
// fetch the note with id = 10
$note = Note::fetch(10);

// display the id and the name of the user that recorded the note
echo $note->recordedById . ' = ' . $note->recordedBy->name;

// fetch a user object
$user = User::fetch(['name'=>'Levi']);

// change the recorded by user to myself. 
// you could set the field yourself:
$note->recordedById = $user->id;

// or you can use the foreign key constraint to recordedById to $user->id
$note->recordedBy = $user;

// save the changes to the note
$note->save();
```

###Notes
Foreign key references are cached throughout each request. So there is little cost to runtime if you reference the foreign key property multiple times, even accross multiple objects.

The side effect is that object may not stay up to date if you modify the row using a separate instance of that object. For example, if you accessed a foreign key property for a user, then **separately** fetched that same object, updated it, and saved it, should you access a foreign key property again, its data would be out of date. However, if instead of separately fetching the object, if you modified the object returned by the foreign key reference, updated and saved it, the next time you access that same object from another foreign key reference, it will be up to date.