<?php

/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 * @link http://www.yidianzhishi.com
 */

namespace Key\Util;


class Console
{

    /**
     * show simple progress text.
     *
     * @param int $percent
     * @param string $prefix
     * @param integer $maxWidth
     * @return void
     */
    public static function simpleProgress($percent = 0, $prefix = '', $maxWidth = 15)
    {
        if ($percent < 0) $percent = 0;

        echo "\033[?25l"; //隐藏光标
        echo ($prefix ? "\033[32m" . $prefix : '') . "\033[33m$percent%";
        echo "\033[" . $maxWidth . "D"; //移动光标到行首
        if ($percent >= 100) {
            echo "\n\33[?25h";
        }
    }

    /**
     * Show progress bar with progress text.
     *
     * @param integer $percent
     * @param integer $maxWidth
     * @return void
     */
    public static function showProgressBar($percent = 0, $maxWidth = 105)
    {
        if ($percent < 0) $percent = 0;
        echo "\033[?25l"; //隐藏光标
        $process = "";
        for ($i = 1; $i <= $percent; $i++) {
            $process .= "|";
        }
        echo "\033[32m" . $process . "\033[33m$percent%";
        echo "\033[" . $maxWidth . "D"; //移动光标到行首，105是进度条最大长度，再大点没关系

        if ($percent >= 100) {
            echo "\n\33[?25h";
        }
    }

    /**
     * Clear console screen.
     *
     * @return void
     */
    public static function clearScreen()
    {
        echo "\033[2J";
    }
}
