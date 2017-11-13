<?php declare(strict_types=1);
////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    <
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// -----------------------------------------------------------------------------
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />
// -----------------------------------------------------------------------------
////////////////////////////////////////////////////////////////////////////////

namespace Gears\Tests;

use Traversable;
use Gears\ClassFinder;
use Gears\IClassFinder;
use Gears\String\Base;
use PHPUnit\Framework\TestCase;

class ClassFinderTest extends TestCase
{
    /**
     * @var string
     */
    protected static $baseDir;

    /**
     * @var IClassFinder
     */
    protected static $finder;

    public static function setUpBeforeClass()
    {
        self::$baseDir = realpath(__DIR__.'/..');

        self::$finder = new ClassFinder
        (
            require(self::$baseDir.'/vendor/autoload.php')
        );
    }

    public function testFindTheClassFinder()
    {
        $this->assertArraySubset
        (
            [self::$baseDir.'/src/ClassFinder.php' => 'Gears\ClassFinder'],
            self::$finder->setNamespace('Gears')->search()
        );
    }

    public function testInterfaceFilter()
    {
        $this->assertSame
        (
            [self::$baseDir.'/src/ClassFinder.php' => 'Gears\ClassFinder'],
            self::$finder->setNamespace('Gears')->addImplements(IClassFinder::class)->search()
        );
    }

    public function testExtendsFilter()
    {
        $this->assertSame
        (
            [self::$baseDir.'/vendor/gears/string/src/Str.php' => 'Gears\String\Str'],
            self::$finder->setNamespace('Gears')->addExtends(Base::class)->search()
        );
    }

    public function testIterator()
    {
        $this->assertInstanceOf(Traversable::class, self::$finder->setNamespace('Gears')->getIterator());

        foreach (self::$finder->setNamespace('Gears') as $filepath => $fqcn)
        {
            $this->assertFileExists($filepath);
            $this->assertTrue(class_exists($fqcn));
        }
    }

    public function testCount()
    {
        $this->assertEquals(1, self::$finder->setNamespace('Gears')->addImplements(IClassFinder::class)->count());
    }
}
