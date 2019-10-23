<?php
namespace PoP\QueryParsing\Facades;

use PoP\QueryParsing\QueryParserInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class QueryParserFacade
{
    public static function getInstance(): QueryParserInterface
    {
        return ContainerBuilderFactory::getInstance()->get('query_parser');
    }
}
