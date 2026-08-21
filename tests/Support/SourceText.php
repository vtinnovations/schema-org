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

namespace VTinnovations\SchemaOrg\Tests\Support;

/**
 * Lets the structural audits look at code rather than prose.
 *
 * Every file carries the project header, which names the licence and the
 * company website. Searching raw file text for words like those would match the
 * header in all of them and say nothing about what the code does.
 */
final class SourceText
{
    public static function withoutComments(string $code): string
    {
        $out = '';

        foreach (token_get_all($code, TOKEN_PARSE) as $token) {
            if (!\is_array($token)) {
                $out .= $token;

                continue;
            }

            [$id, $text] = $token;

            if (T_COMMENT === $id || T_DOC_COMMENT === $id) {
                $out .= str_contains($text, "\n") ? "\n" : ' ';

                continue;
            }

            $out .= $text;
        }

        return $out;
    }
}
