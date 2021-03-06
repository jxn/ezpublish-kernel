<?php
/**
 * File containing the ConfigurationConverter class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */
namespace eZ\Bundle\EzPublishLegacyBundle\SetupWizard;

use eZ\Bundle\EzPublishLegacyBundle\DependencyInjection\Configuration\LegacyConfigResolver;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZINI;
use eZSiteAccess;
use Stash\Driver\FileSystem as FileSystemDriver;

/**
 * Handles conversionlegacy eZ Publish 4 parameters from a set of settings to a configuration array
 * suitable for yml dumping
 */
class ConfigurationConverter
{
    /**
     * @var \eZ\Bundle\EzPublishLegacyBundle\DependencyInjection\Configuration\LegacyConfigResolver
     */
    protected $legacyResolver;

    /**
     * @var \eZ\Publish\Core\MVC\Legacy\Kernel
     */
    protected $legacyKernel;

    /**
     * @var array
     */
    protected $supportedPackages;

    public function __construct( LegacyConfigResolver $legacyResolver, \Closure $legacyKernel, array $supportedPackages )
    {
        $this->legacyResolver = $legacyResolver;
        $this->legacyKernel = $legacyKernel();
        $this->supportedPackages = array_fill_keys( $supportedPackages, true );
    }

    /**
     * Converts from legacy settings to an array dumpable to ezpublish.yml
     * @param string $sitePackage Name of the chosen install package
     * @param string $adminSiteaccess Name of the admin siteaccess
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentException
     *
     * @return array
     */
    public function fromLegacy( $sitePackage, $adminSiteaccess )
    {
        $settings = array();
        $settings['ezpublish'] = array();
        $settings['ezpublish']['siteaccess'] = array();
        $defaultSiteaccess = $this->getParameter( 'SiteSettings', 'DefaultAccess' );
        $settings['ezpublish']['siteaccess']['default_siteaccess'] = $defaultSiteaccess;
        $siteList = $this->getParameter( 'SiteAccessSettings', 'AvailableSiteAccessList' );

        if ( !is_array( $siteList ) || empty( $siteList ) )
            throw new InvalidArgumentException( 'siteList', 'can not be empty' );

        if ( !in_array( $adminSiteaccess, $siteList ) )
            throw new InvalidArgumentException( "adminSiteaccess", "Siteaccess $adminSiteaccess wasn't found in SiteAccessSettings.AvailableSiteAccessList" );

        $settings['ezpublish']['siteaccess']['list'] = $siteList;
        $settings['ezpublish']['siteaccess']['groups'] = array();
        $groupName = $sitePackage . '_group';
        $settings['ezpublish']['siteaccess']['groups'][$groupName] = $siteList;
        $settings['ezpublish']['siteaccess']['match'] = $this->resolveMatching();
        $settings['ezpublish']['system'] = array();
        $settings['ezpublish']['system'][$groupName] = array();

        $settings['ezpublish']['system'][$defaultSiteaccess] = array();
        $settings['ezpublish']['system'][$adminSiteaccess] = array();

        // Database settings
        $databaseSettings = $this->getGroup( 'DatabaseSettings', 'site.ini', $defaultSiteaccess );
        $repositoryName = "{$defaultSiteaccess}_repository";
        $settings['doctrine'] = array(
            'dbal' => array(
                'connections' => array(
                    "{$repositoryName}_connection" => $this->getDoctrineSettings( $databaseSettings )
                )
            )
        );
        $settings['ezpublish']['repositories'] = array(
            $repositoryName => array( 'engine' => 'legacy', 'connection' => "{$repositoryName}_connection" )
        );
        $settings['ezpublish']['system'][$groupName]['repository'] = $repositoryName;

        // If package is not supported, all siteaccesses will have individually legacy_mode to true, forcing legacy fallback
        if ( !isset( $this->supportedPackages[$sitePackage] ) )
        {
            foreach ( $siteList as $siteaccess )
            {
                $settings['ezpublish']['system'][$siteaccess] = array( 'legacy_mode' => true );
            }
        }
        else
        {
            $settings['ezpublish']['system'][$adminSiteaccess] += array( 'legacy_mode' => true );
        }

        $languages = $this->getLanguages( $siteList, $groupName );
        foreach ( $languages as $siteaccess => $langSettings )
        {
            $settings['ezpublish']['system'][$siteaccess]['languages'] = $langSettings;
        }

        // FileSettings
        $settings['ezpublish']['system'][$groupName]['var_dir'] =
            $this->getParameter( 'FileSettings', 'VarDir', 'site.ini', $defaultSiteaccess );

        // we don't map the default FileSettings.StorageDir value
        $storageDir = $this->getParameter( 'FileSettings', 'StorageDir', 'site.ini', $defaultSiteaccess );
        if ( $storageDir !== 'storage' )
            $settings['ezpublish']['system'][$groupName]['storage_dir'] = $storageDir;

        // ImageMagick settings
        $imageMagickEnabled = $this->getParameter( 'ImageMagick', 'IsEnabled', 'image.ini', $defaultSiteaccess );
        if ( $imageMagickEnabled == 'true' )
        {
            $settings['ezpublish']['imagemagick']['enabled'] = true;
            $imageMagickExecutablePath = $this->getParameter( 'ImageMagick', 'ExecutablePath', 'image.ini', $defaultSiteaccess );
            $imageMagickExecutable = $this->getParameter( 'ImageMagick', 'Executable', 'image.ini', $defaultSiteaccess );
            $settings['ezpublish']['imagemagick']['path'] = rtrim( $imageMagickExecutablePath, '/\\' ) . '/' . $imageMagickExecutable;
        }
        else
        {
            $settings['ezpublish']['imagemagick']['enabled'] = false;
        }

        // Dump image variations for unsupported packages only (i.e. not ezdemo)
        // Image variations for ezdemo are defined in DemoBundle
        if ( !isset( $this->supportedPackages[$sitePackage] ) )
        {
            $variations = $this->getImageVariations( $siteList, $groupName );
            foreach ( $variations as $siteaccess => $imgSettings )
            {
                $settings['ezpublish']['system'][$siteaccess]['image_variations'] = $imgSettings;
            }
        }

        foreach ( $siteList as $siteaccess )
        {
            if (
                $this->getParameter( "Session", "SessionNameHandler", "site.ini", $siteaccess ) === "custom" &&
                $this->getParameter( "Session", "SessionNamePerSiteAccess", "site.ini", $siteaccess ) !== "enabled"
            )
            {
                $settings['ezpublish']['system'][$siteaccess]['session'] = array( 'name' => $this->getParameter( "Session", "SessionNamePrefix", "site.ini" ) );
            }
        }

        $settings['stash'] = $this->getStashCacheSettings();

        ksort( $settings );
        ksort( $settings['ezpublish'] );
        return $settings;
    }

