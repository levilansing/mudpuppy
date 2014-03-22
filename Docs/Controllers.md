#Controllers
The Mudpuppy `Controller` class provides the necessary base functionality to receive requests and execute actions if necessary. Controllers can inherit from either or both `PageController` or `DataObjectController`. When you subclass a `Controller`, you are required to implement the following:


```php
	/** @returns array */
	abstract public function getRequiredPermissions();
```


This getRequiredPermissions method should return an array of permissions as defined in the `App\Permissions` class. This array is checked in `App\Security` which is stubbed out and should be implemented.

A controller is accessed by using part of the namespace as the url. For example, a controller named `App\Profile\Manage` is accessed by navigating to `http://yoursite/profile/manage/`. What you can do from there depends on the traits the page implements and the actions that are defined.

Controllers can use **Path Options** as a means of accepting additional data through the URL. Any path parts of the URL that comes after the controller name are extracted and saved into `$pathOptions`. Call the `getOption(index)` method of the `Controller` to get one of the path options.

##Actions

Actions are designed to be API calls that return JSON and are defined within a controller. All controllers, whether or not they implement any other controller traits, support actions. Actions are defined as a public function that begins with `action_`. Non-optional parameters to the action function are required to exist in the POST or GET request and their types **must** be defined through PhpDoc, otherwise an exception is thrown.

Data types currently supported are:

- int / integer
- float / double
- array
- string
- Subclasses of DataObject


Ints and floats/doubles are type checked and casted to their respective type. Strings and Arrays are not checked. DataObjects require the parameter to be an integer representing the id of that object. The DataObject is then automatically instantiated and loaded from the database.


###Example


```php
namepsace App\Profile;
class Manage {
	...
	
	/**
	 * @param string $title
	 * @param string $message
	 * @return array
	 */
	public function action_getJson($title, $message = '1') {
		return array('title' => $title, 'message' => $message);
	}
}
```


The above action can be executed by navigating to `http://yoursite/profile/manage/getJson?title=Hello&message=This+is+a+message`. Message is not required; additionally a trailing slash after the action name (`getJson/`) is acceptable. You can also POST these variables rather than using GET.

If you do not provide the required `title` parameter in either GET or POST, the action will not be executed and Mudpuppy will instead throw an `InvalidInput` exception.

###Exceptions
Actions should throw exceptions whenever a problem occurs. By throwing a `MudpuppyException` or subclass thereof, the API handler will catch this exception and return an appropriate JSON encoded message to the user. This way you can have multiple exit points and if your front end application follows the paradigm, it will handle any exception thrown whether intentional or not. 

```php
/**
 * @param int id
 * @return array
 */
public function action_example($id) {
	if ($id < 0) {
		throw new Mudpuppy\InvalidInputException("id can't be negative");
	}
	...
}
```

In this example, if you call `/example/?id=-1`, assuming you have debug enabled, the response will be:

```json
{ "error": 400, "message": "id can't be negative" }
```

However, if debug is off (in the case of production), the response will be

```json
{ "error": 400, "message": "Invalid Input" }
```

To send an explicit message to the user, you must provide a second parameter to the exception:

```php
	throw new Mudpuppy\InvalidInputException('debug message', 'user message');
```

To send your own status codes, you can subclass or use the MudpuppyException directly:

```php
	throw new Mudpuppy\MudpuppyException('setting a 501 status code', 'user message', 501);
```



##Page Controllers

Page Controllers are `Controllers` that inherit from a `PageController` trait. Page controllers typically include an accompanying view of the same name but may show any view based on the url page or other inputs. Page controllers should always exist in the namespace that corresponds to the desired url. This way the App folder structure mirrors the site map of your application.

```php

trait PageController {
	/** @var string optional page title override */
	protected $pageTitle;
	
	/**
	 * Renders the page header. The default implementation adds the page title and imports any js and css files
	 * specified by getScripts().
	 */
	public function renderHeader()


	/**
	 * return a list of regular expressions or strings that the page options must match
	 * example: a url of "this-controller/get/42" can be validated by array('#^get/[0-9]+$#');
	 * @return array
	 */
	public function getAllowablePathPatterns()


	/**
	 * Renders the page body.
	 */
	abstract public function render();

	/**
	 * @return array associative array with two keys, 'js' and 'css', each being an array of script paths for use by the
	 * default implementation of renderHeader()
	 */
	protected function getScripts()
```

The required methods are stubbed out for you if you use the admin panel to create the page controller. A common implementation of the `render` method would be to use the path options (ex: `getOption(0)` ) to choose a view to render to support multiple views of the same information.

The result of `getScripts()` is used by default implementation to automatically include css and js files on the page that are not part of the template by injecting them into the header when `renderHeader` is executed.

Mudpuppy is not a CMS and only provides a single template by default. If you need to support multiple templates, your controllers can clear the output buffer and load a different template instead, but once rendered it must call `App::cleanExit()` to avoid the remainder of the original template from being displayed. Consider using your own subclass of `Controller` to achieve something like this.


##DataObject Controllers

DataObject Controllers are `Controllers` that inherit from a `DataObjectController` trait. These are intended to provide a standard REST based data object API to directly access rows in your database. Use of the `DataObjectController` trait should be carefully implemented to verify user permissions with every request.

**[todo fill out this section]**




























