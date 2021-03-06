<?php

namespace PUGX\BadgeBundle\Service;

use Packagist\Api\Client;
use Packagist\Api\Result\Package as ApiPackage;

use PUGX\BadgeBundle\Package\Package;
use PUGX\BadgeBundle\Package\PackageInterface;
use PUGX\BadgeBundle\Exception\UnexpectedValueException;

/**
 * This class is intended to load ApiPackage, create, and work with the Package object
 *
 * Class PackageHandler
 * @package PUGX\BadgeBundle\Service
 *
 */
class PackageManager
{
    private static $modifierRegex = '[._-]?(?:(stable|beta|b|RC|alpha|a|patch|pl|p)(?:[.-]?(\d+))?)?([.-]?dev)?';
    private static $packageClass;
    private $client;

    public function __construct(Client $packagistClient, $packageClass = '\PUGX\BadgeBundle\Package\Package')
    {
        self::$packageClass = $packageClass;
        $this->client = $packagistClient;
    }

    /**
     * Factory of Packages.
     *
     * @return PackageInterface
     */
    public static function instantiatePackage()
    {
        $package = self::$packageClass;

        return new $package();
    }

    /**
     * Create a new Package decorated with the Api Package.
     *
     * @param ApiPackage $apiPackage
     *
     * @return PackageInterface
     */
    public function instantiateAndDecoratePackage(ApiPackage $apiPackage)
    {
        $package = self::instantiatePackage();
        $package->setOriginalObject($apiPackage);

        return $package;
    }

    /**
     * Returns package if founded.
     *
     * @param string $repository
     *
     * @return Package
     *
     * @throws UnexpectedValueException
     */
    public function fetchPackage($repository)
    {
        $package = $this->client->get($repository);
        if ($package && $package instanceof ApiPackage) {
            // create a new Package from the ApiPackage
            return $this->instantiateAndDecoratePackage($package);
        }

        throw new UnexpectedValueException(sprintf('Impossible to found repository "%s"', $repository));
    }

    /**
     * Set the latest Stable and the latest Unstable version from a Package.
     *
     * @param Package $package
     *
     * @return Package
     */
    public function calculateLatestVersions(Package &$package)
    {
        $versions = $package->getVersions();

        foreach ($versions as $version) {

            $functionName = 'Unstable';
            if ('stable' == $this->parseStability($version->getVersion())) {
                $functionName = 'Stable';
            }

            if ($version->getVersion() > $package->{'getLatest' . $functionName . 'Version'}()) {
                $package->{'setLatest' . $functionName . 'Version'}($version->getVersion());
            }
        }

        return $package;
    }

    /**
     * Returns the stability of a version.
     *
     * This function is part of Composer.
     *
     * (c) Nils Adermann <naderman@naderman.de>
     * Jordi Boggiano <j.boggiano@seld.be>
     *
     * @param string $version
     *
     * @return string
     */
    public function parseStability($version)
    {
        $version = preg_replace('{#.+$}i', '', $version);

        if ('dev-' === substr($version, 0, 4) || '-dev' === substr($version, -4)) {
            return 'dev';
        }

        preg_match('{' . self::$modifierRegex . '$}i', strtolower($version), $match);
        if (!empty($match[3])) {
            return 'dev';
        }

        if (!empty($match[1])) {
            if ('beta' === $match[1] || 'b' === $match[1]) {
                return 'beta';
            }
            if ('alpha' === $match[1] || 'a' === $match[1]) {
                return 'alpha';
            }
            if ('rc' === $match[1]) {
                return 'RC';
            }
        }

        return 'stable';
    }

    /**
     * Take the Type of the Downloads (total, monthly or daily).
     *
     * @param Package $package
     * @param string  $type
     *
     * @return string
     */
    public function getPackageDownloads(Package $package, $type)
    {
        $statsType = 'get' . ucfirst($type);

        if ($package && ($download = $package->getDownloads()) && $download instanceof \Packagist\Api\Result\Package\Downloads) {
            return $download->{$statsType}();
        }
    }
}