    /**
     * Returns settings for Doctrine in respect to database settings coming from legacy.
     *
     * @param array $databaseSettings
     *
     * @return array
     */
    protected function getDoctrineSettings( array $databaseSettings )
    {
        $databaseMapping = array(
            'ezmysqli' => 'pdo_mysql',
            'eZMySQLiDB' => 'pdo_mysql',
            'ezmysql' => 'pdo_mysql',
            'eZMySQLDB' => 'pdo_mysql',
            'ezpostgresql' => 'pdo_pgsql',
            'eZPostgreSQL' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
            'pgsql' => 'pdo_pgsql',
            'oracle' => 'oci8',
            'ezoracle' => 'oci8'
        );

        $databaseType = $databaseSettings['DatabaseImplementation'];
        if ( isset( $databaseMapping[$databaseType] ) )
            $databaseType = $databaseMapping[$databaseType];

        $databasePassword = $databaseSettings['Password'] != '' ? $databaseSettings['Password'] : null;
        $doctrineSettings = array(
            'driver' => $databaseType,
            'host' => $databaseSettings['Server'],
            'user' => $databaseSettings['User'],
            'password' => $databasePassword,
            'dbname' => $databaseSettings['Database'],
            'charset' => 'UTF8'
        );
        if ( isset( $databaseSettings['Port'] ) && !empty( $databaseSettings['Port'] ) )
            $doctrineSettings['port'] = $databaseSettings['Port'];
        if ( isset( $databaseSettings['Socket'] ) && $databaseSettings['Socket'] !== 'disabled' )
            $doctrineSettings['unix_socket'] = $databaseSettings['Socket'];

        return $doctrineSettings;
    }

    /**
     * Returns cache settings based on which cache functionality is available on the current server
     *
     * Order of preference:
     * - FileSystem
     * - APC
     * - Memcache  [DISABLED, SEE INLINE]
     * - Xcache  [DISABLED, SEE INLINE]
     * - variable instance cache  [DISABLED, SEE INLINE]
     *
     * @return array
     */
    protected function getStashCacheSettings()
    {
        // Should only contain one out of the box
        $handlers = array();
        $inMemory = false;
        $handlerSetting = array();
        if ( FileSystemDriver::isAvailable() )
        {
            $handlers[] = 'FileSystem';
            $inMemory = true;
            // If running on Windows, use "crc32" keyHashFunction
            if ( stripos( php_uname(), 'win' ) === 0 )
            {
                $handlerSetting['FileSystem'] = array(
                    'keyHashFunction' => 'crc32'
                );
            }
        }
        else
        {
            // '/dev/null' fallback driver, no cache at all
            $handlers[] = 'BlackHole';
            $inMemory = true;
        }

        return array(
            'caches' => array(
                'default' => array(
                    'drivers' => $handlers,
                    // inMemory will enable/disable "Ephemeral", not allowed as separate handler in stash-bundle
                    'inMemory' => $inMemory,
                    'registerDoctrineAdapter' => false
                ) + $handlerSetting
            )
        );
    }

