<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2021 keylogic.com
 * @version 1.0.0
 * @link http://www.keylogic.com
 */

namespace Key\Foundation;


use Composer\Autoload\ClassLoader;

class Module implements \Serializable
{
    protected $name;

    protected $desc;

    protected $systemClassLoader;

    /** @deprecated */
    protected $ns;
    /** @deprecated */
    protected $classPath;

    protected $loaderDef = [];

    protected $configureFile;

    protected $routePath;

    protected $pageFile;

    protected $thirdClasses = [];

    protected $dependencies = [];

    protected $recordNSPrefix = null;

    protected $hooks = [];

    protected $menu = [];

    /**
     * Module priority, it is higher priority when it larger.
     * It is useful to override the configure or routes, etc.
     *
     * @var integer
     */
    protected $priority = 100;

    protected $eventFile = null;

    protected $langFolder = null;

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setDescription($desc)
    {
        $this->desc = $desc;
        return $this;
    }

    public function getDescription()
    {
        return $this->desc;
    }

    /**
     * @deprecated
     * @see addClassLoaderDef()
     */
    public function setClassLoader($ns, $classPath)
    {
        $this->ns = $ns;
        $this->classPath = $classPath;
        return $this;
    }

    public function getNs()
    {
        if ($this->loaderDef) {
            return $this->loaderDef[0]['ns'];
        }
        return $this->ns;
    }

    /**
     * @deprecated
     */
    public function getClassPath()
    {
        return $this->classPath;
    }

    /**
     * Set system class loader to load classes.
     * Such as `Composer\Autoload\ClassLoader`.
     * 
     * @param $loader
     * @return $this
     */
    public function setSystemClassLoader($loader)
    {
        $this->systemClassLoader = $loader;
        return $this;
    }

    /**
     * Get system class loader.
     * 
     * @return Composer\Autoload\ClassLoader
     */
    protected function getSystemClassLoader()
    {
        if (!$this->systemClassLoader) {
            $this->systemClassLoader = new ClassLoader();
        }
        return $this->systemClassLoader;
    }

    /**
     * Add class namespace for classloader.
     * 
     * @param string $ns
     * @param string $classPath
     * @return $this
     */
    public function addClassLoaderDef($ns, $classPath, $autoRegister = false)
    {
        $this->ns = $ns;

        $this->loaderDef[] = [
            'name' => $this->name,
            'ns' => $ns,
            'path' => $classPath,
        ];

        if ($autoRegister) {
            error_log('[addClassLoaderDef] load ns: ' . $ns . ' - ' . $classPath);
            $this->getSystemClassLoader()->addPsr4($ns, $classPath);
        }

        return $this;
    }

    /**
     * Add folder namespace for class loader.
     * 
     * @param string $ns
     * @param string $folder
     * @return $this
     */
    public function addClassPackageDef($ns, $folder, $autoRegister = false)
    {
        if (file_exists($folder)) {
            $handle = opendir($folder);
            if ($handle) {
                while (($fl = readdir($handle)) !== false) {
                    if ($fl == '.' || $fl == '..' || startsWith($fl, '__')) {
                        continue;
                    }

                    $pkgFolder = $folder . DIRECTORY_SEPARATOR . $fl;
                    if (is_dir($pkgFolder)) {
                        // error_log('[addClassPackageDef] loading ' . $pkgFolder);
                        $this->addClassLoaderDef($ns, $pkgFolder, $autoRegister);
                    }

                    continue;
                }
            }

            closedir($handle);
        }
        return $this;
    }

    /**
     * Get the class loaders.
     * 
     * @return array
     */
    public function getClassLoaderDef()
    {
        return $this->loaderDef;
    }

    /**
     * Register classes.
     * 
     * @param \Key\Container $container
     * @param bool $autoRegister
     * @return $this
     */
    public function registerClasses($container = null, $autoRegister = true, $ignoreDependency = false)
    {
        $loader = $this->getSystemClassLoader();

        // @deprecated
        if ($this->getNs()) {
            $loader->addPsr4($this->getNs(), $this->getClassPath());
        }
        // END

        $loaderDef = $this->getClassLoaderDef();
        if ($loaderDef) {
            foreach ($loaderDef as $def) {
                // error_log('[loadClasses] def: ' . json_encode($def));
                $loader->addPsr4($def['ns'], $def['path']);
            }
        }

        if (!$ignoreDependency && ($dependencies = $this->getDependencies())) {
            foreach ($dependencies as $dependency) {
                if (isset($container['modules'][$dependency])) {
                    // error_log('load dependency module: ' . $dependency);
                    $dependencyModel = $container['modules'][$dependency];

                    // @deprecated
                    $loader->addPsr4($dependencyModel->getNs(), $dependencyModel->getClassPath());
                    // END

                    $dependencyModel->registerClasses($container, $autoRegister, $ignoreDependency);
                }
                else {
                    error_log('[WARN] Module ' . $this->getName() . ' dependency `' . $dependency . '` not found');
                }
            }
        }

        if ($third = $this->getThirdClasses()) {
            foreach ($third as $item) {
                $loader->addPsr4($item['ns'], $item['classPath']);
            }
        }

        if ($autoRegister) $loader->register();

        return $this;
    }

    public function setConfigureFile($file)
    {
        $this->configureFile = $file;
        return $this;
    }

    public function getConfigureFile()
    {
        return $this->configureFile;
    }

