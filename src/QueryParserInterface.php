<?php

declare(strict_types=1);

namespace PoP\QueryParsing;

interface QueryParserInterface
{
    public function splitElements(string $query, string $separator = ',', $skipFromChars = '(', $skipUntilChars = ')', $ignoreSkippingFromChar = null, $ignoreSkippingUntilChar = null): array;
}
