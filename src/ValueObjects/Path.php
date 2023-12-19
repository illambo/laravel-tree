<?php

namespace Nevadskiy\Tree\ValueObjects;

use Illuminate\Support\Collection;

class Path
{
    /**
     * The path's separator.
     */
    public const SEPARATOR = '.';

    /**
     * The path's value.
     *
     * @var string
     */
    private $value;

    /**
     * Build a path from the given segments.
     *
     * @param string|Path ...$segments
     */
    public static function from(...$segments): Path
    {
        return new static(
            collect($segments)
                ->map(function ($segment) {
                    if ($segment instanceof Path) {
                        return $segment->getValue();
                    }

                    return $segment;
                })
                ->implode(self::SEPARATOR)
        );
    }

    /**
     * Make a new path instance.
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Get the path's value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get segments of the path.
     */
    public function segments(): Collection
    {
        return collect($this->explode());
    }

    /**
     * Get the depth level of the path.
     */
    public function getDepth(): int
    {
        return count($this->explode());
    }

    /**
     * Explode a path to segments.
     */
    protected function explode(): array
    {
        return explode(self::SEPARATOR, $this->getValue());
    }

    /**
     * Convert the path into path set of ancestors including self.
     *
     * @todo rename
     * @example ["1", "1.2", "1.2.3", "1.2.3.4"]
     */
    public function getPathSet(): array
    {
        $output = [];

        $parts = $this->explode();

        for ($index = 1, $length = count($parts); $index <= $length; $index++) {
            $output[] = implode(self::SEPARATOR, array_slice($parts, 0, $index));
        }

        return $output;
    }

    /**
     * Convert the path into path set of ancestors excluding self.
     */
    public function getAncestorSet(): array
    {
        $output = $this->getPathSet();

        array_pop($output);

        return $output;
    }

    /**
     * Get string representation of the object.
     */
    public function __toString(): string
    {
        return $this->getValue();
    }
}
