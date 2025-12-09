<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\PHPStan\Rules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

use function preg_match;
use function preg_match_all;

/**
 * PHPStan rule to detect 'mixed' type in array shapes and annotations
 *
 * @implements Rule<Node>
 */
final class NoMixedInArrayShapeRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $docComment = null;

        if ($node instanceof ClassMethod || $node instanceof Function_ || $node instanceof Property) {
            $docComment = $node->getDocComment();
        }

        if (! $docComment instanceof Doc) {
            return [];
        }

        $text = $docComment->getText();
        $errors = [];

        // Check for mixed in array type annotations
        // Patterns: array<string, mixed>, array<mixed>, list<mixed>, etc.
        if (preg_match('/array\s*<[^>]*\bmixed\b[^>]*>/', $text)) {
            $errors[] = RuleErrorBuilder::message(
                'Array type contains "mixed". Use clear array shapes (e.g. array{key: string}) and/or DTOs.',
            )->identifier('noMixed.arrayShape')->build();
        }

        // Check for mixed in @param annotations
        if (preg_match_all('/@param\s+[^\s]*\bmixed\b/', $text, $matches)) {
            foreach ($matches[0] as $match) {
                $errors[] = RuleErrorBuilder::message(
                    'Parameter annotation contains "mixed". Use clear array shapes and/or DTOs.',
                )->identifier('noMixed.param')->build();
            }
        }

        // Check for mixed in @return annotations
        if (preg_match('/@return\s+[^\s]*\bmixed\b/', $text)) {
            $errors[] = RuleErrorBuilder::message(
                'Return annotation contains "mixed". Use clear array shapes and/or DTOs.',
            )->identifier('noMixed.return')->build();
        }

        // Check for mixed in @var annotations
        if (preg_match('/@var\s+[^\s]*\bmixed\b/', $text)) {
            $errors[] = RuleErrorBuilder::message(
                'Variable annotation contains "mixed". Use clear array shapes and/or DTOs.',
            )->identifier('noMixed.var')->build();
        }

        return $errors;
    }
}
