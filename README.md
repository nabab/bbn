

<p  align="center"><img  alt="BBN PHP"  src="https://bbn.io/logo/black/text/php.svg"  style="max-width: 40%; height: auto"></p>

bbn
===

## The PHP library used by [app-UI](https://app-ui.com)

## 📦 Installation

You can install the library via **Composer**:

```json
{
  "require": {
    "bbn/bbn": "dev/master"
  }
}
```

---

## 🚀 Overview

**bbn** is a PHP library designed for **Single Page Applications (SPA)**.  
It provides a comprehensive set of tools, including:

- ⚙️ **MVC framework**
- 🧠 **Powerful ORM**
  - Supports database structure analysis
  - Provides numerous data retrieval methods
- 🗂️ **Options class** – the foundation for many app-UI features
- 🌐 **API integrations** for:
  - Virtualmin, Cloudmin, GitHub, GitLab, payment gateways, and more
- 🕒 **History class** – track and revert database changes
- 🖼️ **File manipulation utilities** for:
  - Files, images, and PDFs
- 🧭 **Filesystem explorer**
- ⏱️ **Automated task management system**
- ⚡ **Universal caching system**
- 🧱 **HTML generation classes**
- 👥 **User and group management**
- 🧩 **Parsers** for PHP, JavaScript, and VueJS components

### 🧰 app-UI Specific Features

- Notes  
- Media manager  
- Chat  
- Clipboard  
- CMS  
- Dashboard  
- Database management and synchronization  
- I.D.E.  
- Automated mailings  
- Internationalization (i18n)  
- Masking system for text  
- Notification system  
- Data observers  
- Password management  
- Planning and event management  
- Project and workflow management  
- Statistics system  
- Static helper methods for all kinds of data

...and **many other features!**

---

## ⚙️ Framework Architecture

The **bbn** framework works with a **router** and a few configuration files.  
An **installer** will be released in the future.

> 🧑‍💻 There is still a lot of ongoing work regarding code review, translations, and documentation.  
> Contributions are welcome!

---

## 📁 Typical Directory Structure

```
app-ui/
├── data/
├── src/
│   ├── cfg/
│   │   ├── environments.yml
│   │   ├── settings.yml
│   │   └── custom2.php
│   ├── cli/
│   ├── components/
│   ├── lib/
│   ├── locale/
│   ├── mvc/
│   │   ├── css/
│   │   ├── html/
│   │   ├── js/
│   │   ├── model/
│   │   ├── private/
│   │   └── public/
│   ├── plugins/
│   ├── router.php
│
├── public_html/
│   ├── .htaccess
│   └── index.php
```

---

## 🔄 Request Lifecycle

### 1️⃣ Redirection

1. Request:  
   → `https://myapp.com/just/testing` (which does not exist)

2. `.htaccess` rewrites all missing files to `index.php`.

3. `index.php` changes directory to `src/` (outside the public root).

4. The **router** (in `src/`) is loaded — usually symlinked from `vendor`.

---

### 2️⃣ Routing

1. The framework identifies its environment from:
   - `hostname`
   - `app_path` in `src/cfg/environment.yml`

2. Constants are defined and **autoload** is initialized.

3. Classes are instantiated based on configuration.

4. The **MVC** class looks for the appropriate controller in:
   - `src/mvc/public/just/testing/`

   Depending on the request type:

   - **Landing page (GET):**
     ```
     src/mvc/public/just/testing/index.php
     ```
   - **POST request:**
     ```
     src/mvc/public/just/testing.php
     ```

   If not found, it moves up the hierarchy:
   ```
   src/mvc/public/just/index.php
   src/mvc/public/index.php
   ```
   or for POST:
   ```
   src/mvc/public/just.php
   ```

   If none found → **404**.

---

### 3️⃣ Execution

1. Optional `src/custom1.php` is included with `$bbn->mvc`.
2. If not in CLI mode:
   - A session is started.
   - Optional `src/custom2.php` is included (`$bbn` may have `mvc`, `user`, `session`).
3. The MVC includes the controller.

---

### 4️⃣ Output

1. Output buffer becomes the `content` property of the response.
2. Optional `src/custom3.php` is included (with `$bbn->obj`).
3. Depending on request type:
   - **Landing page (GET):** returns `content` with HTML headers.
   - **POST request:** returns JSON-encoded `mvc->obj`.
4. If `obj` contains a file or image, response headers are set accordingly.

---

## 🧠 Response Format

When clicking a link (handled by `bbn-js` and `bbn-vue`), the framework returns a **JSON object** with the following properties:

| Name | Description |
|------|--------------|
| `content` | HTML string injected into a container |
| `title` | Page title (prepended to site title) |
| `css` | CSS string inserted as a `<style>` tag |
| `script` | JavaScript function or VueJS anonymous component |
| `data` | Data object accessible by JavaScript |

> The frontend libraries `bbn-js` and `bbn-vue` handle all I/O:  
> They intercept local links, send POST requests, and process the JSON responses.

---

## ⚡ Quick Start Example

Below is a minimal example showing how to set up and run a simple **bbn** application.

### 🏗️ Project Structure

