<?php

declare(strict_types=1);

class Repo
{
    protected const REPO_USERNAME = 'f52fd8699d9ce4d436289cdad01b256a';
    protected const REPO_PASSWORD = 'f8f11de4a4c6b95223b2566789586f9b';
    protected const REPO_URL = 'https://repo.magento.com/';
    protected const REPO_PACKAGES_FILENAME = 'packages.json';

    protected string $providersUrl = '';
    protected array $modules = [];

    public function __construct()
    {
        $this->modules = $this->getPackageVersionProviders();
    }

    public function getRequiredPackagesVersions(?string $requestedMagentoVersion = null, ?string $packageName = null): array
    {
        $versionMatch = $requestedMagentoVersion !== null
            ? '/^' . str_replace('.', '\.', $requestedMagentoVersion) . '/'
            : null;

        $magentoPackages = $this->getPackageInformation('magento/product-community-edition', $versionMatch);

        ksort($magentoPackages);

        $result = [];
        foreach ($magentoPackages as $magentoPackage) {
            $magentoVersion = $magentoPackage['version'];
            $result[$magentoVersion] = $packageName !== null
                ? $this->getMagentoRequiredPackage($magentoPackage['require'], $packageName)
                : $this->getMagentoRequiredPackages($magentoPackage['require']);
        }

        return $result;
    }

    protected function getMagentoRequiredPackage(array $require, string $packageName): array
    {
        if (!isset($require[$packageName])) {
            throw new \RuntimeException('Module not found.');
        }

        return [$packageName => $require[$packageName]];
    }

    protected function getMagentoRequiredPackages(array $require): array
    {
        ksort($require);
        return $require;
    }

    public function getPackageInformation(string $packageName, ?string $versionMatch = null): array
    {
        $module = $this->modules[$packageName] ?? null;
        if ($module === null) {
            throw new \RuntimeException('Module not found.');
        }

        $url = str_replace(['%package%', '%hash%'], [$packageName, $module['sha256']], $this->providersUrl);
        $versions = $this->getJsonDataFromRepo($url, gzipped: true)['packages'][$packageName];

        if ($versionMatch !== null) {
            $versions = array_filter(
                $versions,
                fn(string $version): bool => (bool) preg_match($versionMatch, $version),
                ARRAY_FILTER_USE_KEY
            );
        }

        return $versions;
    }

    protected function getPackageVersionProviders(): array
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

    protected function getJsonDataFromRepo(string $path, bool $gzipped = false): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::REPO_URL . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => self::REPO_USERNAME . ':' . self::REPO_PASSWORD,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $output = curl_exec($ch);

        if ($gzipped) {
            $output = gzdecode($output);
        }

        return json_decode($output, associative: true);
    }
}

$requestedMagentoVersion = $argv[1] ?? null;
$packageName = $argv[2] ?? null;

if ($requestedMagentoVersion === null && $packageName === null) {
    exit('
        Sample usages:
        1.
            -> php vendor/creativestyle/magento-patches/tools/get_magento_versions.php "2.4.8-p1" "magento/module-customer"
            --- magento/product-community-edition [2.4.8-p1] ---
            magento/module-customer [103.0.8-p1]
        2.
            -> php vendor/creativestyle/magento-patches/tools/get_magento_versions.php "" "magento/module-review"
            --- magento/product-community-edition [0.42.0-beta7] ---
            magento/module-review [self.version]
            --- magento/product-community-edition [2.0.0] ---
            magento/module-review [100.0.2]
            ...
    ');
}

$repo = new Repo();
$result = $repo->getRequiredPackagesVersions($requestedMagentoVersion, $packageName);

foreach ($result as $magentoVersion => $packages) {
    echo "--- magento/product-community-edition [$magentoVersion] ---\n";
    foreach ($packages as $name => $version) {
        echo "$name [$version]\n";
    }
}
