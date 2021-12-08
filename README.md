

<p  align="center"><img  alt="BBN PHP"  src="https://bbn.io/logo/black/text/php.svg"  style="max-width: 40%; height: auto"></p>

bbn
===

## The PHP library used by [app-UI](https://app-ui.com)

### You can install the library through Composer

```json
{
  "require": {
    "bbn/bbn": "dev/master"
  }
}
```

### A library targeted at Single Page Applications that includes:

* [An MVC framework](#mvc)
* [A powerful ORM supporting database structure analysis and lots of return methods](#orm)
* [An options' class on which most app-UI features are based](#option)
* API classes for integrating external services (Virtualmin, Cloudmin, Github, Gitlab, Payments...)
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
  * Content Management System
  * Dashboard
  * Databases management system
  * Databases synchronization system
  * I.D.E.
  * Automated mailings
  * Internationalization
  * Masking system for letters and texts
  * A notification system
  * Data's observers
  * Passwords management
  * Planning and events management
  * Project management system
  * A statistics system
  * A general project and workflow management system
  * A bunch of static methods for manipulating all kind of data and other useful functions
* And many other features!

The bbn framework works with a [router](https://github.com/nabab/bbn-router) and some configuration files. An [installer](https://www.youtube.com/watch?v=dQw4w9WgXcQ) will be released in 2022.

There is still a huge amount of work on code review, translation and documentation ahead.

Any help is welcome!

### Typical installation structure:

- app-ui/
	- data/
	- src/
		- cfg/
			- environments.yml
			- settings.yml
			- custom2.php
		- cli/
		- components/
		- lib/
		- locale/
		- mvc/
			- css/
			- html/
			- js/
			- model/
			- private/
			- public/
		- plugins/
	- router.php
- public_html/
	- .htaccess
	- index.php

## By default the BBN framework returns a HTML document if there is no POST, and a JSON object otherwise.

The [bbn-js](https://github.com/nabab/bbn-js) and [bbn-vue](https://github.com/nabab/bbn-vue) libraries and intimately related with this framework, and deal with its I/O.

They catch each local link clicked, send them as a POST request, then deal with the response.

### The JSON object returned by clicking a link typically holds the following properties:

|Name|Description|
|---|---|
|`content`|a HTML string, which will be injected into a container|
|`title`|will be the new page's title, that will be prepended to the website's general title|
|`css`|a CSS string which will be put as a `<style/>` tag in the same container|
|`script`|a javascript function which will either return:<br>- A function that will receive the container as argument and will be executed after the content injection<br>- An object that will be treated as a VueJS anonymous component inside the [router component](https://github.com/nabab/bbn-vue/blob/master/src/components/router/router.js)|

### Life cycle of a typical request

#### The redirection

:arrow_right: Call to https://myapp.com/just/testing (which does not exist)  
:arrow_right: An .htaccess file rewrites all the not found files to an index.php file  
:arrow_right: The index file `chdir` in the app folder `src/` which should be outside of the public root  
:arrow_right: It then includes the [router](https://github.com/nabab/bbn-router/blob/master/src/router.php) which should be in the src/ directory (as a symlink towards vendor)  

#### The routing

:arrow_right: It recognizes the predefined configuration in which it stands through the `hostname` and `app_path` definitions from `src/cfg/environment.yml`  
:arrow_right: It defines constants and initializes autoload  
:arrow_right: It instantiates different classes depending on the configuration  
:arrow_right: It creates the MVC class, which will look for the right controller:  

- It looks into `src/mvc/public/` for a controller corresponding to the path `just/testing`:
	- If it is a landing page (no POST) the file should be:
	`src/mvc/public/just/testing/index.php`
	- Otherwise the file should be:
	`src/mvc/public/just/testing.php`
- while it doesn't find the file it goes backward in the directories: 
	- If it is a landing page (no POST) it looks for:
	`src/mvc/public/just/index.php` and finally `src/mvc/public/index.php`
	- Otherwise it looks for:
	`src/mvc/public/just.php` then returns a `404` if it doesn't find it

#### The execution

:arrow_right: An optional file `src/custom1.php` is included with an object `$bbn` available with property `mvc`  
:arrow_right: If we are not in CLI mode a session is started   
:arrow_right: Still not in CLI mode an optional file `src/custom2.php` is included with an object `$bbn` available with property `mvc`, `user` and `session` depending on the configuration  
:arrow_right: The MVC includes the controller  
:arrow_right: Whatever output becomes the `content` property of the response object  
:arrow_right: An optional file `src/custom3.php` is included with an object `$bbn` available with the new property `obj` which will be the output  

#### The output  

:arrow_right:  If it is a landing page (no POST) the property `content` will be returned with HTML headers  
:arrow_right:  Otherwise the object `mvc->obj` will be returned encoded with JSON headers  
:arrow_right:  If there is no `content` in `obj` but there is `file` or `image` the response will be dealt accordingly with the corresponding headers  

## A few examples

### ORM

```php
<?php
use bbn\X;

/** @var bbn\Db $db */

// Returns an array with fields, cols and keys props which will give you all information about a table
X::adump($db->modelize("my_table"));
// Simple query
X::adump($db->getRows("SELECT * FROM my_table WHERE status = ?", $var));
// Same query
X::adump($db->select(
  "my_table", // table
  [], // all columns
  ["status" => $var] // WHERE
));

// More arguments
X::adump($db->rselectAll(
  "my_table", // table
  ["field1", "field2"], // columns
  [["id", "<", 25], ["name", "LIKE", "tri%"]], // WHERE
  ["date" => DESC, "name"], // ORDER
  50, // LIMIT
  20 // START
));

// The full way 
X::adump($db->rselectAll([
  'tables'  => ["my_table_name", "my_table_name2"],
  'fields'  => ["field1", "field2"], // all columns
  'where'   => [
    'logic'      => 'OR',
    'conditions' => [
      'user'       => 'admin',
      'conditions' => [
        'logic'      => 'AND',
        'conditions' => [ // Mixed mode allowed in filters
          [
            'field'    => 'my_date',
            'operator' => '<',
            'exp'      => 'NOW()'
          ],
          ["id", "<", 25]
          'name' => 'tri%'
        ],
      ]
    ]
  ],
  'join'    => [
    [
      'table' => 'my_table3',
      'on' => [
        [
          'field' => 'my_table3.uid',
          'exp'   => 'my_table.uid_table3' // Operator is = by default
        ]
      ]
    ]
  ],
  'order'    => ["date" => DESC, "name"], // ORDER
  'group_by' => ['my_table.id'],
  'limit'    => 50,
  'start'    => 20
]));
```

### MVC

```php
use bbn\X;
/** @var bbn\Mvc\Controller $ctrl */

// the/path/to/the/controller
X::adump($ctrl->getPath()); 

// The corresponding (= same path) model
X::adump($ctrl->getModel()); 

// Another model to which we send data
X::adump($ctrl->getModel('another/model', ['some' => 'data']));

X::adump(
  // HTML view with same path (in html)
  $ctrl->getView(), 
  // with data sent to js
  $ctrl->getView('another/view', 'js', ['my_data' => 'my_value']), 
  // encapsulated in a script tag
  $ctrl->getJs('another/view', ['my_data' => 'my_value']), 
  // compiles and returns the Less code from the same path (in css)
  $ctrl->getLess(), 
  // The post data
  $ctrl->post,
  // The get data 
  $ctrl->get, 
  // The files array (revisited)
  $ctrl->files, 
  // an array of each bit of the path which are not part of (=after) the controller 
  $ctrl->arguments, path
  // an associative array that will be sent to the model if nothiung else is sent
  $ctrl->data, 
  // Adds properties to $ctrl->data
  $ctrl->addData(['my' => 'var']) 
  // Moves the request to another controller
  $ctrl->reroute('another/route') 
  // Includes another controller
  $ctrl->add('another/controller', ['some' => 'data']), 
  // Includes a private controller (unaccessible through URL)
  $ctrl->add('another/controller', [], true),
  // timer will be a property of the $ctrl->inc property, also available in the subsequent models
  $ctrl->addInc('timer', new bbn\Util\Timer()) 
);

// The most useful functions:

// Fetches for everything related to the current controller (model, html, js, css) and combines the results into a single object ($ctrl->obj). That's the typical function for showing a page
$ctrl->combo("My page title");

// Fetches the corresponding model and returns its result as an object. Typically used for write operations.
$ctrl->action();

// The second parameter allows the javascript to access the model's data
$ctrl->combo("My page title", true);

// Here the second parameter is the data sent to javascript
$ctrl->combo("My page title", ['my' => 'data']);

?>
```
#### When the javascript can access the data, it will be differently available

##### If the anonymous function returns a **function**, the data will be its **second argument**

```javascript
(() => {
  return (container, data) => {
    if (data && data.success && data.color) {
      container.style.color = '#' + data.color;
    }
  };
})();
```

##### If the anonymous function returns an **object**, the data will reside in the **source property**

```javascript
(() => {
  return {
    computed: {
      realColor() {
        return '#' + this.source.color
      }
    }
  };
})();
```

##### Example of an HTML view

```html
<div style="color: #{{color}}">Hello world</div>
```

##### Example of a PHP view

```php
<div style="color: #<?= $color ?>"><?= _("Hello world") ?></div>
```




### Option

The option system is built in a database with a table having the following structure:

|Name|Description|
|---|---|
|`id`|is the primary key|
|`id_parent`|has a constraint to `id`. It is nullable but all options but one (the `root`) should have it set|
|`text`|Is a string which should be the title of the option|
|`code`|is a `varchar` which forms a unique key associated with `id_parent`, so 2 same codes can't co-exist with a same parent, except if they are `NULL`|
|`num`|is the position of the option among its siblings, if the parent option is orderable|
|`id_alias`|has also a constraint on `id` but is never mandatory. It is a reference to another option|
|`value`|(JSON) is whatever properties the option will hold; when you get an option you won't see `value` but all the properties you will get which are not in the aforementioned columns come from `value`|
|`cfg`|(JSON) is the configuration of the option defines how the children, or the whole tree below, will be fetched and displayed. The properties can be:<br>- `show_code`   The code matters<br>- `show_alias`  The alias might matter<br>- `show_value`  The value contains stuff and thre is no schema<br>- `orderable`   If true the num will be used for the options' order<br>- `schema`      An array of object describing the different properties held in value<br>- `language`    A language set so the options can be translated<br>- `children`    Allows the option to have children<br>- `inheritance` Sets if these rules apply to children, children + grand-children, or all lineage<br>- `permissions` True if the options below should have a permission<br>- `default`     The default value among the children<br>- `scfg`        A similar configuration object to apply to grand-children|


The `code` system allows us to find an option just by its codes path.  
For example the sequence of codes `permissions`, `ide`, `appui` targets:
- in the option which has code `appui` whose parent is the `root`
- in the option which has code `ide`
- the option which has code `permissions`

The order is reversed to go from the most precise to the most general when in fact the sequence is:
`root` :arrow_right: `appui` :arrow_right: `ide` :arrow_right: `permissions`

```php
use bbn\X;
/** @var bbn\Appui\Option $option */


// Returns the option ID from its code sequence
X::adump($option->fromCode('permissions', 'ide', 'appui')); 
// The whole option with the same arguments (which work for all fetching functions)
X::adump($option->option('permissions', 'ide', 'appui')); 

// It works also with the ID:
$id_option = $option->fromCode('permissions', 'ide', 'appui');
X::adump($option->option($id_option));
// ID is a 32 hex value, so a code shouldn't look like one
// If the last parameter is an ID, it will take this ID as the root
X::adump($option->option('test', 'page', $id_option));
// Is the same as
X::adump($option->option('test', 'page', 'permissions', 'ide', 'appui'));

// Then you can fetch options (i.e. the children of an option) in many different ways
X::adump(
  // Only the IDs, in the right order if orderable
  $option->items($id_option),
  // Only the IDs, text, and code if applicable
  $option->options($id_option),
  // All the option properties (but cfg)
  $option->fullOptions($id_option),
  // Same as options but with an items property holding the lineage
  $option->tree($id_option),
  // Same as fullOptions but with an items property holding the lineage
  $option->Fulltree($id_option),
  // Returns the code: permissions
  $option->code($id_option),
  // Returns the text
  $option->text($id_option),
  // You can insert whaever you like
  $option->add(['id_parent' => $id_option, 'text' => 'Hello', 'myProp' => 'myValue'])
);
?>
```
