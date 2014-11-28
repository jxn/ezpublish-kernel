<?php
/**
 * File containing the Elasticsearch abstract Mapper class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Elasticsearch\Content\Search;

use eZ\Publish\SPI\Search\Field;
use eZ\Publish\SPI\Persistence\Content;
use eZ\Publish\SPI\Persistence\Content\Location;
use eZ\Publish\SPI\Persistence\Content\Section;
use eZ\Publish\SPI\Search\FieldType;
use eZ\Publish\SPI\Persistence\Content\Type;
use eZ\Publish\Core\Persistence\Solr\Content\Search\FieldRegistry;
use eZ\Publish\SPI\Persistence\Content\Handler as ContentHandler;
use eZ\Publish\SPI\Persistence\Content\Location\Handler as LocationHandler;
use eZ\Publish\SPI\Persistence\Content\Type\Handler as ContentTypeHandler;
use eZ\Publish\SPI\Persistence\Content\ObjectState\Handler as ObjectStateHandler;
use eZ\Publish\SPI\Persistence\Content\Section\Handler as SectionHandler;

/**
 * Mapper maps Content and Location objects to a Document object, representing a
 * document in Elasticsearch index storage.
 *
 * Note that custom implementations might need to be accompanied by custom mappings.
 */
abstract class Mapper
{
    /**
     * Maps given Content by given $contentId to a Document.
     *
     * @param int|string $contentId
     *
     * @return \eZ\Publish\Core\Persistence\Elasticsearch\Content\Search\Document
     */
    abstract public function mapContentById( $contentId );

    /**
     * Maps given Content to a Document.
     *
     * @param \eZ\Publish\SPI\Persistence\Content $content
     *
     * @return \eZ\Publish\Core\Persistence\Elasticsearch\Content\Search\Document
     */
    abstract public function mapContent( Content $content );

    /**
     * Maps given Location to a Document.
     *
     * Returned Document represents a "parent" Location document searchable with Location Search.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\Location $location
     *
     * @return \eZ\Publish\Core\Persistence\Elasticsearch\Content\Search\Document
     */
    abstract public function mapLocation( Location $location );
}
