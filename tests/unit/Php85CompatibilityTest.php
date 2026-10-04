<?php
namespace Algo26\IdnaConvert\Test;

use Algo26\IdnaConvert\EncodingHelper;
use Algo26\IdnaConvert\IdnaConvert;
use Algo26\IdnaConvert\UnicodeTranscoder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Algo26\IdnaConvert\EncodingHelper
 * @covers \Algo26\IdnaConvert\UnicodeTranscoder
 * @covers \Algo26\IdnaConvert\Punycode
 */
class Php85CompatibilityTest extends TestCase
{
    public function testEncodingHelpersRemainByteCompatible()
    {
        $this->assertSame("\xC3\xA4", EncodingHelper::toUtf8("\xE4", 'ISO-8859-1'));
        $this->assertSame("\xE4", EncodingHelper::fromUtf8("\xC3\xA4", 'ISO-8859-1'));
    }

    public function testSafeUtf8ReplacementKeepsTheConfiguredCodepoint()
    {
        $this->assertSame(
            [0xFFFC, 0x41],
            UnicodeTranscoder::convert("\xC2A", 'utf8', 'ucs4array', true)
        );
    }

    public function testMalformedPunycodeDoesNotReadPastTheInput()
    {
        $errors = [];
        set_error_handler(function ($severity, $message) use (&$errors) {
            $errors[] = $message;
            return true;
        });

        try {
            (new IdnaConvert())->decode('xn--b');
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);
    }
}
