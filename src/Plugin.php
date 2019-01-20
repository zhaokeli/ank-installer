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
            'post-update-cmd' => 'cmdUpdate',
        );
    }
    public function install(PackageEvent $event)
    {
        // if ($event != null) {
        //     self::runScript($event, 'install');
        // }
    }
    public function cmdUpdate(Event $event)
    {
        $this->log('---------------------------------------------------plugin update');
        // if ($event != null) {
        //     self::runScript($event, 'update');
        // }
    }
    public function update(PackageEvent $event)
    {
        $this->log('---------------------------------------------------plugin update');
        // if ($event != null) {
        //     self::runScript($event, 'update');
        // }
    }
    public function uninstall(PackageEvent $event)
    {
        // if ($event != null) {
        //     self::runScript($event, 'uninstall');
        // }
    }
}
