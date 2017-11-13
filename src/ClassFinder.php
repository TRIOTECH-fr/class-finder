<?php
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

namespace Gears;

use ArrayIterator;
use Closure;
use Composer\Autoload\ClassLoader;
use Exception;
use Gears\String\Str;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ClassFinder implements IClassFinder
{
    /**
     * The composer class loader as returned from `vendor/autoload.php`.
     *
     * @var ClassLoader
     */
    protected $composer;

    /**
     * This will be filled with fully qualified class names as they are found
     * by searching through the various class maps provided by composer.
     *
     * @var array|string[]
     */
    protected $foundClasses = [];

    /**
     * The namespace to filter by will be stored here.
     *
     * > NOTE: This must be set, otherwise we could have a
     *         very large data set to search through.
     *
     * @var string
     */
    protected $namespace;

    /**
     * The interface names to filter by will be stored here.
     *
     * @var array|string[]
     */
    protected $implements = [];

    /**
     * The parent classes to filter by will be stored here.
     *
     * @var array|string[]
     */
    protected $extends = [];

    /**
     * An optional custom filter method can be set.
     * Otherwise we will use the `defaultFilter` method in this class.
     *
     * @var Closure
     */
    protected $filter;

    /**
     * Constructor.
     *
     * @param ClassLoader $composer We rely on the information provided by the
     *                              composer class maps in order to find classes
     *                              for you.
     */
    public function __construct(ClassLoader $composer)
    {
        $this->composer = $composer;
    }

    /** @inheritdoc */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;

        return $this;
    }

    /** @inheritdoc */
    public function addImplements($interface)
    {
        $this->implements[] = $interface;

        return $this;
    }

    /** @inheritdoc */
    public function addExtends($parent)
    {
        $this->extends[] = $parent;

        return $this;
    }

    /** @inheritdoc */
    public function filterBy(Closure $filter)
    {
        if (!empty($this->implements) || !empty($this->extends)) {
            throw new Exception
            (
                'Can not set a custom filter and filter ' .
                'by `implements` or `extends`!'
            );
        }

        $this->filter = $filter;

        return $this;
    }

    /** @inheritdoc */
    public function search()
    {
        $this->foundClasses = [];

        if ($this->namespace === null) {
            throw new Exception('Namespace must be set!');
        }

        $this->searchClassMap();
        $this->searchPsrMaps();
        $this->runFilter();

        $this->namespace = null;
        $this->implements = [];
        $this->extends = [];

        return $this->foundClasses;
    }

    /** @inheritdoc */
    public function getIterator()
    {
        return new ArrayIterator($this->search());
    }

    /** @inheritdoc */
    public function count()
    {
        return iterator_count($this->getIterator());
    }

    /**
     * Searches the composer class map.
     *
     * Results are added to the `$foundClasses` array.
     *
     * @return void
     */
    protected function searchClassMap()
    {
        foreach ($this->composer->getClassMap() as $fqcn => $file) {
            if (Str::s($fqcn)->is($this->namespace . '*')) {
                $this->foundClasses[realpath($file)] = $fqcn;
            }
        }
    }

    /**
     * Searches the composer PSR-x class maps.
     *
     * Results are added to the `$foundClasses` array.
     *
     * @return void
     */
    protected function searchPsrMaps()
    {
        $prefixes = array_merge(
            $this->composer->getPrefixes(),
            $this->composer->getPrefixesPsr4()
        );

        $trimmedNs = Str::s($this->namespace)->trimRight('\\');

        $nsSegments = $trimmedNs->split('\\');

        foreach ($prefixes as $ns => $dirs) {
            $foundSegments = Str::s($ns)->trimRight('\\')
                ->longestCommonPrefix($trimmedNs)->split('\\')
            ;

            foreach ($foundSegments as $key => $segment) {
                if ((string)$nsSegments[$key] !== (string)$segment) {
                    continue 2;
                }
            }

            foreach ($dirs as $dir) {
                $finder = new Finder;
                foreach ($finder->in($dir)->files()->name('*.php') as $file) {
                    if ($file instanceof SplFileInfo) {
                        $fqcn = (string)Str::s($file->getRelativePathname())
                            ->trimRight('.php')
                            ->replace('/', '\\')
                            ->ensureLeft($ns)
                        ;

                        if (Str::s($fqcn)->is($this->namespace . '*')) {
                            $this->foundClasses[$file->getRealPath()] = $fqcn;
                        }
                    }
                }
            }
        }
    }

    /**
     * Runs a filter over the array of `$foundCLasses`.
     *
     * Also ensures the classes are real by creating a ReflectionClass instance.
     * By default we use the `defaultFilter` method.
     *
     * @return void
     */
    protected function runFilter()
    {
        foreach ($this->foundClasses as $file => $fqcn) {
            try {
                $rClass = new ReflectionClass($fqcn);

                if ($this->filter === null) {
                    $result = $this->defaultFilter($rClass);
                } else {
                    $result = call_user_func($this->filter, $rClass);
                }
            } catch (ReflectionException $e) {
                $result = false;
            }

            if ($result === false) {
                unset($this->foundClasses[$file]);
            }
        }
    }

    /**
     * The default filter run by `runFilter()`.
     *
     * Further filters by  interface or parent class and also filters
     * out actual Interfaces, Abstract Classes and Traits.
     *
     * @param  ReflectionClass $rClass
     *
     * @return bool
     */
    protected function defaultFilter(ReflectionClass $rClass)
    {
        $matches = true;

        foreach ($this->implements as $implements) {
            $matches = $matches && $rClass->implementsInterface($implements) && !$rClass->isInterface();
        }

        foreach ($this->extends as $extends) {
            $matches = $matches && $rClass->isSubclassOf($extends) && !$rClass->isAbstract();
        }

        return $matches && (!$rClass->isInterface() && !$rClass->isAbstract() && !$rClass->isTrait());
    }
}
