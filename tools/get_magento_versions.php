<?php

// Usage
// php get_magento_versions.php - get all magento module reqs for every magneto version
// php get_magento_versions.php '2.2' - select only magento 2.2.X versions
// php get_magento_versions.php '2.2.[1-2]' - select magento 2.2.1 and 2.2.2 using this regex
// php get_magento_versions.php '2.3' 'magento/module-newsletter' -  show only magento/module-newsletter deps for 2.3.X magento versions

class Repo {

    const REPO_USERNAME = 'f52fd8699d9ce4d436289cdad01b256a';

    const REPO_PASSWORD = 'f8f11de4a4c6b95223b2566789586f9b';

    const REPO_URL = 'https://repo.magento.com/';

    const REPO_PACKAGES_FILENAME = 'packages.json';

    protected $providersUrl;

    protected $modules = [];

    public function __construct()
    {
        $this->modules = $this->getPackageVersionProviders();
    }

    public function getRequiredPackagesVersions($requestedMagentoVersion = null, $packageName = null)
    {
        $magentoPackages = $this->getPackageInformation(
            'magento/product-community-edition',
            $requestedMagentoVersion ? '/^' . str_replace('.', '\.', $requestedMagentoVersion) .'/' : null
        );

        $result = [];
        ksort($magentoPackages);
        foreach ($magentoPackages as $magentoPackage) {
            $magentoVersion = $magentoPackage['version'];
            $result[$magentoVersion] = $packageName ?
                $this->getMagentoRequiredPackage($magentoPackage['require'], $packageName) :
                $this->getMagentoRequiredPackages($magentoPackage['require']);
        }

        return $result;
    }

    protected function getMagentoRequiredPackage($require, $packageName)
    {
        if(! isset($require[$packageName])) {
            throw new \Exception('Module not found.');
        }

        $result[$packageName] = $require[$packageName];
        return $result;
    }

    protected function getMagentoRequiredPackages($require)
    {
        $result = [];


        ksort($require);
        foreach ($require as $name => $version) {
            $result[$name] = $version;
        }

        return $result;
    }

    public function getPackageInformation($packageName, $versionMatch = null)
    {
        $module = $this->modules[$packageName];
        if (empty($module)) {
            throw new \Exception('Module not found.');
        }

        $sha256 = $module['sha256'];
        $url = str_replace('%package%', $packageName, $this->providersUrl);
        $url = str_replace('%hash%', $sha256, $url);

        $data = $this->getJsonDataFromRepo($url, true);
        $versions = $data['packages'][$packageName];

        if($versionMatch) {
            $versions = array_filter($versions, function ($version) use ($versionMatch) {
                return preg_match($versionMatch, $version);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $versions;
    }

    protected function getPackageVersionProviders($providerType = 'provider-ce')
    {
        $packages = $this->getJsonDataFromRepo(self::REPO_PACKAGES_FILENAME);
        $this->providersUrl = trim($packages['providers-url'], '/');

        $modules = [];
        foreach ($packages['provider-includes'] as $urlScheme => $provider) {
            $url = str_replace('%hash%', $provider['sha256'], $urlScheme);
            $providers = $this->getJsonDataFromRepo($url);
            $modules = array_merge($modules, $providers['providers']);
        }

        return $modules;
    }

    protected function getJsonDataFromRepo($path, $isGziped = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::REPO_URL.$path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, self::REPO_USERNAME . ':' . self::REPO_PASSWORD);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: application/json'));

        if($isGziped) {
            curl_setopt($ch,CURLOPT_ENCODING , "gzip");
        }

        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output, true);
    }
}

$requestedMagentoVersion = isset($argv[1]) ? $argv[1] : null;
$packageName = isset($argv[2]) ? $argv[2] : null;

$repo = new Repo();
$result = $repo->getRequiredPackagesVersions($requestedMagentoVersion, $packageName);

foreach ($result as $magentoVersion => $packages) {
    echo "--- magento/product-community-edition [$magentoVersion] ---\n";
    foreach ($packages as $name => $version) {
        echo "$name [$version]\n";
    }
}