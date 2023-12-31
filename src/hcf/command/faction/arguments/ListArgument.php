<?php

declare(strict_types=1);

namespace hcf\command\faction\arguments;

use abstractplugin\command\Argument;
use hcf\command\faction\threaded\FactionListThreaded;
use hcf\factory\FactionFactory;
use hcf\thread\ThreadPool;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use function igbinary_serialize;

final class ListArgument extends Argument {

    /**
     * @param CommandSender $sender
     * @param string        $commandLabel
     * @param array         $args
     */
    public function onConsoleExecute(CommandSender $sender, string $commandLabel, array $args): void {
        if (($factionsSerialized = igbinary_serialize(FactionFactory::getInstance()->getFactionsStored())) === null) {
            $sender->sendMessage(TextFormat::RED . 'An error occurred while serialized the factions');

            return;
        }

        ThreadPool::getInstance()->submit(new FactionListThreaded(
            $factionsSerialized,
            $sender->getName(),
            isset($args[0]) ? (int) $args[0] : 1
        ));
    }
}