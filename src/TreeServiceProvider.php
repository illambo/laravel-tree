<?php

namespace Nevadskiy\Tree;

use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Nevadskiy\Tree\Database\BuilderMixin;

class TreeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerBuilderMixin();
        $this->registerLtreeType();
        $this->registerLtreeColumn();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishMigrations();
    }

    /**
     * Register the query builder mixin.
     */
    private function registerBuilderMixin(): void
    {
        Builder::mixin(new BuilderMixin());
    }

    /**
     * Register the "ltree" column type for database.
     */
    private function registerLtreeType(): void
    {
        Grammar::macro('typeLtree', function () {
            return 'ltree';
        });
    }

    /**
     * Register the "ltree" column on the blueprint.
     */
    private function registerLtreeColumn(): void
    {
        Blueprint::macro('ltree', function (string $name) {
            return $this->addColumn('ltree', $name);
        });
    }

    /**
     * Publish any package migrations.
     */
    private function publishMigrations(): void
    {
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'pgsql-ltree-migration');
    }
}
