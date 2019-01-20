<?php

namespace mokuyu\ComposerInstallersExtender;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
    public static function getSubscribedEvents()
    {
        return array(
            'post-update-cmd' => 'update',
        );
    }
    public function install(PackageEvent $event)
    {
        // if ($event != null) {
        //     self::runScript($event, 'install');
        // }
    }
    public function update(PackageEvent $event)
    {
        self::log('---------------------------------------------------plugin update');
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
