<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_to_whats_app_converts_local_forms(): void
    {
        $this->assertSame('6281234567890', PhoneNumber::toWhatsApp('081234567890'));
        $this->assertSame('6281234567890', PhoneNumber::toWhatsApp('+62 812-3456-7890'));
        $this->assertSame('6281234567890', PhoneNumber::toWhatsApp('6281234567890'));
        $this->assertSame('6281234567890', PhoneNumber::toWhatsApp('81234567890'));
    }

    public function test_to_whats_app_rejects_implausible_numbers(): void
    {
        $this->assertNull(PhoneNumber::toWhatsApp(null));
        $this->assertNull(PhoneNumber::toWhatsApp(''));
        $this->assertNull(PhoneNumber::toWhatsApp('021123456'));
        $this->assertNull(PhoneNumber::toWhatsApp('0812'));
        $this->assertNull(PhoneNumber::toWhatsApp('bukan nomor'));
    }
}
