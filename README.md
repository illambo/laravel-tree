[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct-single.svg)](https://stand-with-ukraine.pp.ua)

# 🌳 Tree-like structure for Eloquent models

[![PHPUnit](https://img.shields.io/github/actions/workflow/status/nevadskiy/laravel-tree/phpunit.yml?branch=master)](https://packagist.org/packages/nevadskiy/laravel-tree)
[![Code Coverage](https://img.shields.io/codecov/c/github/nevadskiy/laravel-tree?token=9X6AQQYCPA)](https://packagist.org/packages/nevadskiy/laravel-tree)
[![Latest Stable Version](https://img.shields.io/packagist/v/nevadskiy/laravel-tree)](https://packagist.org/packages/nevadskiy/laravel-tree)
[![License](https://img.shields.io/github/license/nevadskiy/laravel-tree)](https://packagist.org/packages/nevadskiy/laravel-tree)

It is a powerful solution that enabled you to create hierarchical structures for your Eloquent models.
It leverages the "materialized path" pattern to represent the hierarchy of your data.
It can be used for a wide range of use cases such as managing categories, nested comments, and more.

## 🍬 Features

[//]: # (@todo)
- Move subtree using a single query

## 🔌 Installation

Install the package via Composer:

```bash
composer require nevadskiy/laravel-tree
````

[//]: # (## Demo)

[//]: # (todo)

## ✨ How it works

When working with hierarchical data structures in your application, storing the structure using a self-referencing `parent_id` column is a common approach.  
While it works well for many use cases, it can become challenging when you need to make complex queries, such as finding all descendants of a given node.
The [materialized path](#materialized-path) pattern provides a simple and effective solution.

### Materialized path

The "materialized pattern" involves storing the full path of each node in the hierarchy in a separate `path` column as a string. 
The ancestors of each node are represented by a series of IDs separated by a delimiter.

For example, the categories database table might look like this:

| id | name           | parent_id |  path |
|:---|:---------------|----------:|------:|
| 1  | Science        |      null |     1 |
| 2  | Physics        |         1 |   1.2 |
| 3  | Mechanics      |         2 | 1.2.3 |
| 4  | Thermodynamics |         2 | 1.2.4 |

With this structure, you can easily retrieve all descendants of a node using a SQL query:

```SQL
SELECT * FROM categories WHERE path LIKE '1.%'
```

#### PostgreSQL Ltree extension

Using the [PostgreSQL ltree](https://www.postgresql.org/docs/current/ltree.html) extension we can go even further. This extension provides an additional `ltree` field type specifically for purposes such as a path in a hierarchy.
In combination with a GiST index it allows executing lightweight and performant queries across an entire tree.

Now the SQL query will look like this:

```SQL
SELECT * FROM categories WHERE path ~ '1.*'
```

## 🔨 Configuration

[//]: # (todo add info about `parent_id` column)

All you have to do is to add a `AsTree` trait to the model and add a `path` column to the model's table.

Let's get started by configuring a `Category` model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nevadskiy\Tree\AsTree;

class Category extends Model
{
    use AsTree;
}
```

Next, create a migration for the model. The `path` column definition depends on your database connection:

#### Using PostgreSQL database

To add a `path` column with the `ltree` type and a GiST index, use the following code:

```php
$table->ltree('path')->nullable()->spatialIndex();
```

The complete migration file may look like this:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->ltree('path')->nullable()->spatialIndex(); // Create a "path" column with a "ltree" type and a GiST index.
            $table->timestamps();
        });

        // Add a self-referencing "parent_id" column with a "foreign key" constraint using a separate database query.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->index()
                ->constrained('categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

Sometimes the Ltree extension may be disabled in PostgreSQL. To enable it, you can publish and run a package migration:

[//]: # (todo rename tag)

```bash
php artisan vendor:publish --tag=tree-migrations
```

#### Using MySQL database

Create a new migration for the `categories` table:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path')->nullable()->index();
            $table->timestamps();
        });

        // Add a self-referencing "parent_id" column with a "foreign key" constraint using a separate database query.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->index()
                ->constrained('categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

## 🚊 Usage

Once you have configured your model, the package automatically handles all manipulations with the `path` column based on the parent, so you do not need to set it manually.

### Inserting models

To insert a root node, simply save the model to the database as usual:

```php
$root = new Category();
$root->name = 'Books';
$root->save();
```

To insert a child model, you only need to assign the `parent_id` attribute or use the `parent` relation like this:

```php
$child = new Category;
$child->name = 'Science';
$child->parent()->associate($root);
$child->save();
```

As you can see, it works as with regular Eloquent models.

### Relations

The `AsTree` trait provides the following relations:

- [`parent`](#parent)
- [`children`](#children)
- [`ancestors`](#ancestors) (read-only)
- [`descendants`](#descendants) (read-only)

The `parent` and `children` relations use default Laravel relations BelongsTo and HasMany.

The `ancestors` and `descendants` can be used only in the "read" mode, which means methods like `make` or `create` are not available. 
So to save related nodes you need to use the `parent` or `children` relation.

#### Parent

The `parent` relation uses the default Eloquent BelongsTo relation that needs the `parent_id` column as a foreign key.
It allows getting a parent of the node.

```php
echo $category->parent->name;
```

#### Children

The `children` relation uses a default Eloquent HasMany relation and is a reverse relation to the `parent`.
It allows getting all children of the node.

```php
foreach ($category->children as $child) {
    echo $child->name;
}
```

#### Ancestors

The `ancestors` relation is a custom relation that works only in "read" mode. 
It allows getting all ancestors of the node (without the current node).

Using the attribute:

```php
foreach ($category->ancestors as $ancestor) {
    echo $ancestor->name;
}
```

Using the query builder:

```php
$ancestors = $category->ancestors()->get();
```

Getting a collection with the current node and its ancestors:

```php
$hierarchy = $category->joinAncestors();
```

#### Descendants

The `descendants` relation is a custom relation that works only in "read" mode.
It allows getting all descendants of the node (without the current node).

Using the attribute:

```php
foreach ($category->descendants as $descendant) {
    echo $descendant->name;
}
```

Using the query builder:

```php
$ancestors = $category->descendants()->get();
```

### Querying models

Getting root nodes:

```php
$roots = Category::query()->root()->get(); 
```

Getting nodes by the depth level:

```php
$categories = Category::query()->whereDepth(3)->get(); 
```

Getting ancestors of the node (including the current node):

```php
$ancestors = Category::query()->whereSelfOrAncestorOf($category)->get();
```

Getting descendants of the node (including the current node):

```php
$ancestors = Category::query()->whereSelfOrDescendantOf($category)->get();
```

Ordering nodes by depth:

```php
$categories = Category::query()->orderByDepth()->get();
$categories = Category::query()->orderByDepthDesc()->get();
```

### HasManyDeep

The package provides the `HasManyDeep` relation that can be used to link, for example, a `Category` model that uses the `AsTree` trait with a `Product` model.

That allows us to get products of a category and each of its descendants.

Here is the code example on how to use the `HasManyDeep` relation:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nevadskiy\Tree\AsTree;
use Nevadskiy\Tree\Relations\HasManyDeep;

class Category extends Model
{
    use AsTree;

    public function products(): HasManyDeep
    {
        return HasManyDeep::between($this, Product::class);
    }
}
```

Now you can get products:

```php
$products = $category->products()->paginate(20);
```

### Querying category products

You can easily get the products of a category and each of its descendants using a query builder.

1st way (recommended):

```php
$products = Product::query()
    ->join('categories', function (JoinClause $join) {
        $join->on(Product::qualifyColumn('category_id'), Category::qualifyColumn('id'));
    })
    ->whereSelfOrDescendantOf($category);
    ->paginate(25, [
        Product::qualifyColumn('*')
    ]);
```

2nd way (slower):

```php
$products = Product::query()
    ->whereHas('category', function (Builder $query) use ($category) {
        $query->whereSelfOrDescendantOf($category);
    })
    ->paginate(25);
```

### Moving nodes

When you move a node, the `path` column of the node and each of its descendants have to be updated as well.
Luckily the package does this automatically using a single query when it detects that the `parent_id` column has been updated.

So basically to move a node with its subtree you need to update the `parent` node of the current node:

```php
$books = Category::query()->where('name', 'Books')->firstOrFail();
$science = Category::query()->where('name', 'Science')->firstOrFail();

$science->parent()->associate($books);
$science->save();
```

### Other examples

[//]: # (@todo add list with all available builder methods)

#### Building a tree

To build a tree, we need to call the `tree` method on the `NodeCollection`:

```php
$tree = Category::query()->orderBy('name')->get()->tree();
```

This method associates nodes using the `children` relation and returns only root nodes.

#### Building breadcrumbs

```php
echo $category->joinAncestors()->reverse()->implode('name', ' > ');
```

#### Deleting a subtree

Delete the current node and all its descendants:

```php
$category->newQuery()->whereSelfOrDescendantOf($category)->delete();
```

## 📚 Useful links

- https://www.postgresql.org/docs/current/ltree.html
- https://patshaughnessy.net/2017/12/13/saving-a-tree-in-postgres-using-ltree
- https://patshaughnessy.net/2017/12/14/manipulating-trees-using-sql-and-the-postgres-ltree-extension

## ☕ Contributing

Thank you for considering contributing. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for more information.

## 📜 License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.

[//]: # (@todo doc ordering)
[//]: # (@todo doc available build methods)
[//]: # (@todo doc postgres uuid and dashes)
[//]: # (@todo doc custom query where['path', '~', '*.1.*]()
