<?php

namespace Tests\Unit;

use Tests\TestCase;

class CasheaConfigTest extends TestCase
{
    private string $testFilePath;
    private array $backupData = [];
    private bool $hadBackup = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testFilePath = storage_path('app/cashea_levels.json');
        
        // Backup existing config
        if (file_exists($this->testFilePath)) {
            $this->backupData = json_decode(file_get_contents($this->testFilePath), true) ?? [];
            $this->hadBackup = true;
            unlink($this->testFilePath);
        }
    }

    protected function tearDown(): void
    {
        // Restore backup
        if ($this->hadBackup) {
            file_put_contents($this->testFilePath, json_encode($this->backupData, JSON_PRETTY_PRINT));
        } elseif (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        parent::tearDown();
    }

    public function test_default_cashea_levels_fallback()
    {
        // File does not exist, check fallback defaults
        $this->assertFileDoesNotExist($this->testFilePath);

        $defaultLevels = [
            1 => 60,
            2 => 50,
            3 => 40,
            4 => 40,
            5 => 40,
            6 => 40,
        ];

        // Simulate UserController index load behavior
        $casheaLevels = $defaultLevels;
        if (file_exists($this->testFilePath)) {
            $stored = json_decode(file_get_contents($this->testFilePath), true);
            if (is_array($stored)) {
                foreach (range(1, 6) as $nivel) {
                    if (isset($stored[$nivel])) {
                        $casheaLevels[$nivel] = (int) $stored[$nivel];
                    }
                }
            }
        }

        $this->assertEquals(60, $casheaLevels[1]);
        $this->assertEquals(50, $casheaLevels[2]);
        $this->assertEquals(40, $casheaLevels[3]);
        $this->assertEquals(40, $casheaLevels[6]);
    }

    public function test_save_and_load_cashea_levels()
    {
        // Save test levels
        $testLevels = [
            1 => 75,
            2 => 55,
            3 => 45,
            4 => 35,
            5 => 25,
            6 => 15,
        ];

        $dir = dirname($this->testFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->testFilePath, json_encode($testLevels, JSON_PRETTY_PRINT));
        $this->assertFileExists($this->testFilePath);

        // Load back
        $loadedLevels = [];
        if (file_exists($this->testFilePath)) {
            $stored = json_decode(file_get_contents($this->testFilePath), true);
            if (is_array($stored)) {
                foreach (range(1, 6) as $nivel) {
                    if (isset($stored[$nivel])) {
                        $loadedLevels[$nivel] = (int) $stored[$nivel];
                    }
                }
            }
        }

        $this->assertEquals(75, $loadedLevels[1]);
        $this->assertEquals(55, $loadedLevels[2]);
        $this->assertEquals(45, $loadedLevels[3]);
        $this->assertEquals(35, $loadedLevels[4]);
        $this->assertEquals(25, $loadedLevels[5]);
        $this->assertEquals(15, $loadedLevels[6]);
    }
}
