<?php

declare(strict_types=1);

namespace hcf\command\faction\arguments\leader;

use abstractplugin\command\Argument;
use hcf\command\faction\ProfileArgumentTrait;
use hcf\factory\FactionFactory;
use hcf\factory\ProfileFactory;
use hcf\HCFCore;
use hcf\HCFLanguage;
use hcf\object\profile\Profile;
use hcf\object\profile\ProfileData;
use hcf\object\profile\query\BatchSaveProfileQuery;
use hcf\thread\ThreadPool;
use hcf\utils\ServerUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function count;

final class TransferArgument extends Argument {
    use ProfileArgumentTrait;

    /**
     * @param Player  $sender
     * @param Profile $profile
     * @param string  $label
     * @param array   $args
     */
    public function onPlayerExecute(Player $sender, Profile $profile, string $label, array $args): void {
        if (count($args) === 0) {
            $sender->sendMessage(TextFormat::RED . 'Please use /' . $label . ' transfer <player>');

            return;
        }

        if ($profile->getFactionId() === null || ($faction = FactionFactory::getInstance()->getFaction($profile->getFactionId())) === null) {
            $sender->sendMessage(ServerUtils::replacePlaceholders('COMMAND_FACTION_NOT_IN'));

            return;
        }

        if (!ProfileData::isAtLeast($profile->getFactionRole(), ProfileData::LEADER_ROLE)) {
            $sender->sendMessage(ServerUtils::replacePlaceholders('COMMAND_FACTION_NOT_OFFICER'));

            return;
        }

        if (($factionMember = $faction->getMemberUsingName($args[0])) === null) {
            $sender->sendMessage(HCFLanguage::PLAYER_NOT_FOUND()->build($args[0]));

            return;
        }

        if (HCFCore::released() && $factionMember->getXuid() === $sender->getXuid()) {
            $sender->sendMessage(HCFLanguage::YOU_CANT_USE_THIS_ON_YOURSELF()->build());

            return;
        }

        if (($targetProfile = ProfileFactory::getInstance()->getPlayerProfile($factionMember->getName())) !== null) {
            $targetProfile->setFactionRole(ProfileData::LEADER_ROLE);
            $targetProfile->forceSave(true);
        } elseif (!ThreadPool::getInstance()->submit(new BatchSaveProfileQuery([new ProfileData(
            $factionMember->getXuid(),
            $factionMember->getName(),
            $faction->getId(),
            ProfileData::LEADER_ROLE,
            -1,
            -1,
            -1,
            ServerUtils::dateNow(),
            ServerUtils::dateNow(),
            true
        )]))) {
            $sender->sendMessage(TextFormat::RED . 'An error occurred while save the profile data...');

            return;
        }

        $faction->registerMember($sender->getXuid(), $sender->getName(), ProfileData::OFFICER_ROLE, $profile->getKills());
        $faction->flushMember($factionMember->getXuid(), $factionMember->getName());

        $faction->setLeaderXuid($factionMember->getXuid());
        $faction->forceSave(true);

        $profile->setFactionRole(ProfileData::OFFICER_ROLE);
        $profile->forceSave(true);

        $faction->broadcastMessage(ServerUtils::replacePlaceholders('FACTION_TRANSFERRED', [
            'player' => $factionMember->getName(),
            'who' => $sender->getName()
        ]));
    }
}