    /**
     * Returns the languages list for all siteaccess unless it's the same for
     * each one, in this case, it returns the languages list for the group.
     *
     * @param array $siteList
     * @param string $groupName
     *
     * @return array
     */
    protected function getLanguages( array $siteList, $groupName )
    {
        $result = array();
        $allSame = true;
        $previousSA = null;
        foreach ( $siteList as $siteaccess )
        {
            $result[$siteaccess] = $this->getParameter(
                'RegionalSettings', 'SiteLanguageList', 'site.ini', $siteaccess
            );
            if ( $allSame && $previousSA !== null )
            {
                $allSame = ( $result[$previousSA] === $result[$siteaccess] );
            }
            $previousSA = $siteaccess;
        }
        if ( $allSame )
        {
            return array( $groupName => $result[$previousSA] );
        }
        return $result;
    }

    /**
     * Returns the image variations settings for all siteaccess unless it's the
     * same for each one, in this case, it returns the variations settings for
     * the group. This avoids to duplicate the image variations settings
     *
     * @param array $siteList
     * @param string $groupName
     *
     * @return array
     */
    protected function getImageVariations( array $siteList, $groupName )
    {
        $result = array();
        $allSame = true;
        $previousSA = null;
        foreach ( $siteList as $siteaccess )
        {
            $result[$siteaccess] = $this->getImageVariationsForSiteaccess( $siteaccess );
            if ( $allSame && $previousSA !== null )
            {
                $allSame = ( $result[$previousSA] === $result[$siteaccess] );
            }
            $previousSA = $siteaccess;
        }
        if ( $allSame )
        {
            return array( $groupName => $result[$previousSA] );
        }
        return $result;
    }

    /**
     * Returns the image variation settings for the siteaccess
     *
     * @param string $siteaccess
     *
     * @return array
     */
    protected function getImageVariationsForSiteaccess( $siteaccess )
    {
        $variations = array();
        $imageAliasesList = $this->getGroup( 'AliasSettings', 'image.ini', $siteaccess );
        foreach ( $imageAliasesList['AliasList'] as $imageAliasIdentifier )
        {
            $variationSettings = array( 'reference' => null, 'filters' => array() );
            $aliasSettings = $this->getGroup( $imageAliasIdentifier, 'image.ini', $siteaccess );
            if ( isset( $aliasSettings['Reference'] ) && $aliasSettings['Reference'] != '' )
            {
                $variationSettings['reference'] = $aliasSettings['Reference'];
            }
            if ( isset( $aliasSettings['Filters'] ) && is_array( $aliasSettings['Filters'] ) )
            {
                // parse filters. Format: filtername=param1;param2...paramN
                foreach ( $aliasSettings['Filters'] as $filterString )
                {
                    $filteringSettings = array();

                    if ( strstr( $filterString, '=' ) !== false )
                    {
                        list( $filteringSettings['name'], $filterParams) = explode( '=', $filterString );
                        $filterParams = explode( ';', $filterParams );

                        // make sure integers are actually integers, not strings
                        array_walk(
                            $filterParams,
                            function ( &$value )
                            {
                                if ( preg_match( '/^[0-9]+$/', $value ) )
                                    $value = (int)$value;
                            }
                        );

                        $filteringSettings['params'] = $filterParams;
                    }
                    else
                    {
                        $filteringSettings['name'] = $filterString;
                    }

                    $variationSettings['filters'][] = $filteringSettings;
                }
            }
            $variations[$imageAliasIdentifier] = $variationSettings;
        }
        return $variations;
    }

    /**
     * Returns the contents of the legacy group $groupName. If $file and
     * $siteaccess are null, the global value is fetched with the legacy resolver.
     *
     * @param string $groupName
     * @param string|null $file
     * @param string|null $siteaccess
     *
     * @return array
     */
    public function getGroup( $groupName, $file = null, $siteaccess = null )
    {
        if ( $file === null && $siteaccess === null )
        {
            // in this case we want the "global" value, no need to use the
            // legacy kernel, the legacy resolver is enough
            return $this->legacyResolver->getGroup( $groupName );
        }
        return $this->legacyKernel->runCallback(
            function () use ( $file, $groupName, $siteaccess )
            {
                // @todo: do reset injected settings everytime
                // and make sure to restore the previous injected settings
                eZINI::injectSettings( array() );
                return eZSiteAccess::getIni( $siteaccess, $file )->group( $groupName );
            },
            false,
            false
        );
    }

