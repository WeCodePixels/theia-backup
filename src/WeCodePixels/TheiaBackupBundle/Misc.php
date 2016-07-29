<?php

namespace WeCodePixels\TheiaBackupBundle;

class Misc
{
    /**
     * Returns a rought approximation of the number of days/hours/minutes/etc. that have passed
     * Taken from http://stackoverflow.com/a/14339355/148388
     * @param int $pastTime A Unix timestamp.
     * @return string
     */
    public static function getElapsedTime($pastTime)
    {
        $currentTime = time() - $pastTime;

        if ($currentTime < 1) {
            return '0 seconds';
        }

        $a = array(
            365 * 24 * 60 * 60 => 'year',
            30 * 24 * 60 * 60 => 'month',
            24 * 60 * 60 => 'day',
            60 * 60 => 'hour',
            60 => 'minute',
            1 => 'second'
        );
        $aPlural = array(
            'year' => 'years',
            'month' => 'months',
            'day' => 'days',
            'hour' => 'hours',
            'minute' => 'minutes',
            'second' => 'seconds'
        );

        foreach ($a as $secs => $str) {
            $d = $currentTime / $secs;

            if ($d >= 1) {
                $r = round($d);

                return $r . ' ' . ($r > 1 ? $aPlural[$str] : $str) . ' ago';
            }
        }

        return '';
    }

    public static function getTextForTimestamp($timestamp)
    {
        return date("H:i:s d-m-y", $timestamp);
    }
}
