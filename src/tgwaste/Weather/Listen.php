<?php

declare(strict_types=1);

namespace tgwaste\Weather;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;

class Listen implements Listener {
	public function onPlayerJoinEvent(PlayerJoinEvent $event) {
		$player = $event->getPlayer();
		$world = $player->getWorld();

		Main::$instance->weatherobj->sendWeatherToPlayer($player, $world);
	}

	public function onEntityTeleportEvent(EntityTeleportEvent $event) {
		$player = $event->getEntity();
		$world = $event->getTo()->world;

		if ($player instanceof Player) {
			Main::$instance->weatherobj->sendWeatherToPlayer($player, $world);
		}
	}
}