    public function setRoutePath($path)
    {
        $this->routePath = $path;
        return $this;
    }

    public function getRoutePath()
    {
        return $this->routePath;
    }

    /**
     * Set page route file.
     *
     * @param string $file Page route file
     * @return self
     */
    public function setPageFile($file)
    {
        $this->pageFile = $file;
        return $this;
    }

    public function getPageFile()
    {
        return $this->pageFile;
    }

    public function addThirdClasses($ns, $classPath)
    {
        $this->thirdClasses[] = [
            'ns' => $ns,
            'classPath' => $classPath
        ];
        return $this;
    }

    public function getThirdClasses()
    {
        return $this->thirdClasses;
    }

    /**
     * Add dependency module.
     *
     * @param string $module Dependent module name
     * @return self
     */
    public function addDependency($module)
    {
        $this->dependencies[] = $module;
        return $this;
    }

    /**
     * Get all dependent modules.
     *
     * @return array
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    /**
     * Set module priority.
     *
     * @param int $priority
     * @return self
     */
    public function setPriority($priority)
    {
        $this->priority = (int) $priority;
        return $this;
    }

    /**
     * Get module priority.
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Set record class namespace prefix. such as "\\App\\Records".
     *
     * @param string $prefix
     * @return self
     */
    public function setRecordNSPrefix($prefix)
    {
        $this->recordNSPrefix = $prefix;
        return $this;
    }

    /**
     * Get record classname.
     *
     * @param string $name suffix classname, such as 'MyRecord'
     * @return string such as '\App\Records\MyRecord'
     */
    public function getRecordClass($name)
    {
        if (class_exists($name)) {
            return $name;
        }
        if ($this->recordNSPrefix) {
            return $this->recordNSPrefix . ucfirst($name);
        }
        return $this->ns . 'Records\\' . ucfirst($name);
    }

    /**
     * Add a hook for app.
     *
     * @param string $name Hook name
     * @param callable $func
     * @return self
     */
    public function addHook($name, $func)
    {
        $this->hooks[] = ['name' => $name, 'function' => $func];
        return $this;
    }

    /**
     * Call the hook.
     *
     * @param string $name Hook name
     * @param array $params
     * @return array Call result
     */
    public function callHook($name, $params = [])
    {
        $result = [];
        foreach ($this->hooks as $hook) {
            if ($hook['name'] == $name) {
                $result[] = [$name => call_user_func_array($hook['function'], $params)];
            }
        }
        return $result;
    }

    /**
     * Add menu for the system.
     * 
     * @param string $name Menu name, for example: "System"
     * @param string $uri Client state or url, such as 'index.abc.xyz' or 'http://xxx.yyy.zzz'
     * @param string $parentModule Parent module must be set when the menu is not top menu, for example: knowledge_management/course_management
     * @param array $opts Extra options, such as i18n etc
     * @return $this
     */
    public function addMenu($name, $moduleName, $uri, $parentModule = null, $opts = [])
    {
        $this->menu[] = [
            'name' => $name,
            'module' => $moduleName,
            'uri' => $uri,
            'parent' => $parentModule,
            'opts' => $opts,
        ];
        return $this;
    }

    /**
     * Get the defined menu.
     * 
     * @return array for example: [{'name' => 'abc', 'module' => 'abc', 'uri' => 'index.abc', 'parent' => null, 'opts' => ['i18n' => ...]}]
     */
    public function getMenu()
    {
        return $this->menu;
    }

    public function setEventFile($eventFile)
    {
        $this->eventFile = $eventFile;
        return $this;
    }

    public function getEventFile()
    {
        return $this->eventFile;
    }

    public function setLanguageFolder($folder)
    {
        $this->langFolder = $folder;
        return $this;
    }
    public function getLanguageFolder()
    {
        return $this->langFolder;
    }

    public function serialize() {
        $data = [
            'name' => $this->getName(),
            'desc' => $this->getDescription(),
            'priority' => $this->getPriority(),
            'systemClassLoader' => $this->getSystemClassLoader(),
            'loaderDef' => $this->getClassLoaderDef(),
            'configureFile' => $this->getConfigureFile(),
            'routePath' => $this->getRoutePath(),
            'pageFile' => $this->getPageFile(),
            'thirdClasses' => $this->getThirdClasses(),
            'dependencies' => $this->getDependencies(),
            'recordNSPrefix' => $this->recordNSPrefix,
            // 'hooks' => $this->hooks, // Serialization of 'Closure' is not allowed
            'menu' => $this->menu,
        ];
        return serialize($data);
    }

    public function unserialize($data) {
        $unserializedData = unserialize($data);
        $this->setName($unserializedData['name']);
        $this->setDescription($unserializedData['desc']);
        $this->setPriority($unserializedData['priority']);
        $this->setSystemClassLoader($unserializedData['systemClassLoader']);
        $this->setConfigureFile($unserializedData['configureFile']);
        $this->setRoutePath($unserializedData['routePath']);
        $this->setPageFile($unserializedData['pageFile']);

        $this->loaderDef = $unserializedData['loaderDef'];
        $this->thirdClasses = $unserializedData['thirdClasses'];
        $this->dependencies = $unserializedData['dependencies'];
        $this->recordNSPrefix = $unserializedData['recordNSPrefix'];
        // $this->hooks = $unserializedData['hooks'];
        $this->menu = $unserializedData['menu'];
    }
}