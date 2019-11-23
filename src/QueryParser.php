<?php
namespace PoP\QueryParsing;

class QueryParser implements QueryParserInterface
{
    /**
     * Parse elements by a separator, not failing whenever the separator is also inside the fieldArgs (i.e. inside the brackets "(" and ")")
     * Eg 1: Split elements by "|": ?query=id|posts(limit:3,order:title|ASC)
     * Eg 2: Split elements by ",": ?query=id,posts(ids:1175,1152).id|title
     * Taken from https://stackoverflow.com/a/1084924
     */
    public function splitElements(string $query, string $separator = ',', $skipFromChars = '(', $skipUntilChars = ')', $ignoreSkippingFromChar = null, $ignoreSkippingUntilChar = null, bool $onlyFirstOcurrence = false): array
    {
        $buffer = '';
        $stack = array();
        $depth = 0;
        $len = strlen($query);
        if (!is_array($skipFromChars)) {
            $skipFromChars = [$skipFromChars];
        }
        if (!is_array($skipUntilChars)) {
            $skipUntilChars = [$skipUntilChars];
        }
        // If there is any character that is both in $skipFromChars and $skipUntilChars, then allow only 1 instance of it for starting/closing
        // Potential eg: "%" for demarcating variables
        $skipFromUntilChars = array_intersect(
            $skipFromChars,
            $skipUntilChars
        );
        $isInsideSkipFromUntilChars = [];
        // Use variable $ignore to indicate when a string starts, which can include any symbol, including separators and skipFrom/UntilChars
        // Eg: quotes for strings: ". A string "this is (a) string" will say to ignore everything before '"' and '"'
        // If the 2 characters are the same, use it as a toggle
        $toggleIgnore = $ignoreSkippingFromChar == $ignoreSkippingUntilChar;
        $ignore = false;
        for ($i=0; $i<$len; $i++) {
            $char = $query[$i];
            if (!$ignore && $char == $ignoreSkippingFromChar && !is_null($ignoreSkippingUntilChar)) {
                // Check that the closing symbol appears on the rest of the string (eg: opening then closing quotes for strings)
                // If it doesn't, then treat it as a normal char
                // Eg: search:with some quote " is ok
                $restStr = substr($query, $i+1);
                $restStrIgnoreSkippingUntilCharPos = strpos($restStr, $ignoreSkippingUntilChar);
                if ($restStrIgnoreSkippingUntilCharPos !== false) {
                    $ignore = true;
                }
            } elseif ($char == $ignoreSkippingFromChar || $char == $ignoreSkippingUntilChar) {
                // Eg: search:"(these brackets are ignored so are part of the string)"
                if ($toggleIgnore) {
                    $ignore = !$ignore;
                } elseif ($char == $ignoreSkippingFromChar) {
                    $ignore = true;
                } else {
                    $ignore = false;
                }
            } elseif (!$ignore && in_array($char, $skipFromUntilChars)) {
                // If first occurrence, flag that from now on we start ignoring the chars, so everything goes to the buffer
                if (!$isInsideSkipFromUntilChars[$char]) {
                    $isInsideSkipFromUntilChars[$char] = true;
                    $depth++;
                } else {
                    // If second occurrence, flag it as false
                    $isInsideSkipFromUntilChars[$char] = false;
                    $depth--;
                }
            } elseif (!$ignore && in_array($char, $skipFromChars)) {
                $depth++;
            } elseif (!$ignore && in_array($char, $skipUntilChars)) {
                if ($depth) {
                    $depth--;
                } else {
                    // If there can only be one occurrence of "()", then ignore any "(" and ")" found in between other "()"
                    // Then, we can search by strings like this (notice that the ".", "(" and ")" inside the search are ignored):
                    // /api/?query=posts(searchfor:(.)).id|title
                    $restStr = substr($query, $i+1);
                    $restStrEndBracketPos = strpos($restStr, $skipUntilChars[0]);
                    $restStrSeparatorPos = strpos($restStr, $separator);
                    if ($restStrEndBracketPos === false || ($restStrSeparatorPos >= 0 && $restStrEndBracketPos >= 0 && $restStrEndBracketPos > $restStrSeparatorPos)) {
                        $depth--;
                    }
                }
            } elseif (!$ignore && $char == $separator) {
                if (!$depth) {
                    if ($buffer !== '') {
                        $stack[] = $buffer;
                        $buffer = '';
                        // If we need only one occurrence, then already return.
                        if ($onlyFirstOcurrence) {
                            $restStr = substr($query, $i+1);
                            $stack[] = $restStr;
                            return $stack;
                        }
                    }
                    continue;
                }
            }
            $buffer .= $char;
        }
        if ($buffer !== '') {
            $stack[] = $buffer;
        }

        return $stack;
    }
}
