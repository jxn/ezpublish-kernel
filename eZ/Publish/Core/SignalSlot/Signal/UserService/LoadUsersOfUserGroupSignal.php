<?php
/**
 * LoadUsersOfUserGroupSignal class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\SignalSlot\Signal\UserService;
use eZ\Publish\Core\SignalSlot\Signal;

/**
 * LoadUsersOfUserGroupSignal class
 * @package eZ\Publish\Core\SignalSlot\Signal\UserService
 */
class LoadUsersOfUserGroupSignal extends Signal
{
    /**
     * UserGroup
     *
     * @var eZ\Publish\API\Repository\Values\User\UserGroup
     */
    public $userGroup;

    /**
     * Offset
     *
     * @var mixed
     */
    public $offset;

    /**
     * Limit
     *
     * @var mixed
     */
    public $limit;

}
