<?php

declare(strict_types=1);

namespace hcf\command\faction\arguments\officer;

use abstractplugin\command\Argument;
use hcf\command\ProfileArgumentTrait;
use hcf\factory\FactionFactory;
use hcf\HCFCore;
use hcf\object\profile\Profile;
use hcf\object\profile\ProfileData;
use hcf\utils\ServerUtils;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

final class SetHomeArgument extends Argument {
    use ProfileArgumentTrait;

    /**
     * @param Player  $sender
     * @param Profile $profile
     * @param string  $label
     * @param array   $args
     */
    public function onPlayerExecute(Player $sender, Profile $profile, string $label, array $args): void {
        if ($profile->getFactionId() === null || ($faction = FactionFactory::getInstance()->getFaction($profile->getFactionId())) === null) {
            $sender->sendMessage(ServerUtils::replacePlaceholders('COMMAND_FACTION_NOT_IN'));

            return;
        }

        if (!ProfileData::isAtLeast($profile->getFactionRole(), ProfileData::OFFICER_ROLE)) {
            $sender->sendMessage(ServerUtils::replacePlaceholders('COMMAND_FACTION_NOT_OFFICER'));

            return;
        }

        if (($factionAt = FactionFactory::getInstance()->getFactionAt($loc = $sender->getLocation())) !== null && $factionAt->getId() !== $faction->getId()) {
            $sender->sendMessage(ServerUtils::replacePlaceholders('YOU_CANNOT_DO_THIS_HERE'));

            return;
        }

        $config = new Config(HCFCore::getInstance()->getDataFolder() . 'hq.json');
        $config->set($faction->getId(), [
        	'x' => $loc->getFloorX(),
        	'y' => $loc->getFloorY(),
        	'z' => $loc->getFloorZ(),
        	'world' => $loc->getWorld()->getFolderName(),
        	'yaw' => $loc->yaw,
        	'pitch' => $loc->pitch
        ]);
        $config->save();

        if (!is_array($hq = $config->get($faction->getId()))) {
            $sender->sendMessage(TextFormat::RED . 'An error occurred');

            return;
        }

        $faction->setHq($hq);

        $faction->broadcastMessage(ServerUtils::replacePlaceholders('FACTION_HOME_CHANGED', [
        	'player' => $sender->getName()
        ]));
    }
}