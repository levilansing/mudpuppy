#Database Sessions Add-on

This add on allows you to store your PHP sessions in the database.

## Installation

1. Copy DatabaseSessionHandler.php to your App folder
2. Copy BrowserSession.php to your Model folder
3. Execute the following SQL to create the necessary DB table:

```mysql
CREATE TABLE `BrowserSessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessionId` varchar(32) NOT NULL,
  `lastAccessed` int(11) NOT NULL,
  `data` mediumtext,
  PRIMARY KEY (`id`),
  KEY `ix_sessionId` (`sessionId`),
  KEY `ix_accessed` (`lastAccessed`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
```

## Usage

In your main Application static (abstract) class, ensure the configure method is overridden and add the following:

```php
	public static function configure() {
		// Perform any application-specific configuration here
		// Note: Mudpuppy has not yet connected to the database OR started the Session

		// initialize the database session handler
		$sessionHandler = new DatabaseSessionHandler();
	}
```

The handler MUST be instantiated during the `configure` method in order to start up before the session starts.
