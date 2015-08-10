<?php

/*
 * This file is part of the SensioLabs Security Checker.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SensioLabs\Security;

use SensioLabs\Security\Exception\RuntimeException;
use SensioLabs\Security\Crawler\CrawlerInterface;
use SensioLabs\Security\Crawler\DefaultCrawler;

class SecurityChecker
{
    private $vulnerabilityCount;
    private $ignoredVulnerabilityCount;
    private $crawler;
    private $whitelistPath;

    public function __construct(CrawlerInterface $crawler = null)
    {
        $this->crawler = null === $crawler ? new DefaultCrawler() : $crawler;
    }

    /**
     * Checks a composer.lock file.
     *
     * @param string $lock The path to the composer.lock file
     *
     * @return array An array of vulnerabilities
     *
     * @throws RuntimeException When the lock file does not exist
     * @throws RuntimeException When the certificate can not be copied
     */
    public function check($lock)
    {
        if (is_dir($lock) && file_exists($lock.'/composer.lock')) {
            $lock = $lock.'/composer.lock';
        } elseif (preg_match('/composer\.json$/', $lock)) {
            $lock = str_replace('composer.json', 'composer.lock', $lock);
        }

        if (!is_file($lock)) {
            throw new RuntimeException('Lock file does not exist.');
        }

        list($this->vulnerabilityCount, $vulnerabilities) = $this->crawler->check($lock);

        if ($this->whitelistPath) {
            $cveIgnoreArray = json_decode(file_get_contents($this->whitelistPath));
            foreach ($vulnerabilities as $name => &$package) {
                $package['ignore'] = true;

                foreach ($package['advisories'] as $advisoryPath => &$advisory) {
                    if (in_array($advisory['cve'], $cveIgnoreArray) ) {
                        $advisory['ignore'] = true;
                        $this->ignoredVulnerabilityCount++;
                    } else {
                        $advisory['ignore'] = false;
                        $package['ignore'] = false;
                    }
                }

                if ($package['ignore'] === true) {
                    $this->vulnerabilityCount--;
                }
            }
        }

        return $vulnerabilities;
    }

    public function setWhitelistPath($path)
    {
        $this->whitelistPath = $path;
    }

    public function getLastVulnerabilityCount()
    {
        return $this->vulnerabilityCount;
    }

    public function getLastIgnoredVulnerabilityCount()
    {
        return $this->ignoredVulnerabilityCount;
    }

    public function getWhitelistPath()
    {
        return $this->whitelistPath;
    }
}
