<?php
namespace PoP\QueryParsing\Facades\Parsers;

use PoP\QueryParsing\Parsers\QueryParserInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class QueryParserFacade
{
    public static function getInstance(): QueryParserInterface
    {
        return ContainerBuilderFactory::getInstance()->get('query_parser');
    }
}
