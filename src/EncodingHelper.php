<?php

namespace Algo26\IdnaConvert;

class EncodingHelper
{
    /**
     * Convert a string from any of various encodings to UTF-8
     *
     * @param  string  $string String to encode
     *[@param  string $encoding  Encoding; Default: ISO-8859-1]
     *[@param  bool $safe_mode  Safe Mode: if set to TRUE, the original string is retunred on errors]
     * @return  string|false  The encoded string or false on failure
     * @since 0.0.1
     */
    public static function toUtf8($string = '', $encoding = 'iso-8859-1', $safe_mode = false)
    {
        $safe = ($safe_mode) ? $string : false;
        if (strtoupper($encoding) == 'UTF-8' || strtoupper($encoding) == 'UTF8') {
            return $string;
        }

        if (strtoupper($encoding) === 'ISO-8859-1') {
            return self::encodeIso8859_1ToUtf8($string);
        }

        if (strtoupper($encoding) === 'WINDOWS-1252') {
            return self::encodeIso8859_1ToUtf8(self::map_w1252_iso8859_1($string));
        }

        if (strtoupper($encoding) === 'UNICODE-1-1-UTF-7') {
            $encoding = 'utf-7';
        }

        if (function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($string, 'UTF-8', strtoupper($encoding));

            if ($conv) {
                return $conv;
            }
        }

        if (function_exists('iconv')) {
            $conv = @iconv(strtoupper($encoding), 'UTF-8', $string);
            if ($conv) {
                return $conv;
            }
        }
        if (function_exists('libiconv')) {
            $conv = @libiconv(strtoupper($encoding), 'UTF-8', $string);
            if ($conv) {
                return $conv;
            }
        }

        return $safe;
    }

    /**
     * Convert a string from UTF-8 to any of various encodings
     *
     * @param  string  $string String to decode
     *[@param  string  $encoding Encoding; Default: ISO-8859-1]
     *[@param  bool  $safe_mode Safe Mode: if set to TRUE, the original string is retunred on errors]
     * @return  string|false  The decoded string or false on failure
     * @since 0.0.1
     */
    public static function fromUtf8($string = '', $encoding = 'iso-8859-1', $safe_mode = false)
    {
        $safe = ($safe_mode) ? $string : false;
        if (!$encoding) {
            $encoding = 'ISO-8859-1';
        }

        if (strtoupper($encoding) === 'UTF-8' || strtoupper($encoding) === 'UTF8') {
            return $string;
        }

        if (strtoupper($encoding) === 'ISO-8859-1') {
            return self::decodeUtf8ToIso8859_1($string);
        }

        if (strtoupper($encoding) === 'WINDOWS-1252') {
            return self::map_iso8859_1_w1252(self::decodeUtf8ToIso8859_1($string));
        }

        if (strtoupper($encoding) === 'UNICODE-1-1-UTF-7') {
            $encoding = 'utf-7';
        }

        if (function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($string, strtoupper($encoding), 'UTF-8');
            if ($conv) {
                return $conv;
            }
        }

        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', strtoupper($encoding), $string);
            if ($conv) {
                return $conv;
            }
        }

        if (function_exists('libiconv')) {
            $conv = @libiconv('UTF-8', strtoupper($encoding), $string);
            if ($conv) {
                return $conv;
            }
        }

        return $safe;
    }

    /**
     * Drop-in replacement for utf8_encode(), which is deprecated as of PHP 8.2.
     *
     * @param string $string Your input in ISO-8859-1
     * @return string The resulting UTF-8 string
     */
    protected static function encodeIso8859_1ToUtf8($string = '')
    {
        $return = '';
        $length = strlen($string);
        for ($i = 0; $i < $length; ++$i) {
            $codePoint = ord($string[$i]);
            if ($codePoint < 0x80) {
                $return .= $string[$i];
            } else {
                $return .= chr(0xC0 | ($codePoint >> 6)) . chr(0x80 | ($codePoint & 0x3F));
            }
        }

        return $return;
    }

    /**
     * Drop-in replacement for utf8_decode(), which is deprecated as of PHP 8.2.
     * Characters outside ISO-8859-1 and malformed UTF-8 sequences are replaced by '?'.
     *
     * @param string $string Your input in UTF-8
     * @return string The resulting ISO-8859-1 string
     */
    protected static function decodeUtf8ToIso8859_1($string = '')
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
                    // Skip to a byte that is missing or could start a new sequence.
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
                    // Overlong encoding, surrogate or beyond U+10FFFF.
                    $codePoint = null;
                }
            }

            $return .= ($codePoint === null || $codePoint > 0xFF) ? '?' : chr($codePoint);
            $position += $sequenceLength;
        }

        return $return;
    }

    /**
     * Special treatment for our guys in Redmond
     * Windows-1252 is basically ISO-8859-1 -- with some exceptions, which get accounted for here
     *
     * @param  string $string Your input in Win1252
     * @return string  The resulting ISO-8859-1 string
     * @since 0.0.1
     */
    protected static function map_w1252_iso8859_1($string = '')
    {
        if ($string === '') {
            return '';
        }

        $return = '';

        for ($i = 0; $i < strlen($string); ++$i) {
            $c = ord($string[$i]);
            switch ($c) {
                case 129:
                    $return .= chr(252);
                    break;
                case 132:
                    $return .= chr(228);
                    break;
                case 142:
                    $return .= chr(196);
                    break;
                case 148:
                    $return .= chr(246);
                    break;
                case 153:
                    $return .= chr(214);
                    break;
                case 154:
                    $return .= chr(220);
                    break;
                case 225:
                    $return .= chr(223);
                    break;
                default:
                    $return .= chr($c);
            }
        }

        return $return;
    }

    /**
     * Special treatment for our guys in Redmond
     * Windows-1252 is basically ISO-8859-1 -- with some exceptions, which get accounted for here
     *
     * @param  string $string  Your input in ISO-8859-1
     * @return  string  The resulting Win1252 string
     * @since 0.0.1
     */
    protected static function map_iso8859_1_w1252($string = '')
    {
        if ($string === '') {
            return '';
        }

        $return = '';
        for ($i = 0; $i < strlen($string); ++$i) {
            $c = ord($string[$i]);
            switch ($c) {
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
                    $return .= chr($c);
            }
        }

        return $return;
    }
}
