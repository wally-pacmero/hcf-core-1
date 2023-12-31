<?php

declare(strict_types=1);

namespace hcf\object\profile\query;

use hcf\factory\ProfileFactory;
use hcf\object\profile\Profile;
use hcf\object\profile\ProfileData;
use hcf\thread\datasource\MySQL;
use hcf\thread\datasource\Query;
use hcf\utils\ServerUtils;
use mysqli_result;
use pocketmine\plugin\PluginException;
use function is_array;
use function is_int;

final class LoadProfileQuery implements Query {

    private ?ProfileData $profileData = null;

    /**
     * @param string $xuid
     * @param string $name
     */
    public function __construct(
        private string $xuid,
        private string $name
    ) {}

    /**
     * @param MySQL $provider
     *
     * This function is executed on other Thread to prevent lag spike on Main thread
     */
    public function run(MySQL $provider): void {
        $this->profileData = self::fetch($this->xuid, $this->name, $provider);
    }

    public static function fetch(string $xuid, string $name, MySQL $provider): ?ProfileData {
        $provider->prepareStatement('SELECT * FROM profiles WHERE xuid = ?');
        $provider->set($xuid);

        $stmt = $provider->executeStatement();
        if (!($result = $stmt->get_result()) instanceof mysqli_result) {
            throw new PluginException('An error occurred while tried fetch profile');
        }

        $profileData = null;

        if (is_array($fetch = $result->fetch_array(MYSQLI_ASSOC))) {
            $profileData = new ProfileData(
                $xuid,
                $name,
                ($factionId = $fetch['faction_id'] ?? null) === '' ? null : $factionId,
            $fetch['faction_role'] ?? ProfileData::MEMBER_ROLE,
                is_int($kills = $fetch['kills'] ?? 0) ? $kills : 0,
                is_int($deaths = $fetch['deaths'] ?? 0) ? $deaths : 0,
                is_int($deaths = $fetch['balance'] ?? 0) ? $deaths : 0,
                $fetch['first_seen'],
                $fetch['last_seen'],
                true
            );
        }

        $result->close();
        $stmt->close();

        return $profileData;
    }

    /**
     * This function is executed on the Main Thread because need use some function of pmmp
     */
    public function onComplete(): void {
        ProfileFactory::getInstance()->registerNewProfile(
            $this->profileData === null ? new Profile($this->xuid, $this->name, $now = ServerUtils::dateNow(), $now) : Profile::fromProfileData($this->profileData),
            $this->profileData !== null
        );
    }
}