```
my-app/
├── composer.json
├── src/
│   ├── cfg/
│   │   ├── environments.yml
│   │   └── settings.yml
│   ├── mvc/
│   │   └── public/
│   │       └── hello/
│   │           └── index.php
│   └── router.php
└── public_html/
    ├── .htaccess
    └── index.php
```

### ⚙️ Configuration

#### `composer.json`
```json
{
  "require": {
    "bbn/bbn": "dev/master"
  }
}
```

#### `src/cfg/environments.yml`
```yaml
environments:
  dev:
    host: localhost
    app_path: /path/to/my-app/src
    db:
      engine: mysql
      host: 127.0.0.1
      user: root
      pass: root
      dbname: myapp
```

#### `src/cfg/settings.yml`
```yaml
mode: dev
timezone: UTC
locale: en
```

### 🧩 Router

#### `src/router.php`
```php
<?php
require __DIR__ . '/../vendor/autoload.php';
$router = new \bbn\Mvc\Router();
$router->run();
```

### 🌐 Entry Point

#### `public_html/.htaccess`
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

#### `public_html/index.php`
```php
<?php
chdir(__DIR__ . '/../src/');
require 'router.php';
```

### 🧱 Controller

#### `src/mvc/public/hello/index.php`
```php
<?php
/** @var bbn\Mvc\Model $model */
return [
  'title' => 'Hello World',
  'content' => '<h1>Welcome to bbn!</h1>'
];
```

### ▶️ Running the App

1. Install dependencies:
   ```bash
   composer install
   ```
2. Start a local PHP server:
   ```bash
   php -S localhost:8080 -t public_html
   ```
3. Visit [http://localhost:8080/hello](http://localhost:8080/hello)

You should see:

> **Hello World — Welcome to bbn!**

### 🧩 Next Steps

- Explore the MVC structure under `src/mvc/`
- Connect your database and start using the ORM
- Integrate with `bbn-vue` or `bbn-js` for dynamic SPA behavior

---

## 🤝 Contributing

We welcome contributions of all kinds — code, documentation, tests, translations, or ideas.  
Your help is greatly appreciated in making **bbn** better for everyone.

### 🧭 How to Contribute

1. **Fork** the repository  
2. **Create a new branch** for your feature or fix:  
   ```bash
   git checkout -b feature/my-new-feature
   ```
3. **Make your changes** and ensure that everything is working  
4. **Commit** your changes with a meaningful message:  
   ```bash
   git commit -m "Add support for XYZ feature"
   ```
5. **Push** your branch to your fork:  
   ```bash
   git push origin feature/my-new-feature
   ```
6. **Open a Pull Request** to the `master` branch  

### 🧹 Code Guidelines

- Follow **PSR-12** coding standards  
- Use clear and consistent **naming conventions**  
- Add **type hints** and **PHPDoc** comments where appropriate  
- Keep functions **small and focused**  
- Write **unit tests** for new features whenever possible  

### 🧪 Testing

Run the test suite using:

```bash
composer test
```

> Please ensure that all tests pass before submitting a pull request.

### 🌍 Translations

We’re progressively translating the framework and documentation.  
If you’d like to contribute translations, you can:
- Add new language files under `src/locale/`
- Improve existing translations

### 🧾 Documentation

Help us improve the documentation by:
- Fixing typos or outdated examples  
- Expanding missing sections  
- Adding code samples or tutorials  

All documentation files are located in the `/docs` folder (coming soon).

### 🗓️ Roadmap & Issues

Check out the **Issues** section for:
- Ongoing bug reports  
- Feature requests  
- Upcoming milestones  

You can also start a new discussion or suggest improvements.

### 💡 Need Help?

If you encounter problems or have questions:
- Open a **GitHub Issue**
- Or reach out via our community channels (coming soon)



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
  $ctrl->arguments,
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

// Transform all input (get, post, files) data into a single data array
// Fetches the corresponding model with this data
// and returns its result as an object.
// Typically used for write operations.
$ctrl->action();

// The second parameter allows the javascript to access the model's data
$ctrl->combo("My page title", true);

// Here the second parameter is the data sent to javascript
$ctrl->combo("My page title", ['my' => 'data']);

?>
```
#### Accessing the data through javascript

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

#### The HTML views are server-rendered and therefore  can by default access all the data

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
|`cfg`|(JSON) is the configuration of the option defines how the children, or the whole tree below, will be fetched and displayed. The properties can be:<br>- `show_code`   The code matters<br>- `relations`  The kind of relation the alias will embody if any<br>- `show_value`  The value contains stuff and thre is no schema<br>- `orderable`   If true the num will be used for the options' order<br>- `schema`      An array of object describing the different properties held in value<br>- `language`    A language set so the options can be translated<br>- `children`    Allows the option to have children<br>- `inheritance` Sets if these rules apply to children, children + grand-children, or all lineage<br>- `permissions` True if the options below should have a permission<br>- `default`     The default value among the children<br>- `scfg`        A similar configuration object to apply to grand-children|


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
  $option->fullTree($id_option),
  // Returns the code: permissions
  $option->code($id_option),
  // Returns the text
  $option->text($id_option),
  // You can insert whaever you like
  $option->add(['id_parent' => $id_option, 'text' => 'Hello', 'myProp' => 'myValue'])
);
```
