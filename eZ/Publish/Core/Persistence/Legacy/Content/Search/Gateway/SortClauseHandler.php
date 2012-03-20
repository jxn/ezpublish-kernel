<?php
/**
 * File containing the EzcDatabase sort clause handler class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Legacy\Content\Search\Gateway;
use eZ\Publish\Core\Persistence\Legacy\Content\Search\Gateway,
    eZ\Publish\Core\Persistence\Legacy\EzcDbHandler,
    eZ\Publish\API\Repository\Values\Content\Query\SortClause,
    eZ\Publish\API\Repository\Values\Content\Query,
    ezcQuerySelect;

/**
 * Handler for a single sort clause
 */
abstract class SortClauseHandler
{
    /**
     * Database handler
     *
     * @var eZ\Publish\Core\Persistence\Legacy\EzcDbHandler
     */
    protected $dbHandler;

    /**
     * Creates a new criterion handler
     *
     * @param EzcDbHandler $dbHandler
     */
    public function __construct( EzcDbHandler $dbHandler )
    {
        $this->dbHandler = $dbHandler;
    }

    /**
     * Check if this sort clause handler accepts to handle the given sort clause.
     *
     * @param SortClause $sortClause
     * @return bool
     */
    abstract public function accept( SortClause $sortClause );

    /**
     * Apply selects to the query
     *
     * Returns the name of the (aliased) column, which information should be
     * used for sorting.
     *
     * @param \ezcQuerySelect $query
     * @param SortClause $sortClause
     * @param int $number
     * @return string
     */
    abstract public function applySelect( ezcQuerySelect $query, SortClause $sortClause, $number );

    /**
     * applies joins to the query
     *
     * @param \ezcQuerySelect $query
     * @param SortClause $sortClause
     * @param int $number
     * @return void
     */
    abstract public function applyJoin( ezcQuerySelect $query, SortClause $sortClause, $number );

    /**
     * Returns the quoted sort column name
     *
     * @param int $number
     * @return string
     */
    protected function getSortColumnName( $number )
    {
        return $this->dbHandler->quoteIdentifier( 'sort_column_' . $number );
    }

    /**
     * Returns the sort table name
     *
     * @param int $number
     * @return string
     */
    protected function getSortTableName( $number )
    {
        return 'sort_table_' . $number;
    }
}

