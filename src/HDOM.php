<?php

namespace Toxageek\LaravelHtmlDom;

class HDOM
{
    public const TYPE_ELEMENT = 1;

    public const TYPE_COMMENT = 2;

    public const TYPE_TEXT = 3;

    public const TYPE_ENDTAG = 4;

    public const TYPE_ROOT = 5;

    public const TYPE_UNKNOWN = 6;

    public const QUOTE_DOUBLE = 0;

    public const QUOTE_SINGLE = 1;

    public const QUOTE_NO = 3;

    public const INFO_BEGIN = 0;

    public const INFO_END = 1;

    public const INFO_QUOTE = 2;

    public const INFO_SPACE = 3;

    public const INFO_TEXT = 4;

    public const INFO_INNER = 5;

    public const INFO_OUTER = 6;

    public const INFO_ENDSPACE = 7;

    public const SMARTY_AS_TEXT = 1;

    public static function TARGET_CHARSET(): string
    {
        return config('laravel-html-dom.TARGET_CHARSET', 'UTF-8');
    }

    public static function BR_TEXT(): string
    {
        return config('laravel-html-dom.BR_TEXT', "\r\n");
    }

    public static function SPAN_TEXT(): string
    {
        return config('laravel-html-dom.SPAN_TEXT', ' ');
    }

    public static function MAX_FILE_SIZE(): int
    {
        return config('laravel-html-dom.MAX_FILE_SIZE', 600000);
    }
}
