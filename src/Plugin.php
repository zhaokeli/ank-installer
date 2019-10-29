<?php

namespace mokuyu\ComposerInstallersExtender;

use ank\facade\App;
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
        return [
            'init-script'           => 'runInitScript',
            //'pre-update-cmd'        => 'cmdPostUpdate',
            'post-update-cmd'       => 'clearRunfile',
            'post-install-cmd'      => 'clearRunfile',
            // 'post-package-install'  => "packageInstall",
            'post-package-update'   => 'packageUpdate',
            'pre-package-uninstall' => 'packageUninstall',
        ];
    }
    public function clearRunfile(Event $event)
    {
        $composer  = $event->getComposer();
        $arr       = $composer->getRepositoryManager()->getLocalRepository()->getPackages();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $runpath   = dirname($vendorDir) . '/runtime/runfile/';
        $this->delAllFile($runpath);
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
        if (!defined('SCRIPT_ENTRY')) {
            define('SCRIPT_ENTRY', 1);
            defined('SITE_ROOT') or define('SITE_ROOT', str_replace('\\', '/', dirname($vendorDir) . '/web'));
            $autopath = $vendorDir . '/autoload.php';
            $loader   = require $autopath;
            if (!class_exists('\ank\App')) {
                return;
            }
            \ank\App::start('script');
        }
        return;
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
            foreach ($dirlist as $key => $value) {
                $this->runAction($value, 'initScript');
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
                $this->runAction($packagePath, $type);
            }
        }
    }
    private function runAction($filePath, $act = 'initScript')
    {
        try {
            global $action;
            $action = $act;
            include $filePath;
            $sname = substr($filePath, strripos($filePath, 'vendor/') + 7);
            $sname = str_replace('/InitScript.php', '', $sname);
            $sname = str_replace('/', '\\', $sname);
            $sname = str_replace('-', '', $sname);
            $sname .= '\\InitScript';
            if (class_exists($sname)) {
                $obj = new $sname();
                $obj->run();
            }
        } catch (\ank\DbException $e) {
            $this->log($e->getmessage());
        }
    }
    /**
     * 此功能废弃,为啦把它独立出来
     * @authname [权限名字]     0
     * @Author   mokuyu
     * @DateTime 2019-09-11
     * @return   [type]
     */
    private function clearAll()
    {
        return;
        // if (!class_exists('\ank\App')) {
        //     return;
        // }
        // $cache_type = App::config('cache.type');
        // \ank\facade\Cache::action(null);
        // if ($cache_type == 'file') {
        //     //因为文件缓存不会清理目录所以下面手动清理下目录
        //     $arr = [];
        //     //下面只清理啦所有的数据缓存和模板缓存
        //     $p1    = App::config('cache.path');
        //     $p2    = App::config('runtime_path');
        //     $p3    = App::config('template.cache_path');
        //     $arr[] = \utils\admin\Com::delAllFile($p1 . '/');
        //     $arr[] = \utils\admin\Com::delAllFile($p2 . '/');
        //     $arr[] = \utils\admin\Com::delAllFile($p3 . '/');
        //     //运行时目录缓存
        //     if (is_array($arr)) {
        //         //统计缓存大小
        //         $siz = 0;
        //         foreach ($arr as $aa) {
        //             if (!is_array($aa)) {
        //                 continue;
        //             }
        //             foreach ($aa as $aaa) {
        //                 $siz += $aaa['size'];
        //             }
        //         }
        //         // $this->log("clearCache ok! total:  " . ($siz / 1000) . " k");
        //     }
        // } else {
        //     // $this->log('clearCache ' . $cache_type . ' ok!');
        // }
    }
    /**此方法用来删除某个文件夹下的所有文件
     *@param string $path为文件夹的绝对路径如d:/tem/
     *@param string $delself 是否把自己也删除,默认不删除
     *@param string $delfolder 删除所有文件夹默认为true,如果为false,则只删除所有目录中的文件
     *@返回值为 删除的文件数量(路径和大小)
     *@清理缓存很实用,哈哈
     *@author qiaokeli <735579768@qq.com>  www.zhaokeli.com
     **/
    public function delAllFile($fpath, $delself = false, $delfolder = true)
    {
        defined('YPATH') or define('YPATH', $fpath);
        $files    = [];
        $filepath = iconv('gb2312', 'utf-8', $fpath);
        if (is_dir($fpath)) {
            if ($dh = opendir($fpath)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $temarr = $this->delAllFile($fpath . '/' . $file);
                        $files  = array_merge($files, $temarr);
                    }
                }
                closedir($dh);
            }
            if ($delfolder) {
                //过虑删除自己的情况
                if ($fpath === YPATH) {
                    if ($delself) {
                        $files[] = ['path' => $fpath, 'size' => filesize($fpath)];
                        @rmdir($fpath);
                    }
                } else {
                    $files[] = ['path' => $fpath, 'size' => filesize($fpath)];
                    @rmdir($fpath);
                }
            }
        } else {
            if (is_file($fpath)) {
                $files[] = ['path' => $fpath, 'size' => filesize($fpath)];
                @unlink($fpath);
            }
        }
        return $files;
    }
}
