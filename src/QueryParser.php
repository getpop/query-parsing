<?php
namespace PoP\QueryParsing;

class QueryParser implements QueryParserInterface
{
    /**
     * Parse elements by a separator, not failing whenever the separator is also inside the fieldArgs (i.e. inside the brackets "(" and ")")
     * Eg 1: Split elements by "|": ?query=id|posts(limit:3,order:title|ASC)
     * Eg 2: Split elements by ",": ?query=id,posts(ids:1175,1152).id|title
     * Adapted from https://stackoverflow.com/a/1084924
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
        // Both $ignoreSkippingFromChar and $ignoreSkippingUntilChar must be provided to be used, only 1 cannot
        if (is_null($ignoreSkippingFromChar) || is_null($ignoreSkippingUntilChar)) {
            $ignoreSkippingFromChar = $ignoreSkippingUntilChar = null;
        }
        // If there is any character that is both in $skipFromChars and $skipUntilChars, then allow only 1 instance of it for starting/closing
        // Potential eg: "%" for demarcating variables
        $skipFromUntilChars = array_intersect(
            $skipFromChars,
            $skipUntilChars
        );
        $isInsideSkipFromUntilChars = [];
        $charPos=-1;
        while ($charPos<$len-1) {
            $charPos++;
            $char = $query[$charPos];
            if ($char == $ignoreSkippingFromChar) {
                // Search the closing symbol and shortcut to that position (eg: opening then closing quotes for strings)
                // If it is not there, then treat this char as a normal char
                // Eg: search:with some quote " is ok
                $restStrIgnoreSkippingUntilCharPos = strpos($query, $ignoreSkippingUntilChar, $charPos+1);
                if ($restStrIgnoreSkippingUntilCharPos !== false) {
                    // Add this stretch of string into the buffer
                    $buffer .= substr($query, $charPos, $restStrIgnoreSkippingUntilCharPos-$charPos+1);
                    // Continue iterating from that position
                    $charPos = $restStrIgnoreSkippingUntilCharPos;
                    continue;
                }
            } elseif (in_array($char, $skipFromUntilChars)) {
                // If first occurrence, flag that from now on we start ignoring the chars, so everything goes to the buffer
                if (!$isInsideSkipFromUntilChars[$char]) {
                    $isInsideSkipFromUntilChars[$char] = true;
                    $depth++;
                } else {
                    // If second occurrence, flag it as false
                    $isInsideSkipFromUntilChars[$char] = false;
                    $depth--;
                }
            } elseif (in_array($char, $skipFromChars)) {
                $depth++;
            } elseif (in_array($char, $skipUntilChars)) {
                if ($depth) {
                    $depth--;
                } else {
                    // If there can only be one occurrence of "()", then ignore any "(" and ")" found in between other "()"
                    // Then, we can search by strings like this (notice that the ".", "(" and ")" inside the search are ignored):
                    // /api/?query=posts(searchfor:(.)).id|title
                    $restStr = substr($query, $charPos+1);
                    $restStrEndBracketPos = strpos($restStr, $skipUntilChars[0]);
                    $restStrSeparatorPos = strpos($restStr, $separator);
                    if ($restStrEndBracketPos === false || ($restStrSeparatorPos >= 0 && $restStrEndBracketPos >= 0 && $restStrEndBracketPos > $restStrSeparatorPos)) {
                        $depth--;
                    }
                }
            } elseif ($char == $separator) {
                if (!$depth) {
                    if ($buffer !== '') {
                        $stack[] = $buffer;
                        $buffer = '';
                        // If we need only one occurrence, then already return.
                        if ($onlyFirstOcurrence) {
                            $restStr = substr($query, $charPos+1);
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
