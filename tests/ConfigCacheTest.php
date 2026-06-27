<?php

declare(strict_types=1);

/**
 * Tests for the C() read-cache optimization.
 */
class ConfigCacheTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        // Restore clean state for each test via snapshot mechanism
        C("\x00snapshot_restore\x00");
    }

    public function testReadCacheReturnsSameValueOnRepeatCall(): void
    {
        C('CACHE_TEST_KEY', 'hello');
        $first  = C('CACHE_TEST_KEY');
        $second = C('CACHE_TEST_KEY');
        $this->assertSame('hello', $first);
        $this->assertSame($first, $second);
    }

    public function testWriteInvalidatesCache(): void
    {
        C('CACHE_INVAL', 'old');
        $this->assertSame('old', C('CACHE_INVAL'));   // warms cache
        C('CACHE_INVAL', 'new');                       // should invalidate
        $this->assertSame('new', C('CACHE_INVAL'));
    }

    public function testDotNotationCacheHit(): void
    {
        C('TOP', ['SUB' => ['DEEP' => 'deep_value']]);
        $first  = C('TOP.SUB.DEEP');
        $second = C('TOP.SUB.DEEP');
        $this->assertSame('deep_value', $first);
        $this->assertSame($first, $second);
    }

    public function testDotNotationInvalidatedByParentWrite(): void
    {
        C('DB', ['host' => 'localhost']);
        $this->assertSame('localhost', C('DB.host')); // warm
        C('DB', ['host' => 'remotehost']);            // rewrite parent key
        // Cache for 'DB.host' should be invalidated since 'DB' prefix was written
        $this->assertSame('remotehost', C('DB.host'));
    }

    public function testSnapshotClearsCache(): void
    {
        C('SNAP_KEY', 'before');
        C('SNAP_KEY'); // warm cache
        C("\x00snapshot_take\x00");   // should clear cache
        C('SNAP_KEY', 'after');       // mutate
        C("\x00snapshot_restore\x00"); // restore → cache cleared again
        $this->assertSame('before', C('SNAP_KEY'));
    }

    public function testBulkWriteInvalidatesCache(): void
    {
        C('BULK_A', 'a1');
        C('BULK_A'); // warm
        C(['BULK_A' => 'a2', 'BULK_B' => 'b1']); // bulk write
        $this->assertSame('a2', C('BULK_A'));
        $this->assertSame('b1', C('BULK_B'));
    }

    public function testNullReturnedForMissingKeyIsCached(): void
    {
        // Reading a missing key should not cause repeated file-system/array lookups
        $first  = C('DEFINITELY_MISSING_XYZ');
        $second = C('DEFINITELY_MISSING_XYZ');
        $this->assertNull($first);
        $this->assertNull($second);
    }

    public function testTwoLayerDotKey(): void
    {
        C(['LAYER' => ['inner' => 'val']]);
        $this->assertSame('val', C('LAYER.inner'));
        $this->assertSame('val', C('LAYER.inner')); // cache hit
    }
}
