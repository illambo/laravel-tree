<?php

namespace Nevadskiy\Tree\Tests;

use Illuminate\Database\Eloquent\Builder;
use Nevadskiy\Tree\Exceptions\CircularReferenceException;
use Nevadskiy\Tree\Tests\App\Category;
use Nevadskiy\Tree\Tests\Database\Factories\CategoryFactory;
use Nevadskiy\Tree\Tests\Database\Factories\CategoryWithCustomSourceColumnFactory;
use RuntimeException;

class CategoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_path_attribute(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        self::assertEquals(3, $category->getPath()->getDepth());
        self::assertEquals($category->parent->parent->getPathSource(), $category->getPath()->segments()[0]);
        self::assertEquals($category->parent->getPathSource(), $category->getPath()->segments()[1]);
        self::assertEquals($category->getPathSource(), $category->getPath()->segments()[2]);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_path_attribute_is_not_a_path_instance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "path" is not a Path instance.');

        CategoryFactory::new()->create(['path' => '1.2.3']);
    }

    /**
     * @test
     */
    public function it_assigns_path_when_nullable_path_is_provided(): void
    {
        $category = CategoryFactory::new()->create(['path' => null]);

        self::assertEquals($category->getKey(), $category->getPath()->segments()->first());
    }

    /**
     * @test
     */
    public function it_has_relation_with_parent_category(): void
    {
        $parent = CategoryFactory::new()->create();

        $category = CategoryFactory::new()
            ->forParent($parent)
            ->create();

        self::assertTrue($category->parent->is($parent));
    }

    /**
     * @test
     */
    public function it_has_relation_with_children_categories(): void
    {
        $parent = CategoryFactory::new()->create();

        $children = CategoryFactory::new()
            ->forParent($parent)
            ->count(3)
            ->create();

        self::assertCount(3, $parent->children);
        self::assertTrue($parent->children[0]->is($children[0]));
        self::assertTrue($parent->children[1]->is($children[1]));
        self::assertTrue($parent->children[2]->is($children[2]));
    }

    /**
     * @test
     */
    public function it_has_relation_with_ancestor_categories(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(3)
            ->create();

        $this->assertDatabaseCount('categories', 4);

        self::assertCount(3, $category->ancestors);
        self::assertNotContains($category, $category->ancestors);
    }

    /**
     * @test
     */
    public function it_has_relation_with_descendant_categories(): void
    {
        $root = CategoryFactory::new()->create();

        [$child] = CategoryFactory::new()
            ->forParent($root)
            ->count(2)
            ->create();

        [$descendant] = CategoryFactory::new()
            ->forParent($child)
            ->count(2)
            ->create();

        self::assertCount(4, $root->descendants);
        self::assertTrue($root->descendants->contains($child));
        self::assertTrue($root->descendants->contains($descendant));
    }

    /**
     * @test
     */
    public function it_handles_correctly_similar_paths_in_descendants(): void
    {
        $parent = CategoryWithCustomSourceColumnFactory::new()->create(['name' => 'a']);

        $child = CategoryWithCustomSourceColumnFactory::new()
            ->forParent($parent)
            ->create(['name' => 'b']);

        $anotherChild = CategoryWithCustomSourceColumnFactory::new()
            ->forParent($parent)
            ->create(['name' => 'bc']);

        self::assertCount(0, $child->descendants);
    }

    /**
     * @test
     */
    public function it_can_be_ordered_by_depth_asc(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(3)
            ->create();

        $categories = Category::query()->orderByDepth()->get();

        self::assertTrue($categories->last()->is($category));
        self::assertEquals(1, $categories->first()->getPath()->getDepth());
        self::assertEquals(4, $categories->last()->getPath()->getDepth());
    }

    /**
     * @test
     */
    public function it_can_be_ordered_by_depth_desc(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(3)
            ->create();

        $categories = Category::query()->orderByDepthDesc()->get();

        self::assertTrue($categories->first()->is($category));
        self::assertEquals(1, $categories->last()->getPath()->getDepth());
        self::assertEquals(4, $categories->first()->getPath()->getDepth());
    }

    /**
     * @test
     */
    public function it_can_be_filtered_by_depth(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $categories = Category::query()->whereDepth(2)->get();

        self::assertCount(1, $categories);
        self::assertTrue($categories->first()->is($category->parent));
        self::assertEquals(2, $categories->first()->getPath()->getDepth());
    }

    /**
     * @test
     */
    public function it_can_be_filtered_by_depth_using_custom_operator(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $categories = Category::query()->whereDepth(2, '>=')->get();

        self::assertCount(2, $categories);
        self::assertTrue($categories[0]->is($category->parent));
        self::assertTrue($categories[1]->is($category));
    }

    /**
     * @test
     */
    public function it_can_be_filtered_by_root(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $categories = Category::query()->whereRoot()->get();

        self::assertCount(1, $categories);
        self::assertTrue($categories->first()->is($category->parent->parent));
        self::assertEquals(1, $categories->first()->getPath()->getDepth());
    }

    /**
     * @test
     */
    public function it_joins_ancestors_to_node_collection(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $categories = $category->joinAncestors();

        self::assertCount(3, $categories);
        self::assertTrue($categories[0]->is($category));
        self::assertTrue($categories[1]->is($category->parent));
        self::assertTrue($categories[2]->is($category->parent->parent));
    }

    /**
     * @test
     */
    public function it_eager_loads_category_with_ancestors(): void
    {
        CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        Category::query()->getConnection()->enableQueryLog();

        $categories = Category::query()
            ->with('ancestors')
            ->get();

        self::assertCount(3, $categories);
        self::assertCount(0, $categories[0]->ancestors);
        self::assertCount(1, $categories[1]->ancestors);
        self::assertCount(2, $categories[2]->ancestors);
        self::assertCount(2, Category::query()->getConnection()->getQueryLog());
    }

    /**
     * @test
     */
    public function it_eager_loads_category_with_descendants(): void
    {
        CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        Category::query()->getConnection()->enableQueryLog();

        $categories = Category::query()
            ->with('descendants')
            ->get();

        self::assertCount(3, $categories);
        self::assertCount(2, $categories[0]->descendants);
        self::assertCount(1, $categories[1]->descendants);
        self::assertCount(0, $categories[2]->descendants);
        self::assertCount(2, Category::query()->getConnection()->getQueryLog());
    }

    /**
     * @test
     */
    public function it_updates_path_of_subtree_when_parent_category_is_changed(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $anotherCategory = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $category->parent->parent()->associate($anotherCategory);
        $category->parent->save();

        $category->refresh();

        self::assertEquals(5, $category->getPath()->getDepth());
        self::assertEquals($anotherCategory->parent->parent->getPathSource(), $category->getPath()->segments()[0]);
    }

    /**
     * @test
     */
    public function it_updates_path_of_subtree_when_category_moves_to_root(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $category->parent->parent()->associate(null);
        $category->parent->save();

        $category->refresh();

        self::assertEquals($category->parent->getPathSource(), $category->getPath()->segments()[0]);
        self::assertEquals(1, $category->parent->getPath()->getDepth());
        self::assertEquals(2, $category->getPath()->getDepth());
    }

    /**
     * @test
     */
    public function it_detects_circular_dependency(): void
    {
        $category = CategoryFactory::new()->create();

        $anotherCategory = CategoryFactory::new()
            ->forParent($category)
            ->create();

        $this->expectException(CircularReferenceException::class);

        $category->parent()->associate($anotherCategory);
        $category->save();
    }

    /**
     * @test
     */
    public function it_detects_circular_dependency_when_category_is_moved_inside_itself(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $this->expectException(CircularReferenceException::class);

        $category->parent->parent->parent()->associate($category);
        $category->parent->parent->save();
    }

    /**
     * @test
     */
    public function it_can_determine_whether_parent_is_changed(): void
    {
        $category = CategoryFactory::new()->create();

        self::assertFalse($category->isParentChanged());

        $anotherCategory = CategoryFactory::new()
            ->forParent($category)
            ->create();

        self::assertFalse($anotherCategory->fresh()->isParentChanged());

        $anotherCategory->parent()->associate(null);
        $anotherCategory->save();

        self::assertTrue($anotherCategory->isParentChanged());
    }

    /**
     * @test
     */
    public function it_can_determine_whether_parent_is_changing(): void
    {
        $category = CategoryFactory::new()->create();

        self::assertFalse($category->isParentChanging());

        $anotherCategory = CategoryFactory::new()
            ->forParent($category)
            ->create();

        self::assertFalse($anotherCategory->isParentChanging());

        $anotherCategory->parent()->associate(null);

        self::assertTrue($anotherCategory->isParentChanging());

        $anotherCategory->save();

        self::assertFalse($anotherCategory->isParentChanging());
    }

    /**
     * @test
     */
    public function it_can_filter_items_by_ancestors_using_where_has_method(): void
    {
        $clothing = CategoryFactory::new()->create(['name' => 'Clothing']);

        $accessories = CategoryFactory::new()
            ->forParent($clothing)
            ->create(['name' => 'Accessories']);

        $belts = CategoryFactory::new()
            ->forParent($accessories)
            ->create(['name' => 'Belts']);

        $categories = Category::query()
            ->whereHas('ancestors', function (Builder $query) {
                $query->where('name', 'like', 'Clo%');
            })
            ->get();

        self::assertCount(2, $categories);
        self::assertTrue($categories->contains($belts));
        self::assertTrue($categories->contains($accessories));
        self::assertFalse($categories->contains($clothing));
    }

    /**
     * @test
     */
    public function it_can_filter_items_by_descendants_using_where_has_method(): void
    {
        $clothing = CategoryFactory::new()->create(['name' => 'Clothing']);

        $accessories = CategoryFactory::new()
            ->forParent($clothing)
            ->create(['name' => 'Accessories']);

        $belts = CategoryFactory::new()
            ->forParent($accessories)
            ->create(['name' => 'Belts']);

        $categories = Category::query()
            ->whereHas('descendants', function (Builder $query) {
                $query->where('name', 'like', 'Bel%');
            })
            ->get();

        self::assertCount(2, $categories);
        self::assertTrue($categories->contains($clothing));
        self::assertTrue($categories->contains($accessories));
        self::assertFalse($categories->contains($belts));
    }

    /**
     * @test
     */
    public function it_filters_root_nodes(): void
    {
        $parent = CategoryFactory::new()->create();

        CategoryFactory::new()
            ->forParent($parent)
            ->create();

        $categories = Category::query()
            ->get()
            ->root();

        self::assertCount(1, $categories);
        self::assertTrue($categories->contains($parent));
    }

    /**
     * @test
     */
    public function it_does_not_include_categories_with_similar_id_to_its_ancestor(): void
    {
        $parent = CategoryWithCustomSourceColumnFactory::new()->create(['name' => '1']);

        $similarParent = CategoryWithCustomSourceColumnFactory::new()->create(['name' => '11']);

        $child = CategoryFactory::new()
            ->forParent($parent)
            ->create(['name' => '2']);

        self::assertCount(1, $child->ancestors);
        self::assertTrue($child->ancestors[0]->is($parent));
    }

    /**
     * @test
     */
    public function it_does_include_self_using_where_self_or_ancestor_of_method(): void
    {
        $category = CategoryFactory::new()
            ->withAncestors(2)
            ->create();

        $ancestors = Category::query()->whereSelfOrAncestorOf($category)->get();

        self::assertCount(3, $ancestors);
        $this->assertTrue($ancestors->contains($category->getKeyName(), $category->getKey()));
    }
}
