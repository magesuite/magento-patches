<?php

// Usage
// php get_magento_versions.php - get all magento module reqs for every magneto version
// php get_magento_versions.php '2.2' - select only magento 2.2.X versions
// php get_magento_versions.php '2.2.[1-2]' - select magento 2.2.1 and 2.2.2 using this regex
// php get_magento_versions.php '2.3' 'magento/module-newsletter' -  show only magento/module-newsletter deps for 2.3.X magento versions

class Repo {

    const REPO_URL = 'https://f52fd8699d9ce4d436289cdad01b256a:f8f11de4a4c6b95223b2566789586f9b@repo.magento.com/packages.json';
    const REPO_FILE = './tmp.repo.magento.com.json';

    private $repoData;

    public function __construct()
    {
        $this->repoData = $this->getRepoData();
    }

    private function getRepoData()
    {
        if (!file_exists(self::REPO_FILE)) {
            file_put_contents(self::REPO_FILE, file_get_contents(self::REPO_URL));
        }

        return json_decode(file_get_contents(self::REPO_FILE), true);
    }

    public function getPackageData($packageName)
    {
        if (!isset($this->repoData['packages'][$packageName])) {
            return null;
        }

        return $this->repoData['packages'][$packageName];
    }

    public function getPackageVersions($packageName, $match = null)
    {
        if (null === $package = $this->getPackageData($packageName)) {
            return null;
        }

        $versions = array_keys($package);

        if (null !== $match) {
            $versions = array_filter($versions, function($verString) use ($match) {
                return preg_match($match, $verString);
            });
        }

        return $versions;
    }

    public function getPackageRequires($packageName, $packageVersion, $match = null)
    {
        if (!isset($this->repoData['packages'][$packageName][$packageVersion]['require'])) {
            return null;
        }

        $require = $this->repoData['packages'][$packageName][$packageVersion]['require'];

        if (null !== $match) {
            $require = array_filter($require, function($version, $name) use ($match) {
                return preg_match($match, $name);
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $require;
    }
}



$repo = new Repo();


$requestedMagentoVersion = isset($argv[1]) ? $argv[1] : null;
$packageName = isset($argv[2]) ? $argv[2] : null;


$magentoVersions = $repo->getPackageVersions(
    'magento/product-community-edition',
    $requestedMagentoVersion ? '/^' . str_replace('.', '.', $requestedMagentoVersion) .'/' : null
);


sort($magentoVersions);

foreach ($magentoVersions as $foundMagentoVersion) {
    if (!$packageName) {
        echo "--- magento/product-community-edition [$foundMagentoVersion] ---\n";
    }

    $requires = $repo->getPackageRequires(
        'magento/product-community-edition',
        $foundMagentoVersion,
        $packageName ? '~^' . $packageName . '$~' : '~^magento/~'
    );

    ksort($requires);

    foreach ($requires as $name => $version) {
        echo "$name [$version]";

        if ($packageName) {
            echo "  (from magento/product-community-edition [$foundMagentoVersion])";
        }

        echo "\n";
    }
}


