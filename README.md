# Mudpuppy

Mudpuppy is a PHP web application framework that provides a robust and easy-to-use backend MVC architecture.

## Quick Start

### Installation

Mudpuppy requires a basic AMP stack (be it LAMP, WAMP, or MAMP) with PHP version 5.4 or newer. To install, simply copy the contents of the `src` directory into your project's web root.

Note that it must be run from the site root (ie, not a sub-directory of the domain), so we suggest setting up a virtual host for your development environment. Please see [Using Virtual Hosts](https://github.com/levilansing/mudpuppy/wiki/Using-Virtual-Hosts) for details.

For optimal support on Windows and Mac development environments, and to prevent potential compatibility issues when later deploying to Linux, you should adjust MySQL's table name casing mode by setting `lower_case_table_names = 2`. Please see [MySQL Case Settings](https://github.com/levilansing/mudpuppy/wiki/MySQL-Case-Settings) for details. 

Finally, to run the included sample application, you will need to create a new MySQL database named `MudpuppySample`. The default configuration assumes the MySQL user and password are both `root`, so if that is not the case for your setup, or if you use a non-standard port, you will need to update the database connection settings in `App/Config.php`.

Once everything is setup, navigate your browser to `localhost` (or the virtual host name you configured). You should see a sample home page with a few links to explore. If not, please double-check your MySQL settings, virtual host setup, PHP version, etc, and try again.

### Basic Concepts

There are two core concepts/constructs that you need to understand to use Mudpuppy: controllers, which accept requests from the outside world, and data objects, which represent and simplify interaction with your database.

#### Controllers

#### Data Objects

### Admin Area

## Architecture

### Site Structure

### Page Options

### API Calls

## Configuration

## Security

## Utilities