<?php

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;

class JSON extends ScalarType
{
    public string $name = 'JSON';

    public ?string $description = 'Arbitrary JSON data.';

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    public function parseLiteral($valueNode, ?array $variables = null): mixed
    {
        return $this->parseLiteralValue($valueNode);
    }

    private function parseLiteralValue($valueNode): mixed
    {
        if ($valueNode instanceof StringValueNode) {
            return $valueNode->value;
        }

        if ($valueNode instanceof IntValueNode) {
            return (int) $valueNode->value;
        }

        if ($valueNode instanceof FloatValueNode) {
            return (float) $valueNode->value;
        }

        if ($valueNode instanceof BooleanValueNode) {
            return $valueNode->value;
        }

        if ($valueNode instanceof NullValueNode) {
            return null;
        }

        if ($valueNode instanceof ListValueNode) {
            return array_map(fn ($node) => $this->parseLiteralValue($node), iterator_to_array($valueNode->values));
        }

        if ($valueNode instanceof ObjectValueNode) {
            $result = [];
            foreach ($valueNode->fields as $field) {
                $result[$field->name->value] = $this->parseLiteralValue($field->value);
            }
            return $result;
        }

        throw new Error('Cannot parse literal value for JSON scalar.');
    }
}
