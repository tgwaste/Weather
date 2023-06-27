<?php

declare(strict_types=1);

namespace tgwaste\Weather;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\LevelEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\World;

class Weather {
	public function switchWeather(int $condition, int $duration=0) {
		if ($condition > -1) {
			Main::$instance->weather = $condition;
		}

		else if (Main::$instance->weather > Main::CLEAR) {
			Main::$instance->weather = Main::CLEAR;
		}

		else {
			Main::$instance->weather = mt_rand(Main::LIGHT_RAIN, Main::HEAVY_THUNDER);
		}

		$this->sendWeatherToPlayers();

		if (Main::$instance->weather >= Main::LIGHT_THUNDER) {
			$this->playThunder();
			$this->sendLightning();
		}

		$this->weatherTimer($duration);

		if (Main::$instance->getConfig()->get("console") == true) {
			Main::$instance->getServer()->getLogger()->info($this->weatherQuery());
		}
	}

	public function getWeatherPackets(World $world) : array {
		$pks = [ new LevelEventPacket(), new LevelEventPacket() ];
		$conds = [0, 5000, 30000, 100000, 5000, 30000, 100000];

		# defaults
		$pks[0]->eventId = LevelEvent::STOP_RAIN;
		$pks[0]->eventData = 0;
		$pks[1]->eventId = LevelEvent::STOP_THUNDER;
		$pks[1]->eventData = 0;

		if ($this->isAlwaysClear($world) == true) {
			# skies are always clear
			return $pks;
		}

		if (Main::$instance->weather > Main::CLEAR) {
			$pks[0]->eventId = LevelEvent::START_RAIN;
			$pks[0]->eventData = $conds[Main::$instance->weather];
			$pks[1]->eventId = LevelEvent::STOP_THUNDER;
			$pks[1]->eventData = 0;
		}

		if (Main::$instance->weather >= Main::LIGHT_THUNDER) {
			$pks[1]->eventId = LevelEvent::START_THUNDER;
			$pks[1]->eventData = $conds[Main::$instance->weather];
		}

		return $pks;
	}

	public function sendWeatherToPlayers() {
		foreach (Main::$instance->getServer()->getWorldManager()->getWorlds() as $world) {
			foreach ($world->getPlayers() as $player) {
				$this->sendWeatherToPlayer($player, $world);
			}
		}
	}

	public function sendWeatherToPlayer(Player $player, World $world) {
		foreach ($this->getWeatherPackets($world) as $pk) {
			$player->getNetworkSession()->sendDataPacket($pk);
		}
	}

	public function playThunder() {
		if (Main::$instance->weather < Main::LIGHT_THUNDER) {
			return;
		}

		$result = Main::$instance->getConfig()->get("thunder");

		if ($result == null or $result != true) {
			return;
		}

		$volumes = [0.1, 0.2, 0.3, 0.4];
		$pitches = [0.2, 0.3];

		foreach (Main::$instance->getServer()->getWorldManager()->getWorlds() as $world) {
			if ($this->isAlwaysClear($world) == true) {
				continue;
			}

			foreach ($world->getPlayers() as $player) {
				$location = $player->getLocation();

				$volume = mt_rand(0, 3);
				$pitch = mt_rand(0, 1);

				$pk = new PlaySoundPacket();
				$pk->soundName = "ambient.weather.lightning.impact";
				$pk->x = (int)$location->x;
				$pk->y = (int)$location->y;
				$pk->z = (int)$location->z;
				$pk->volume = $volumes[$volume];
				$pk->pitch = $pitches[$pitch];

				$player->getNetworkSession()->sendDataPacket($pk);
			}
		}
	}

	public function sendLightning() {
		if (Main::$instance->weather < Main::LIGHT_THUNDER) {
			return;
		}

		$result = Main::$instance->getConfig()->get("lightning");

		if ($result == null or $result != true) {
			return;
		}

		foreach (Main::$instance->getServer()->getWorldManager()->getWorlds() as $world) {
			if ($this->isAlwaysClear($world) == true) {
				continue;
			}

			$players = $world->getPlayers();

			if (count($players) < 1)
				continue;

			$dist1 = [-64, 64];
			$dist2 = [8, -8];

			$player = $players[array_rand($players)];
			$location = $player->getLocation();

			$x = (int)$location->x + $dist1[mt_rand(0, 1)];
			$z = (int)$location->z + $dist2[mt_rand(0, 1)];
			$y = $world->getHighestBlockAt((int)$x, (int)$z);

			if ($y === null) {
				return;
			}

			$eid = Entity::nextRuntimeId();
			$vec = new Vector3($x, $y, $z);

			$light = AddActorPacket::create($eid, $eid, "minecraft:lightning_bolt", $vec, null, 0, 0, 0.0, 0.0, [], [], new PropertySyncData([], []), []);
			$sound = PlaySoundPacket::create("ambient.weather.thunder", $x, $y, $z, 1, 1);

			NetworkBroadcastUtils::broadcastPackets($players, [$light, $sound]);
		}
	}

	public function isAlwaysClear(World $world) : bool {
		$worldname = $world->getFolderName();
		$alwaysclear = Main::$instance->getConfig()->get("alwaysclear");

		if ($alwaysclear == null) {
			return false;
		}

		foreach ($alwaysclear as $entry) {
			if (strpos($worldname, $entry) !== false) {
				return true;
			}
		}

		return false;
	}

	public function weatherTimer(int $duration=0) {
		if ($duration > 0) {
			Main::$instance->timer = $duration;
			return;
		}

		if (Main::$instance->weather == Main::CLEAR) {
			$min = Main::$instance->getConfig()->get("clearmin");
			$max = Main::$instance->getConfig()->get("clearmax");
		} else {
			$min = Main::$instance->getConfig()->get("rainmin");
			$max = Main::$instance->getConfig()->get("rainmax");
		}

		if (is_null($min) or $min == false) {
			if (Main::$instance->weather == Main::CLEAR) {
				$min = 600;
			} else {
				$min = 150;
			}
		}

		if (is_null($max) or $max == false) {
			if (Main::$instance->weather == Main::CLEAR) {
				$max = 3000;
			} else {
				$max = 300;
			}
		}

		Main::$instance->timer = mt_rand($min, $max);
	}

	public function weatherConditionName(int $condition) {
		$conditions = [
			"Clear",
			"Light Rain",
			"Moderate Rain",
			"Heavy Rain",
			"Light Thunderstorms",
			"Moderate Thunderstorms",
			"Heavy Thunderstorms"
		];

		return $conditions[$condition];
	}

	public function weatherQuery() {
		$mins = 0;
		$secs = Main::$instance->timer;

		while ($secs >= 60) {
			$mins += 1;
			$secs -= 60;
		}

		$condition = $this->weatherConditionName(Main::$instance->weather);

		return "Weather conditions §d$condition §f(for $mins mins $secs secs)";
	}
}
