<?php

namespace mokuyu\ComposerInstallersExtender;

use ank\App;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }

    public static function getSubscribedEvents()
    {
        return [
            'init-script'           => 'runAllInitScript',
            //'pre-update-cmd'        => 'cmdPostUpdate',
            // 'post-update-cmd'       => 'clearRunfile',
            // 'post-install-cmd'      => 'clearRunfile',
            'pre-package-install'   => 'packageInstall',
            // 'post-package-install'  => "packageInstall",
            'post-package-update'   => 'packageUpdate',
            'pre-package-uninstall' => 'packageUninstall',
        ];
    }

    public function packageInstall(PackageEvent $event)
    {
        if ($event != null) {
            $this->runScript($event, 'install');
        }
    }

    public function packageUninstall(PackageEvent $event)
    {
        if ($event != null) {
            $this->runScript($event, 'uninstall');
        }
    }

    public function packageUpdate(PackageEvent $event)
    {
        if ($event != null) {
            $this->runScript($event, 'update');
        }
    }

    public function runAllInitScript(Event $event)
    {

        if ($event != null) {

            $composer  = $event->getComposer();
            $arr       = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            $dirlist   = [];
            if (!$this->initWeb($vendorDir)) {
                return;
            }
            if (!class_exists('\utils\admin\InitScript')) {
                return;
            }
            foreach ($arr as $key => $value) {
                $dirpath = $vendorDir . '/' . $value->getName() . '/InitScript.php';
                if (file_exists($dirpath)) {
                    $dirlist[] = $dirpath;
                }
            }
            $dirlist = array_unique($dirlist);
            if (!$dirlist) {
                return;
            }
            //输出要在初始化后,否则会导致session_start失败
            $this->log('Start RunInitScript...');
            foreach ($dirlist as $key => $value) {
                $this->log('Running Script: ' . $value);
                $this->runAction($value, 'install');
            }
        }
    }

    protected function initWeb($vendorDir)
    {
        static $isInit = 0;
        $isInit++;
        global $loader;
        $autopath = $vendorDir . '/autoload.php';
        //首次安装这个文件会不存在
        if (!file_exists($autopath)) {
            return false;
        }
        $loader = require $autopath;
        if (!class_exists('\ank\App') || $isInit > 1) {
            return;
        }
        App::start([
            'appEnv'   => 'script',
            'siteRoot' => dirname($vendorDir) . '/web',
        ]);

        return true;

    }

    protected function log($str)
    {
        echo '  - ' . $str . "\n";
    }

    protected function runAction($filePath, $action = 'install')
    {
        try {
            include $filePath;
            $sname = substr($filePath, strripos($filePath, 'vendor/') + 7);
            $sname = strtr($sname, [
                '/InitScript.php' => '',
                '/'               => '\\',
                '-'               => '',
            ]);
            $sname .= '\\InitScript';
            if (class_exists($sname)) {
                $obj = new $sname();
                if (method_exists($obj, $action)) {
                    $obj->$action();
                }

            }
        } catch (\ank\DbException $e) {
            $this->log($e->getmessage());
        }
    }

    protected function runScript($event, $type = '')
    {
        try {
            $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
            if (!$this->initWeb($vendorDir)) {
                return;
            }
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
            if (!class_exists('\utils\admin\InitScript')) {
                return;
            }
            if (preg_match('/(.+?)\-\d+/', $installedPackage, $mat)) {
                $packagePath = $vendorDir . '/' . $mat[1] . '/InitScript.php';
                if (file_exists($packagePath)) {
                    $this->runAction($packagePath, $type);
                }
            }
        } catch (\Exception $e) {

        }
    }
}
