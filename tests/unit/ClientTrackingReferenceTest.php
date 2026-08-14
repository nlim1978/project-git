<?php

use App\Services\ClientTrackingService;
use CodeIgniter\Test\CIUnitTestCase;

final class ClientTrackingReferenceTest extends CIUnitTestCase
{
    public function testReadableReferenceAcceptsCommonTypingVariants(): void
    {
        foreach (['TRK-0226-0001', 'trk02260001', '02260001', 'TRK 0226 0001'] as $input) {
            $this->assertSame('TRK-0226-0001', ClientTrackingService::normalizeReference($input));
        }
    }

    public function testReadableReferenceRejectsInvalidMonthOrSeriesLength(): void
    {
        $this->assertNull(ClientTrackingService::normalizeReference('TRK-1326-0001'));
        $this->assertNull(ClientTrackingService::normalizeReference('TRK-0226-001'));
    }

    public function testLegacyOpaqueReferenceStillNormalizes(): void
    {
        $token = str_repeat('a', 32);
        $display = 'TRK-' . implode('-', array_fill(0, 8, 'AAAA'));

        $this->assertSame($token, ClientTrackingService::normalizeToken($display));
    }
}
