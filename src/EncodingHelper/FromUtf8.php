<?php

namespace Algo26\IdnaConvert\EncodingHelper;

class FromUtf8 implements EncodingHelperInterface
{
    private const DEFAULT_ENCODING = 'ISO-8859-1';

    private $encoding = self::DEFAULT_ENCODING;

    public function convert(
        string $sourceString,
        ?string $encoding = self::DEFAULT_ENCODING,
        ?bool $safeMode = false
    ) {
        $safe = ($safeMode) ? $sourceString : false;

        if ($encoding !== null) {
            $this->encoding = strtoupper($encoding);
        } else {
            $this->encoding = 'ISO-8859-1';
        }

        if ($this->encoding === 'UTF-8' || $this->encoding === 'UTF8') {
            return $sourceString;
        }

        if ($this->encoding === 'ISO-8859-1') {
            return $this->decodeUtf8ToIso8859_1($sourceString);
        }

        if ($this->encoding === 'WINDOWS-1252') {
            return self::mapIso8859_1ToWindows1252($this->decodeUtf8ToIso8859_1($sourceString));
        }

        if ($this->encoding === 'UNICODE-1-1-UTF-7') {
            $this->encoding = 'UTF-7';
        }

        $converted = $this->convertWithLibraries($sourceString);
        if (false !== $converted) {
            return $converted;
        }

        return $safe;
    }

    /**
     * Special treatment for our guys in Redmond
     * Windows-1252 is basically ISO-8859-1 -- with some exceptions, which get dealt with here
     *
     * @param  string $string Your input in ISO-8859-1
     *
     * @return  string  The resulting Win1252 string
     * @since 0.0.1
     */
    private function mapIso8859_1ToWindows1252($string = '')
    {
        $return = '';
        for ($i = 0; $i < strlen($string); ++$i) {
            $codePoint = ord($string[$i]);
            switch ($codePoint) {
                case 196:
                    $return .= chr(142);
                    break;
                case 214:
                    $return .= chr(153);
                    break;
                case 220:
                    $return .= chr(154);
                    break;
                case 223:
                    $return .= chr(225);
                    break;
                case 228:
                    $return .= chr(132);
                    break;
                case 246:
                    $return .= chr(148);
                    break;
                case 252:
                    $return .= chr(129);
                    break;
                default:
                    $return .= chr($codePoint);
            }
        }

        return $return;
    }

    /**
     * Drop-in replacement for utf8_decode(), which is deprecated as of PHP 8.2
     * Characters outside ISO-8859-1 and malformed UTF-8 sequences are replaced by '?', just like utf8_decode() does
     *
     * @param string $string Your input in UTF-8
     *
     * @return string The resulting ISO-8859-1 string
     */
    private function decodeUtf8ToIso8859_1(string $string): string
    {
        $return = '';
        $length = strlen($string);
        $position = 0;
        while ($position < $length) {
            $lead = ord($string[$position]);
            $available = $length - $position;
            $sequenceLength = 1;
            $codePoint = null;

            if ($lead < 0x80) {
                $codePoint = $lead;
            } elseif ($lead >= 0xC2 && $lead <= 0xF4) {
                $sequenceLength = $lead < 0xE0 ? 2 : ($lead < 0xF0 ? 3 : 4);
                $codePoint = $lead & (0xFF >> ($sequenceLength + 1));
                for ($i = 1; $i < $sequenceLength; ++$i) {
                    $byte = $i < $available ? ord($string[$position + $i]) : null;
                    if ($byte === null || $byte < 0x80 || $byte > 0xBF) {
                        $codePoint = null;
                        break;
                    }
                    $codePoint = ($codePoint << 6) | ($byte & 0x3F);
                }
                if ($codePoint === null) {
                    // Malformed sequence: skip up to the first byte that is missing or could start
                    // a new sequence, otherwise the whole sequence
                    for ($i = 1; $i < $sequenceLength; ++$i) {
                        $byte = $i < $available ? ord($string[$position + $i]) : null;
                        if ($byte === null || $byte < 0x80 || ($byte >= 0xC2 && $byte <= 0xF4)) {
                            $sequenceLength = $i;
                            break;
                        }
                    }
                } elseif (($sequenceLength === 3 && ($codePoint < 0x800 || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)))
                    || ($sequenceLength === 4 && ($codePoint < 0x10000 || $codePoint > 0x10FFFF))
                ) {
                    // Overlong encoding, surrogate or beyond U+10FFFF
                    $codePoint = null;
                }
            }

            $return .= ($codePoint === null || $codePoint > 0xFF) ? '?' : chr($codePoint);
            $position += $sequenceLength;
        }

        return $return;
    }

    private function convertWithLibraries(string $string): ?string
    {
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($string, $this->encoding, 'UTF-8');
            if (false !== $converted) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', $this->encoding, $string);
            if (false !== $converted) {
                return $converted;
            }
        }

        if (function_exists('libiconv')) {
            $converted = @libiconv('UTF-8', $this->encoding, $string);
            if (false !== $converted) {
                return $converted;
            }
        }

        return false;
    }
}
