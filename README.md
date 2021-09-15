<p align="center">
  <svg viewBox="0 0 120 40" preserveAspectRatio="xMinYMin" xmlns="http://www.w3.org/2000/svg">
    <path fill="color"
          d="m18 28 6-6-6-6-4 4-2-2 4-4.1-8-8L0 14l10 10 2-2z"></path>
    <path fill="#000000"
          d="m18 16-4 4-2-2L29.9 0l8 8L24 22zM10 24l2-2 6 6-2.1 2z"></path>
    <text x="36%"
          y="23"
          :fill="black ? '#000000' : '#FFFFFF'"
          style="font-size:16px;font-family:Montserrat;font-weight:800"
          transform="matrix(.85 0 0 1 1 1)">BBN
    </text>
    <text x="72%"
          y="23"
          fill="#ff5722"
          style="font-size:16px;font-family:Montserrat;font-weight:800"
          transform="matrix(.85 0 0 1 1 1)"
          v-text="name"/>PHP
  </svg>
</p>

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
* Files, Images, and PDF files manipulation classes
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
$db = new \bbn\Db($cfg);

\bbn\X::hdump($db->modelize("my_table_name"));

\bbn\X::dump($db->getRows("SELECT * FROM my_table_name WHERE status = ?", $var));

\bbn\X::hdump($db->select(
  "my_table_name", // table
  ["field1", "field2"], // columns
  ["id" => 25] // WHERE
));

\bbn\X::dump($db->rselectAll(
  "my_table_name", // table
  [], // all columns
  [["id", "<", 25], ["name", "LIKE", "tri%"]], // WHERE
  ["date" => DESC, "name"], // ORDER
  50, // LIMIT
  20 // START 
));

\bbn\X::hdump($db->getVar("SELECT id FROM mytable WHERE name LIKE ?", "tri%"));
```

-->