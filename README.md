<p style="text-align: center; width: 30vw; max-width: 500px; margin: auto"><img alt="BBN PHP" src="https://bbn.io/bbn/php-title-black.png"></p>

bbn
===

## The PHP library used by [app-UI](https://app-ui.com)

### You can install the library through Composer

```
{
  "require": {
    "nabab/bbn": "dev/master"
  }
}
```

### A library targeted at Single Page Applications that includes:

* An MVC framework
* A powerful ORM supporting database structure analysis and plenty of return methods
* An options' class on which most app-UI features are based
* API classes for integrating external services (Virtualmin, Cloudmin, Github...)
* A History class allowing to store each change done in the database and revert them
* Files, images, and PDF files manipulation classes
* Filesystems explorator
* An automated task management system
* A universal caching system
* HTML generation classes
* Users and groups management classes
* Parsers for PHP, Javascript and VueJS components
* Specific classes for app-UI features such as:
  * Notes
  * Medias
  * Chat
  * Clipboard
  * CMS
  * Dashboard
  * Databases management system
  * Databases synchronization system
  * IDE
  * Automated mailings
  * Internationalization
  * Masking system for letters and texts
  * A notification system
  * Data's observers
  * Passwords management
  * Planning and events management
  * Specific projects management system targetted at app-UI
  * A statistics system
  * A general project and workflow management system
* A bunch of static methods for manipulating all kind of data and other useful functions
* And many other features!

It is not yet released and there is a big work of code review, translation and documentation ahead.  

Also no testing has been implemented yet, knowledge and ressources are needed...

Any help is welcome!

<!--
Usage
-----

A quick example:

```php
<?php
$db = new \bbn\db($cfg);

\bbn\x::hdump($db->modelize("my_table_name"));

\bbn\x::dump($db->get_rows("SELECT * FROM my_table_name WHERE status = ?", $var));

\bbn\x::hdump($db->select(
  "my_table_name", // table
  ["field1", "field2"], // columns
  ["id" => 25] // WHERE
));

\bbn\x::dump($db->rselect_all(
  "my_table_name", // table
  [], // all columns
  [["id", "<", 25], ["name", "LIKE", "tri%"]], // WHERE
  ["date" => DESC, "name"], // ORDER
  50, // LIMIT
  20 // START 
));

\bbn\x::hdump($db->get_var("SELECT id FROM mytable WHERE name LIKE ?", "tri%"));
```

-->