    /**
     * Returns the value of the legacy parameter $parameterName in $groupName.
     * If $file and $siteaccess are null, the global value is fetched with the
     * legacy resolver.
     *
     * @param string $groupName
     * @param string $parameterName
     * @param string|null $file
     * @param string|null $siteaccess
     *
     * @return array
     */
    public function getParameter( $groupName, $parameterName, $file = null, $siteaccess = null )
    {
        if ( $file === null && $siteaccess === null )
        {
            // in this case we want the "global" value, no need to use the
            // legacy kernel, the legacy resolver is enough
            return $this->legacyResolver->getParameter( "$groupName.$parameterName" );
        }
        return $this->legacyKernel->runCallback(
            function () use ( $file, $groupName, $parameterName, $siteaccess )
            {
                // @todo: do reset injected settings everytime
                // and make sure to restore the previous injected settings
                eZINI::injectSettings( array() );
                return eZSiteAccess::getIni( $siteaccess, $file )
                    ->variable( $groupName, $parameterName );
            },
            false,
            false
        );
    }

    protected function resolveMatching()
    {
        $siteaccessSettings = $this->getGroup( 'SiteAccessSettings' );

        $matching = array(); $match = false;
        foreach ( explode( ';', $siteaccessSettings['MatchOrder'] ) as $matchMethod )
        {
            switch ( $matchMethod )
            {
                case 'uri':
                    $match = $this->resolveURIMatching( $siteaccessSettings );
                    break;
                case 'host':
                    $match = $this->resolveHostMatching( $siteaccessSettings );
                    break;
                case 'host_uri':
                    $match = false;
                    break;
                case 'port':
                    $match = array( 'Map\Port' => $this->getGroup( 'PortAccessSettings' ) );
                    break;
            }
            if ( $match !== false )
            {
                $matching = $match + $matching;
            }
        }
        return $matching;
    }

    protected function resolveUriMatching( $siteaccessSettings )
    {
        switch ( $siteaccessSettings['URIMatchType'] )
        {
            case 'disabled':
                return false;

            case 'map':
                return array( "Map\\URI" => $this->resolveMapMatch( $siteaccessSettings['URIMatchMapItems'] ) );

            case 'element':
                return array( "URIElement" => $siteaccessSettings['URIMatchElement'] );

            case 'text':
                return array( "URIText" => $this->resolveTextMatch( $siteaccessSettings, 'URIMatchSubtextPre', 'URIMatchSubtextPost' ) );

            case 'regexp':
                return array( "Regex\\URI" => array( $siteaccessSettings['URIMatchRegexp'], $siteaccessSettings['URIMatchRegexpItem'] ) );
        }
    }

    /**
     * Parses Legacy HostMatching settings to a matching array
     * @param mixed[] $siteaccessSettings
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\InvalidArgumentException
     *
     * @return array|bool
     */
    protected function resolveHostMatching( $siteaccessSettings )
    {
        switch ( $siteaccessSettings['HostMatchType'] )
        {
            case 'disabled':
                return false;

            case 'map':
                return array( "Map\\Host" => $this->resolveMapMatch( $siteaccessSettings['HostMatchMapItems'] ) );

            case 'element':
                return array( "HostElement" => $siteaccessSettings['HostMatchElement'] );

            case 'text':
                return array( "HostText" => $this->resolveTextMatch( $siteaccessSettings, 'HostMatchSubtextPre', 'HostMatchSubtextPost' ) );

            case 'regexp':
                return array( "Regex\\Host" => array( $siteaccessSettings['HostMatchRegexp'], $siteaccessSettings['HostMatchRegexpItem'] ) );

            default:
                throw new InvalidArgumentException( "HostMatchType", "Invalid value for legacy setting site.ini '{$siteaccessSettings['HostMatchType']}'" );
        }
    }

    protected function resolveTextMatch( $siteaccessSettings, $prefixKey, $suffixKey )
    {
        $settings = array();
        if ( isset( $siteaccessSettings[$prefixKey] ) )
            $settings['prefix'] = $siteaccessSettings[$prefixKey];
        if ( isset( $siteaccessSettings[$suffixKey] ) )
            $settings['suffix'] = $siteaccessSettings[$suffixKey];

        return $settings;
    }

    protected function resolveMapMatch( $mapArray )
    {
        $map = array();
        foreach ( $mapArray as $mapItem )
        {
            $elements = explode( ';', $mapItem );
            $map[$elements[0]] = count( $elements ) > 2 ? array_slice( $elements, 1 ) : $elements[1];
        }

        return $map;
    }
}
