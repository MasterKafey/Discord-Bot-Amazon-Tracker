<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class ArrayToStringTransformer implements DataTransformerInterface
{
    private string $delimiter;

    public function __construct(string $delimiter = ',')
    {
        $this->delimiter = $delimiter;
    }

    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        return implode($this->delimiter, $value);
    }

    public function reverseTransform($value)
    {
        if (!$value) {
            return [];
        }

        return array_map('trim', explode($this->delimiter, $value));
    }
}