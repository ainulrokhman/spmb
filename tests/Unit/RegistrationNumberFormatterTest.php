<?php

namespace Tests\Unit;

use App\Support\RegistrationNumberFormatter;
use PHPUnit\Framework\TestCase;

class RegistrationNumberFormatterTest extends TestCase
{
    public function test_default_pattern_reproduces_the_legacy_format(): void
    {
        $pattern = RegistrationNumberFormatter::DEFAULT_PATTERN;

        $this->assertSame('3126001', RegistrationNumberFormatter::render($pattern, '31', '2026/2027', 1));
        $this->assertSame('3126012', RegistrationNumberFormatter::render($pattern, '31', '2026/2027', 12));
        $this->assertSame('0527003', RegistrationNumberFormatter::render($pattern, '05', '2027/2028', 3));
    }

    public function test_render_supports_literals_and_custom_padding(): void
    {
        $this->assertSame(
            'PPDB-2026-0001',
            RegistrationNumberFormatter::render('PPDB-{tahun}-{urut:4}', '31', '2026/2027', 1),
        );
        $this->assertSame(
            '31/2026/001',
            RegistrationNumberFormatter::render('{unit}/{tahun}/{urut:3}', '31', '2026/2027', 1),
        );
        $this->assertSame(
            'REG7',
            RegistrationNumberFormatter::render('REG{urut}', '31', '2026/2027', 7),
        );
    }

    public function test_sequence_scope_follows_the_pattern(): void
    {
        $this->assertSame('unit_year', RegistrationNumberFormatter::sequenceScope('{unit}{tahun2}{urut:3}'));
        $this->assertSame('year', RegistrationNumberFormatter::sequenceScope('PPDB-{tahun}-{urut:4}'));
        $this->assertSame('year', RegistrationNumberFormatter::sequenceScope('{tahun2}{urut:3}'));
        $this->assertSame('global', RegistrationNumberFormatter::sequenceScope('REG-{urut:5}'));
    }

    public function test_validate_accepts_the_default_and_custom_patterns(): void
    {
        $this->assertNull(RegistrationNumberFormatter::validatePattern(RegistrationNumberFormatter::DEFAULT_PATTERN));
        $this->assertNull(RegistrationNumberFormatter::validatePattern('PPDB-{tahun}-{urut:4}'));
        $this->assertNull(RegistrationNumberFormatter::validatePattern('{urut}'));
    }

    public function test_validate_rejects_bad_patterns(): void
    {
        $this->assertNotNull(RegistrationNumberFormatter::validatePattern(''));
        $this->assertNotNull(RegistrationNumberFormatter::validatePattern('{unit}{tahun}')); // no sequence
        $this->assertNotNull(RegistrationNumberFormatter::validatePattern('{unit}{bulan}{urut:3}')); // unknown token
        $this->assertNotNull(RegistrationNumberFormatter::validatePattern('{unit}{urut:9}')); // padding too long
        $this->assertNotNull(RegistrationNumberFormatter::validatePattern('{unit:2}{urut:3}')); // length on non-sequence
        $this->assertNotNull(RegistrationNumberFormatter::validatePattern('{unit}{urut:3')); // unpaired brace
    }
}
