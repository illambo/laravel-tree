<?php

namespace Nevadskiy\Tree\Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Nevadskiy\Tree\ValueObjects\Path;
use RuntimeException;

/**
 * @mixin Builder
 */
class BuilderMixin
{
    /**
     * The descendant SQL operator.
     */
    public const ANCESTOR = '@>';

    /**
     * The descendant SQL operator.
     */
    public const DESCENDANT = '<@';

    /**
     * Add an ancestor where clause to the query.
     */
    public function whereSelfOrAncestor(): callable
    {
        return function (string $column, Path $path, string $boolean = 'and') {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->where($column, BuilderMixin::ANCESTOR, $path, $boolean);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->whereIn($column, $path->getPathSet(), $boolean);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Add an ancestor where column clause to the query.
     */
    public function whereColumnSelfOrAncestor(): callable
    {
        return function (string $first, string $second, string $boolean = 'and') {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->whereColumn($first, BuilderMixin::ANCESTOR, $second, $boolean);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->whereRaw(sprintf('find_in_set(%s, path_to_ancestor_set(%s))', $first, $second), [], $boolean);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Add an ancestor "or where" clause to the query.
     */
    public function orWhereSelfOrAncestor(): callable
    {
        return function (string $column, Path $path) {
            return $this->whereSelfOrAncestor($column, $path, 'or');
        };
    }

    /**
     * Add an ancestor where clause to the query from the given model.
     */
    public function whereSelfOrAncestorOf(): callable
    {
        return function (Model $model, string $column = null, string $boolean = 'and') {
            return $this->whereSelfOrAncestor(
                $column ?: $model->newQuery()->qualifyColumn($model->getPathColumn()),
                $model->getPath(),
                $boolean
            );
        };
    }

    /**
     * Add an ancestor "or where" clause to the query from the given model.
     */
    public function orWhereSelfOrAncestorOf(): callable
    {
        return function (Model $model, string $column = null) {
            return $this->orWhereSelfOrAncestor(
                $column ?: $model->newQuery()->qualifyColumn($model->getPathColumn()),
                $model->getPath(),
            );
        };
    }

    /**
     * Add a descendant where clause to the query.
     */
    public function whereSelfOrDescendant(): callable
    {
        return function (string $column, Path $path, string $boolean = 'and') {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->where($column, BuilderMixin::DESCENDANT, $path, $boolean);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->where($column, 'like', "{$path}%", $boolean);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Add a descendant where column clause to the query.
     */
    public function whereColumnSelfOrDescendant(): callable
    {
        return function (string $first, string $second, string $boolean = 'and') {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->whereColumn($first, BuilderMixin::DESCENDANT, $second, $boolean);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->whereColumn($first, 'like', new Expression("concat({$second}, '%')"), $boolean);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Add a descendant "or where" clause to the query.
     */
    public function orWhereSelfOrDescendant(): callable
    {
        return function (string $column, Path $path) {
            return $this->whereSelfOrDescendant($column, $path, 'or');
        };
    }

    /**
     * Add a descendant where clause to the query from the given model.
     */
    public function whereSelfOrDescendantOf(): callable
    {
        return function (Model $model, string $column = null, string $boolean = 'and') {
            return $this->whereSelfOrDescendant(
                $column ?: $model->newQuery()->qualifyColumn($model->getPathColumn()),
                $model->getPath(),
                $boolean
            );
        };
    }

    /**
     * Add a descendant "or where" clause to the query from the given model.
     */
    public function orWhereSelfOrDescendantOf(): callable
    {
        return function (Model $model, string $column = null) {
            return $this->orWhereSelfOrDescendant(
                $column ?: $model->newQuery()->qualifyColumn($model->getPathColumn()),
                $model->getPath(),
            );
        };
    }

    /**
     * Filter records by the given depth level.
     */
    public function wherePathDepth(): callable
    {
        return function (string $column, int $depth, string $operator = '=') {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->where($this->compilePgsqlDepth($column), $operator, $depth);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->where($this->compileMysqlDepth($column), $operator, $depth);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Order records by a depth.
     */
    public function orderByPathDepth(): callable
    {
        return function (string $column, string $direction = 'asc') {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->orderBy($this->compilePgsqlDepth($column), $direction);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->orderBy($this->compileMysqlDepth($column), $direction);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Compile the PostgreSQL "depth" function for the given column.
     */
    protected function compilePgsqlDepth(): callable
    {
        return function (string $column) {
            return new Expression(sprintf('nlevel(%s)', $column));
        };
    }

    /**
     * Compile the MySQL "depth" function for the given column.
     */
    protected function compileMysqlDepth(): callable
    {
        return function (string $column, string $separator = Path::SEPARATOR) {
            return new Expression(vsprintf("(length(%s) - length(replace(%s, '%s', ''))) + 1", [
                $column, $column, $separator
            ]));
        };
    }

    public function rebuildPaths(): callable
    {
        return function (string $column, Path $path, ?Path $parentPath = null) {
            if ($this->getConnection() instanceof PostgresConnection) {
                return $this->update([
                    $column => is_null($parentPath)
                        ? new Expression($this->compilePgsqlSubPath($column, $path->getDepth()))
                        : new Expression($this->compilePgsqlConcat(
                            sprintf("'%s'", $parentPath->getValue() . Path::SEPARATOR),
                            $this->compilePgsqlSubPath($column, $path->getDepth())
                        ))
                ]);
            }

            if ($this->getConnection() instanceof MySqlConnection) {
                return $this->update([
                    $column => is_null($parentPath)
                        ? new Expression($this->compileMysqlSubPath($column, $path->getDepth()))
                        : new Expression($this->compileMysqlConcat(
                            sprintf("'%s'", $parentPath->getValue() . Path::SEPARATOR),
                            $this->compileMysqlSubPath($column, $path->getDepth())
                        ))
                ]);
            }

            throw new RuntimeException(vsprintf('Database connection [%s] is not supported.', [
                get_class($this->getConnection())
            ]));
        };
    }

    /**
     * Compile the PostgreSQL concat function.
     *
     * @todo refactor using array arguments.
     * @todo automatically format arguments if it is string or expression.
     */
    protected function compilePgsqlConcat(): callable
    {
        return function (string $first, string $second) {
            return vsprintf("%s || %s", [$first, $second]);
        };
    }

    /**
     * Compile the MySQL concat function.
     *
     * @todo refactor using array arguments.
     * @todo automatically format arguments if it is string or expression.
     */
    protected function compileMysqlConcat(): callable
    {
        return function (string $first, string $second) {
            return vsprintf("CONCAT(%s, %s)", [$first, $second]);
        };
    }

    /**
     * Compile the MySQL path of the subtree.
     */
    protected function compileMysqlSubPath(): callable
    {
        return function (string $column, int $depth) {
            if ($depth === 1) {
                return $column;
            }

            return vsprintf("substring(%s, length(substring_index(%s, '%s', %d)) + 2)", [
                $column,
                $column,
                Path::SEPARATOR,
                $depth - 1
            ]);
        };
    }

    /**
     * Compile the PostgreSQL path of the subtree.
     */
    protected function compilePgsqlSubPath(): callable
    {
        return function (string $column, int $depth) {
            if ($depth === 1) {
                return $column;
            }

            return vsprintf('subpath(%s, %d)', [$column, $depth]);
        };
    }
}
