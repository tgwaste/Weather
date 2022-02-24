<?php

declare(strict_types=1);

namespace tgwaste\Weather;

use pocketmine\scheduler\Task;

class Schedule extends Task {
	public function onRun() : void {
		Main::$instance->timer -= 1;

		if (Main::$instance->weather >= Main::LIGHT_THUNDER) {
			if (mt_rand(1, 100) >= 95) {
				Main::$instance->weatherobj->sendLightning();
				Main::$instance->weatherobj->playThunder();
			}
		}

		if (!Main::$instance->timer) {
			Main::$instance->weatherobj->switchWeather(-1);
		}
	}
}

