<?php

declare(strict_types=1);

namespace CmCIC\Tests\Integration;

use CmCIC\CmCIC;
use Thelia\Test\IntegrationTestCase;

/**
 * The bank credentials used to live in Config/config.json, inside the module directory. The update
 * copies them into the module configuration (database) and leaves the file where it is.
 */
final class LegacyJsonConfigurationImportTest extends IntegrationTestCase
{
    private const CREDENTIALS = [
        CmCIC::CONFIG_TPE => '7654321',
        CmCIC::CONFIG_KEY => 'test-key-for-the-import',
        CmCIC::CONFIG_COMPANY_CODE => 'test-company',
        CmCIC::CONFIG_SERVER => 'https://bank.example.test/',
        CmCIC::CONFIG_PAGE => 'test-page.cgi',
        CmCIC::CONFIG_VERSION => '3.0',
    ];

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/cmcic-import-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    public function testTheJsonCredentialsAreCopiedIntoTheDatabaseAndTheFileIsKept(): void
    {
        $path = $this->writeJson(self::CREDENTIALS);

        self::assertSame(6, CmCIC::importLegacyJsonConfiguration($path));

        foreach (self::CREDENTIALS as $name => $value) {
            self::assertSame($value, CmCIC::getConfigValue($name), $name);
        }

        self::assertFileExists($path);
    }

    public function testImportingAgainChangesNothing(): void
    {
        $path = $this->writeJson(self::CREDENTIALS);
        CmCIC::importLegacyJsonConfiguration($path);

        // The file changed in the meantime: what is in the database wins.
        $this->writeJson([CmCIC::CONFIG_KEY => 'another-key'] + self::CREDENTIALS);

        self::assertSame(0, CmCIC::importLegacyJsonConfiguration($path));
        self::assertSame(self::CREDENTIALS[CmCIC::CONFIG_KEY], CmCIC::getConfigValue(CmCIC::CONFIG_KEY));
    }

    public function testACredentialAlreadyInTheDatabaseIsNeverOverwritten(): void
    {
        CmCIC::setConfigValue(CmCIC::CONFIG_KEY, 'key-saved-in-database');

        self::assertSame(5, CmCIC::importLegacyJsonConfiguration($this->writeJson(self::CREDENTIALS)));

        self::assertSame('key-saved-in-database', CmCIC::getConfigValue(CmCIC::CONFIG_KEY));
        self::assertSame(self::CREDENTIALS[CmCIC::CONFIG_TPE], CmCIC::getConfigValue(CmCIC::CONFIG_TPE));
    }

    public function testTheSampleOfTheRemovedDistFileIsNotImported(): void
    {
        $sample = [CmCIC::CONFIG_TPE => '0000001'] + self::CREDENTIALS;

        self::assertSame(0, CmCIC::importLegacyJsonConfiguration($this->writeJson($sample)));
        self::assertNull(CmCIC::getConfigValue(CmCIC::CONFIG_KEY));
    }

    public function testAMissingOrCorruptedFileImportsNothingAndDoesNotFail(): void
    {
        self::assertSame(0, CmCIC::importLegacyJsonConfiguration($this->directory . '/absent.json'));

        $corrupted = $this->directory . '/corrupted.json';
        file_put_contents($corrupted, '{"CMCIC_TPE":');

        self::assertSame(0, CmCIC::importLegacyJsonConfiguration($corrupted));
        self::assertNull(CmCIC::getConfigValue(CmCIC::CONFIG_TPE));
    }

    public function testTheModuleUpdateRunsTheImport(): void
    {
        $moduleFile = dirname(__DIR__, 2) . CmCIC::LEGACY_JSON_CONFIG_PATH;
        $backup = null;

        if (is_file($moduleFile)) {
            $backup = $this->directory . '/merchant-config.json';
            self::assertTrue(rename($moduleFile, $backup));
        }

        try {
            file_put_contents($moduleFile, json_encode(self::CREDENTIALS, JSON_THROW_ON_ERROR));

            (new CmCIC())->update('3.0.1', '3.1.0');

            self::assertSame(self::CREDENTIALS[CmCIC::CONFIG_KEY], CmCIC::getConfigValue(CmCIC::CONFIG_KEY));
            self::assertFileExists($moduleFile);
        } finally {
            @unlink($moduleFile);

            if ($backup !== null) {
                rename($backup, $moduleFile);
            }
        }
    }

    /**
     * @param array<string, string> $credentials
     */
    private function writeJson(array $credentials): string
    {
        $path = $this->directory . '/config.json';
        file_put_contents($path, json_encode($credentials, JSON_THROW_ON_ERROR));

        return $path;
    }
}
