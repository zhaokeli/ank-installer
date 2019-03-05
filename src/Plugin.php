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
            // 'post-package-install'  => "packageInstall",
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
    private function initWeb($vendorDir)
    {
        global $loader;
        defined('SCRIPT_ENTRY') or define('SCRIPT_ENTRY', 1);
        defined('SITE_ROOT') or define('SITE_ROOT', str_replace('\\', '/', dirname($vendorDir) . '/web'));
        $autopath = $vendorDir . '/autoload.php';
        $loader   = require $autopath;
        \ank\App::start('script');
    }
    public function runInitScript(Event $event)
    {

        if ($event != null) {

            $composer  = $event->getComposer();
            $arr       = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            $dirlist   = [];
            foreach ($arr as $key => $value) {
                $dirpath = $vendorDir . '/' . $value->getName() . '/InitScript.php';
                if (file_exists($dirpath)) {
                    $dirlist[] = $dirpath;
                }
            }
            $dirlist = array_unique($dirlist);
            $this->initWeb($vendorDir);
            //输出要在初始化后,否则会导致session_start失败
            $this->log('start runInitScript...');
            global $action;
            $action = 'initScript';
            foreach ($dirlist as $key => $value) {
                //$this->log('run ' . $value);
                include $value;
                $sname = str_replace($vendorDir, '', $value);
                $sname = str_replace('/InitScript.php', '', $sname);
                $sname = str_replace('/', '\\', $sname);
                $sname = str_replace('-', '', $sname);
                $sname .= '\\InitScript';
                if (class_exists($sname)) {
                    $obj = new $sname();
                    $obj->run();
                }
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
        global $action;
        try {
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            $this->initWeb($vendorDir);

            $this->clearAll();
            if (!$type || !in_array($type, ['packageInstall', 'packageUpdate', 'packageUninstall'])) {
                return;
            }
            $type             = strtolower(str_replace('package', '', $type));
            $installedPackage = '';
            if ($type == 'update') {
                $installedPackage = $event->getOperation()->getTargetPackage();
            } else {
                $installedPackage = $event->getOperation()->getPackage();
            }
            if (preg_match('/(.+?)\-\d+/', $installedPackage, $mat)) {
                $packagePath = $vendorDir . '/' . $mat[1] . '/InitScript.php';
                if (file_exists($packagePath)) {
                    $action = $type;
                    include $packagePath;
                    $sname = str_replace($vendorDir, '', $packagePath);
                    $sname = str_replace('/InitScript.php', '', $sname);
                    $sname = str_replace('/', '\\', $sname);
                    $sname = str_replace('-', '', $sname);
                    $sname .= '\\InitScript';
                    if (class_exists($sname)) {
                        $obj = new $sname();
                        $obj->run();
                    }
                }
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
