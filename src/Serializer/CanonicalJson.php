<?php

declare(strict_types=1);

/*
 * Schema.org Structured Data
 *
 * Package: vtinnovations/schema-org
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTinnovations\SchemaOrg\Serializer;

/**
 * Deterministic JSON form shared with the issuer (profile "canonical-json-v1").
 *
 *   1. the top level "signature" member is removed before signing/verifying;
 *   2. object members are sorted bytewise ascending, recursively;
 *   3. list order is preserved exactly;
 *   4. UTF-8, no pretty printing, no escaped slashes, no escaped Unicode;
 *   5. scalar types are preserved (false is not "false", null is not 0).
 *
 * Documents are decoded into stdClass rather than associative arrays on purpose:
 * an associative decode cannot tell an empty object from an empty list, which
 * would change the bytes that go into the signature.
 */
final class CanonicalJson
{
    public const MAX_DEPTH = 64;

    private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_THROW_ON_ERROR;

    /**
     * @throws \JsonException when the bytes are not a JSON object
     */
    public static function decodeObject(string $bytes): \stdClass
    {
        $value = json_decode($bytes, false, self::MAX_DEPTH, JSON_THROW_ON_ERROR);

        if (!$value instanceof \stdClass) {
            throw new \JsonException('Expected a JSON object.');
        }

        return $value;
    }

    /**
     * @throws \JsonException
     */
    public static function encode(mixed $value): string
    {
        return json_encode(self::normalise($value), self::ENCODE_FLAGS, self::MAX_DEPTH);
    }

    /**
     * Canonical bytes that a detached signature is computed over: the document
     * without its own signature member.
     *
     * @throws \JsonException
     */
    public static function signedForm(\stdClass $document, string $omitMember = 'signature'): string
    {
        $copy = clone $document;
        unset($copy->{$omitMember});

        return self::encode($copy);
    }

    private static function normalise(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $members = get_object_vars($value);
            uksort($members, static fn (string $a, string $b): int => strcmp($a, $b));

            $sorted = new \stdClass();
            foreach ($members as $name => $member) {
                $sorted->{$name} = self::normalise($member);
            }

            return $sorted;
        }

        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::normalise($item), $value);
        }

        return $value;
    }
}
