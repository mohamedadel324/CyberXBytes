<?php

namespace Dedoc\Scramble\Support\Generator;

trait WithAttributes
{
    /** @var array<string, mixed> */
    private $attributes = [];

    /**
     * @param  mixed  $value
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function getAttribute(string $key, mixed $default = null)
    {
        if ($this->hasAttribute($key)) {
            return $this->attributes[$key];
        }

        return $default;
    }

    public function attributes()
    {
        return $this->attributes;
    }

    public function mergeAttributes($attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }
}
