# Mudpuppy

Mudpuppy is a PHP web application framework that provides a robust and easy-to-use backend MVC architecture.

## Quick Start

### Installation

Mudpuppy requires a basic AMP stack (be it LAMP, WAMP, or MAMP) with PHP version 5.4 or newer. To install, simply copy the contents of the `src` directory into your project's web root.

Note that it must be run from the site root (ie, not a sub-directory of the domain), so we suggest setting up a virtual host for your development environment. Please see [Using Virtual Hosts](https://github.com/levilansing/mudpuppy/wiki/Using-Virtual-Hosts) for details.

For optimal support on Windows and Mac development environments, and to prevent potential compatibility issues when later deploying to Linux, you should adjust MySQL's table name casing mode by setting `lower_case_table_names = 2`. Please see [MySQL Case Settings](https://github.com/levilansing/mudpuppy/wiki/MySQL-Case-Settings) for details. 

Once your environment is configured, navigate your browser to `localhost` (or the virtual host name you configured). You should be redirected to the installer. Choose your options, then click `Install`. If everything is successful, you will be redirected to the sample home page with a few links to explore. If not, please double-check your AMP stack, virtual host setup, etc, and try again.

### Basic Concepts

There are two core concepts/constructs that you need to understand to use Mudpuppy: controllers, which accept requests from the outside world, and data objects, which represent and simplify interaction with your database.

#### Controllers

Controllers are the bridge between a URL and your application. Consider the App folder your site root and use the fully qualified class name as the location of your controller. For example:

- **/** => App\RootController.php
- **/profile/** => App\Profile\ProfileController.php
- **/profile/edit/** => App\Profile\Edit\EditController.php

Your controllers can be created from the Mudpuppy App Structure admin page and can use traits to expand their functionality:

- **No Traits**: standard controller that supports API calls only (action_ApiCallName)
- **PageController**: designed to render a view
- **DataObjectController**: creates an automated interface for fetching and saving data objects


See [Docs/Controllers.md](Docs/Controlllers.md) for a more detailed explanation of controllers.

#### Data Objects

Data objects are classes that correspond 1:1 with a database table. They are initially generated into the `Model` namespace from the Mudpuppy Admin interface using the **Synchronize DataObjects** button, and the fields and foreign key references are updated each time you synchronize. Data object classes are only created if the table includes a primary key (int) called `id`.

When fetching data from a table, you should fetch each row into a DataObject. There are a number of convenience methods to make this happen. Each instance of the DataObject represents a row and allows you to edit the values, save changes, or delete the row.

When inserting a new row, similarly you should create a new instance of the corresponding data object, set the values, and call `save()`.

See [Docs/DataObjects.md](Docs/DataObjects.md) for detailed instructions on using data objects and querying the database.

### Admin Area

The admin area is only available in debug mode and may be protected with HTTP Basic Authentication (chosen during installation). Authentication settings can be changed by selecting **Manage App Structure** then **BasicAuth.json**. 

## Architecture

### Site Structure

### Page Options

### API Calls

## Configuration

## Security

## Utilities