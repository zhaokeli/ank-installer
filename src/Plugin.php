<?php

namespace mokuyu\ComposerInstallersExtender;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private function log($str)
    {
        echo '> ' . $str . "\n";
    }
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
    public static function getSubscribedEvents()
    {
        return array(
            'init-script'           => 'runInitScript',
            // 'pre-update-cmd'        => 'cmdUpdate',
            // 'post-update-cmd'       => 'cmdUpdate',
            'post-package-install'  => "packageInstall",
            'post-package-update'   => "packageUpdate",
            'pre-package-uninstall' => "packageUninstall",
        );
    }
    public function packageInstall(PackageEvent $event)
    {
        if ($event != null) {
            $this->runScript($event, 'packageInstall');
        }
    }
    public function runInitScript(Event $event)
    {

        if ($event != null) {
            $this->log('start runInitScript...');
            $composer  = $event->getComposer();
            $arr       = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            $dirlist   = [];
            foreach ($arr as $key => $value) {
                $dirpath = $vendorDir . '/' . $value->getName() . '/initscript.php';
                if (file_exists($dirpath)) {
                    $dirlist[] = $dirpath;
                }

            }
            $dirlist = array_unique($dirlist);
            defined('SCRIPT_ENTRY') or define('SCRIPT_ENTRY', 1);
            defined('SITE_ROOT') or define('SITE_ROOT', str_replace('\\', '/', dirname($vendorDir) . '/web'));
            $autopath = $vendorDir . '/autoload.php';
            $loader   = require $autopath;
            \ank\App::start('script');
            $action = 'initScript';
            foreach ($dirlist as $key => $value) {
                $this->log('run ' . $value);
                include $value;
            }
        }
    }
    public function packageUpdate(PackageEvent $event)
    {
        if ($event != null) {
            $this->runScript($event, 'packageUpdate');
        }
    }
    public function packageUninstall(PackageEvent $event)
    {
        if ($event != null) {
            $this->runScript($event, 'packageUninstall');
        }
    }
    private function runScript($event, $type = '')
    {
        try {
            $vendorDir = '';
            defined('SCRIPT_ENTRY') or define('SCRIPT_ENTRY', 1);
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            $autopath  = $vendorDir . '/autoload.php';
            if (!file_exists($autopath)) {
                return;
            }
            $loader = require $autopath;
            if (!class_exists('\\ank\\App')) {
                $this->log('\\ank\\App is not found!');
                return;
            }
            defined('SITE_ROOT') or define('SITE_ROOT', str_replace('\\', '/', dirname($vendorDir) . '/web'));
            \ank\App::start('script');
            //这里判断下数据库连接会不会异常
            try {
                $db = \ank\Db::getInstance();
            } catch (\ank\Exception $e) {
                $this->log('database connect error! jump scripts');
                return;
            }
            $this->clearAll();
            if (!$type) {
                return;
            }
            if (!in_array($type, ['packageInstall', 'packageUpdate', 'packageUninstall'])) {
                return;
            }
            $type             = strtolower(str_replace('package', '', $type));
            $installedPackage = '';
            if ($type == 'update') {
                $installedPackage = $event->getOperation()->getTargetPackage();
            } else {
                $installedPackage = $event->getOperation()->getPackage();
            }
            // $this->log('current packageName: ' . $installedPackage);
            if (preg_match('/(.+?)\-\d+/', $installedPackage, $mat)) {
                $packagePath = $vendorDir . '/' . $mat[1] . '/initscript.php';
                if (file_exists($packagePath)) {
                    $action = $type;
                    include $packagePath;
                }
            } else {
                $this->log('not initscript.php! ' . $installedPackage);
            }
        } catch (ClassNotFoundException $e) {

        }
    }
    private function clearAll()
    {
        $cache_type = \ank\App::config('cache.type');
        \ank\Cache::action(null);
        if ($cache_type == 'file') {
            //因为文件缓存不会清理目录所以下面手动清理下目录
            $arr = [];
            //下面只清理啦所有的数据缓存和模板缓存
            $p1    = \ank\App::config('cache.path');
            $p2    = \ank\App::config('runtime_path');
            $p3    = \ank\App::config('template.cache_path');
            $arr[] = \utils\admin\Com::delAllFile($p1 . '/');
            $arr[] = \utils\admin\Com::delAllFile($p2 . '/');
            $arr[] = \utils\admin\Com::delAllFile($p3 . '/');
            //运行时目录缓存
            if (is_array($arr)) {
                //统计缓存大小
                $siz = 0;
                foreach ($arr as $aa) {
                    foreach ($aa as $aaa) {
                        $siz += $aaa['size'];
                    }
                }
                // $this->log("clearCache ok! total:  " . ($siz / 1000) . " k");
            }
        } else {
            // $this->log('clearCache ' . $cache_type . ' ok!');
        }
    }
}
