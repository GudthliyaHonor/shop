<?php
include_once '../helpers.php';
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2017/8/11
 * Time: 10:11
 */
class HelpersTest extends PHPUnit_Framework_TestCase
{

    public function testStartsWith()
    {
        $this->assertTrue(startsWith('ABC xyz', 'ABC'));
        $this->assertTrue(startsWith('ABCD oooooo', 'ABC'));
        $this->assertTrue(startsWith('http://www.baidu.com/aaaa/sssss?aaacdeaaa', 'http://www.baidu.com'));

        $this->assertFalse(startsWith('', 'talentyun-weike-dev'));

        $this->assertFalse(startsWith('abcdefg', 'xyz'));
    }

    public function testEndsWith()
    {
        $this->assertTrue(endsWith('ABC xyz', 'xyz'));
        $this->assertTrue(endsWith('ABCD 00000', '000'));

        $this->assertFalse(endsWith('abcdefg', 'xyz'));
    }
}
