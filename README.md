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

#### Data Objects

### Admin Area

The admin area is only available in debug mode and may be protected with HTTP Basic Authentication (chosen during installation). Authentication settings can be changed by selecting **Manage App Structure** then **BasicAuth.json**. 

## Architecture

### Site Structure

### Page Options

### API Calls

## Configuration

## Security

## Utilities